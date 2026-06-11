# Billing

## Money model

All amounts are `Brick\Money\Money` stored as **integer minor units**
(haléře/cents) next to a sibling `currency` column (`MoneyCast`). Never floats.
Plan prices are **net** (excl. VAT); orders/invoices carry `subtotal` (net),
`tax_amount` and `total` (gross). VAT resolution: `VatResolver`
(CZ 21 %, EU OSS per country, EU B2B reverse charge 0 %, non-EU 0 %).

## Order → proforma flow (Phase 2)

1. `CreateOrderAction` — order + item with an immutable pricing snapshot
   (`unit_price`, `vat_rate`, `period_from/to`, `config` incl. optional
   domain). Status `pending`. Audit: `order.created`.
2. `IssueProformaInvoiceAction` — proforma (`InvoiceType::Proforma`) with:
   - number from `InvoiceNumberGenerator` (series by VAT scenario:
     `CZ-`/`EU-`/`INT-`, gap-free, row-locked sequence),
   - `variable_symbol` = digits of the number (max 10),
   - immutable **billing snapshot** copied from the customer; the address may
     be empty on a proforma — issuing the future **tax document** will require
     completed billing details,
   - items copied from order items. Status `sent`. Audit: `invoice.issued`.
   Idempotent: an order never gets a second non-cancelled proforma.

## Payment paths — one shared event pipeline

Every payment source converges on the same three idempotency gates and the
same `InvoicePaid` event (`ProcessComgateWebhookAction` pioneered the
pattern; the mock and credit actions replicate it):

| gate | mechanism |
|---|---|
| #1 unique attempt | `gateway_transaction_id` UNIQUE + row lock |
| #2 amount match | paid amount must equal `invoice.total` |
| #3 single transition | invoice → `paid` under row lock, exactly once |

- **Mock gateway** (`ProcessMockPaymentAction`): deterministic transaction id
  `MOCK-{invoice uuid}` → a double submit can never double-process. Supports
  simulated failure (`outcome=fail`), which records a `failed` payment and
  transitions nothing. Route gated by `PROVISIONING_MOCK_MODE`.
- **Credit** (`PayInvoiceWithCreditAction`): single DB transaction;
  `CreditLedger::deduct()` locks the customer row, recomputes the balance
  (`SUM(amount)`) and inserts an append-only ledger row — overdraw is
  impossible; on MySQL the ledger is additionally trigger-protected.
  Transaction id `CREDIT-{invoice uuid}`; payment method `credit`.
- **Comgate (real)** — wired (`ProcessComgateWebhookAction` + webhook route),
  but the gateway client stays in test mode; no real calls in Phase 2.

`InvoicePaid` → `HandleInvoicePaid` listener:
order → `processing` (+ `paid_at`, exactly once, audited as `order.paid`),
one `Service` per order item (idempotent by `order_item_id`), then queued
provisioning/domain jobs. See [provisioning.md](provisioning.md).

## Proforma-first; tax documents later

Phase 2 issues **only proformas** (zálohová faktura — not a tax document).
The Phase 3+ transition: after payment, issue `InvoiceType::Invoice`
(daňový doklad) linked via `parent_invoice_id`, with `taxable_supply_date`,
completed billing snapshot validation, and PDF rendering (spatie/laravel-pdf).

## Credit top-up

Deliberately missing in Phase 2: customers cannot top up credit via UI yet
(admins/seeders use `CreditLedger::deposit()`). The panel credit page shows
balance + full ledger history and explains this.
