# Security model

## Authentication & authorization

- Fortify auth (login/register/reset/confirm), roles via Spatie Permission
  (admin/customer/support), `access-admin` gate on every admin route.
- Policies: Service, Invoice (view+pay), Order, DomainRegistration,
  Customer, SupportTicket — customers only ever reach their own records;
  cross-customer access is covered by tests (IsolationTest + portal tests).
- Controllers resolve the customer from the authenticated user only —
  never from request input.

## Secrets

- Integration credentials and server API keys: encrypted at rest
  (Laravel Crypt AES-256), `$hidden`, masked display, never echoed back,
  never logged (audit stores credential KEY NAMES only).
- Mock provisioning credentials are returned once and never persisted
  into task results (`sanitizeResult()` keeps the username only).
- Webhook payloads are sanitized before logging (secrets stripped).

## Real-call protection (the core guarantee of this phase)

Five independent gates must open before any real external write:
active row + mock off + dry-run off + env approval flag + credentials.
Env gates (`AAPANEL_ALLOW_REAL_WRITES`, `WAPI_ALLOW_REAL_WRITES`,
`AI_ALLOW_REAL_CALLS`) default to false and are not part of the admin UI
on purpose — flipping checkboxes can never reach a real backend.

## Auditability

Spatie activitylog on models (orders, invoices, payments, services,
customers, integrations) plus explicit business events for every
transition: order.*, invoice.*, payment.*, credit.*, provisioning.*,
domain.*, monitoring.*, backup.*, support.*, ai.*, integration.*.
High-risk AI actions require explicit admin approval records.

## Financial integrity

Append-only credit ledger (model guards + MySQL triggers), row-locked
balance computation, idempotent payment processing (unique txn ids,
single InvoicePaid emission), gap-free locked invoice numbering.

## Known gaps (tracked, intentional)

2FA not enabled yet (Fortify-ready), no rate limiting on panel POST
endpoints beyond Fortify login + domain-check, CSP headers not configured,
session hardening review pending — see docs/production-checklist.md.
