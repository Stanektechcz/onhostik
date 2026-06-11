# OnHost — Architecture

## Principles

1. **Laravel is the source of truth.** Users, customers, billing, orders,
   payments, invoices, credits, domains, services, provisioning state,
   product catalog, server mappings, logs and settings all live in this app's
   database. External systems only execute or confirm.
2. **aaPanel** is the webhosting execution layer. **WEDOS WAPI** is the
   domain/DNS operation layer. **Comgate** (later GoPay/Stripe) only confirms
   payments. AI providers only assist — they never execute risky actions
   without approval.
3. **Mock-first.** Every connector ships with a mock driver; real API calls
   require explicit approval and `PROVISIONING_MOCK_MODE=false` plus
   per-server `mock_mode=false`. Local/dev never calls real APIs.
4. **Idempotency everywhere.** Webhooks are deduplicated by unique gateway
   transaction id; provisioning checks `Service::external_id` before any
   remote create; invoice numbering locks its sequence row.
5. **Append-only money.** The credit ledger only ever inserts rows
   (model guards + MySQL triggers). Balance = `SUM(amount)`.

## Module layout (DDD)

```
app/Domains/
├── Billing/        orders, invoices (immutable snapshots), payments,
│                   credit ledger, VAT (CZ/EU OSS/reverse charge), Comgate
├── Customer/       Customer (billing identity, 1:1 with User), addresses
├── Products/       Product, PricingPlan (per-currency minor-unit prices)
├── Provisioning/   Service, Server, ProvisioningTask, DomainRegistration,
│   └── Drivers/    AAPanel | Wedos | Proxmox | Pterodactyl (mock first;
│                   Proxmox/Pterodactyl are reserved slots, not implemented)
├── Support/        (placeholder — Phase 15)
└── Shared/         MoneyCast, MoneyFormatter, Currency/Locale enums, HasUuid
```

Layering: **Controller (thin) → Action/Service (domain logic, transactions)
→ queued Job → Driver (behind interface) → external API**. Controllers never
call drivers; drivers never run outside jobs.

## Identity & access

- `App\Models\User` — auth identity (Fortify; registration, login, password
  reset enabled; email verification and 2FA are prepared but switched off
  until the hardening phase).
- `Customer` — billing identity created with registration (1 user : 1 customer).
- Roles via spatie/laravel-permission: `admin`, `customer`, `support`.
- Gate **`access-admin`** (admin role) protects all `/admin` routes and the
  admin section of the panel sidebar.
- Policies (`Service`, `Invoice`, `Order`, `DomainRegistration`, `Customer`)
  enforce per-customer ownership; admins pass via `before()`.

## Frontend strategy

- Public site: **Antler** template, assets published to `public/front`,
  converted page-by-page into Blade (`resources/views/front`,
  components under `components/front`). Template source in
  `homepage-sablona/` is read-only reference — never edited.
- Panel + admin: **Cuba** template, assets in `public/panel`, one shared
  `layouts/panel.blade.php` for customers and admins (admin nav gated by
  `@can('access-admin')`). Source in `laravel-admin-panel/` is read-only.
- Bilingual (cs/en) from day one via `lang/cs` + `lang/en`; locale switch
  stored in session + user profile (`SetLocale` middleware).

## Billing core (implemented foundation)

- `CreditLedger` — append-only wallet; row-locked writes, overdraw-proof.
- `VatResolver` — CZ B2C/B2B 21 %, EU B2C OSS country rate, EU B2B reverse
  charge 0 %, non-EU 0 %. OSS table in `config/billing.php` (review quarterly).
  **Final tax treatment requires accountant review before production.**
- `InvoiceNumberGenerator` — `{SERIES}-{YYYY}-{NNNNNN}` with locked sequences.
- Invoices carry an immutable billing snapshot (legal requirement); proforma →
  tax document → credit note linkage via `parent_invoice_id`.
- `ProcessComgateWebhookAction` — sanitized logging, IP whitelist,
  server-to-server status re-fetch, three idempotency gates. The webhook
  endpoint (`POST /api/webhooks/comgate`) currently only logs; processing is
  wired via a queued job in Phase 5.

## Payment event pipeline (implemented — Phase 2)

Every payment source fires the same `InvoicePaid` event after passing the
same three idempotency gates (unique transaction id, amount match, single
invoice transition): the **mock gateway** (`ProcessMockPaymentAction`,
deterministic `MOCK-{uuid}` id), the **credit ledger**
(`PayInvoiceWithCreditAction`, `CREDIT-{uuid}`, row-locked overdraw-proof
deduction) and later the real Comgate webhook. `HandleInvoicePaid` then
transitions the order, creates `Service` rows idempotently and queues the
provisioning/domain jobs — all auditable. Details: [billing.md](billing.md).

## Provisioning (implemented in MOCK — Phase 2)

`ProvisioningDriverInterface`: create / suspend / unsuspend / terminate /
changePackage / getUsageStats / resetPassword / loginAsUser / testConnection.
Flow: paid order → `Service` (pending) → `ProvisioningTask` → queued job
(`provisioning-high`) → driver via `DriverResolver` (**AapanelMockDriver**;
`WedosMockRegistrar` behind `DomainRegistrarInterface` for domains) →
external mapping saved (`MOCK-AAP-…` / `MOCK-WD-…`) → service active /
domain registered → audit log. Failures mark the task failed (→
manual_review beyond max attempts), are visible and retryable in
`/admin/provisioning`, and never duplicate remote resources. Real drivers
are a later phase; Proxmox/Pterodactyl slots stay reserved.
Details: [provisioning.md](provisioning.md).

## Phase roadmap

0 ✅ discovery · 1 ✅ foundation · **2 ✅ mock vertical slice** (catalog →
checkout → proforma → mock/credit payment → mock WEDOS domain + mock aaPanel
provisioning → customer portal + admin oversight + audit + tests) ·
next: tax documents & PDF invoices · credit top-up · Comgate test-mode
end-to-end · real aaPanel/WEDOS drivers (approval required) · monitoring ·
backups · AI hub · support/automation · hardening · release QA
