# Payments

## One event path

Every payment source converges on the same idempotent pattern
(established in Phase 2):

1. unique `gateway_transaction_id` (DB unique index),
2. amount must equal the invoice total,
3. invoice transitions to Paid exactly once under a row lock,
4. `InvoicePaid` fires exactly once → `HandleInvoicePaid` does order
   transition + provisioning + tax document (or wallet deposit for
   top-ups).

## Providers (Phase 3)

- **Mock gateway** — the only provider that completes payments.
  `ProcessMockPaymentAction`; deterministic `MOCK-{invoice uuid}` txn id;
  `outcome=fail` simulates a declined payment. Gated by
  `PROVISIONING_MOCK_MODE=true`; admin "mark paid" reuses the same action.
- **Credit** — `PayInvoiceWithCreditAction` (see docs/credits-wallet.md).
- **Comgate** — prepared: `ComgateGateway` client + idempotent
  `ProcessComgateWebhookAction` (IP whitelist, server-to-server status
  re-fetch, 3 idempotency gates). Webhook endpoint logs but the gateway
  stays in test mode; no real payment can occur.
- **GoPay / Stripe** — placeholders (vault rows, no clients).

`PaymentProviderRegistry` feeds the admin Integrations screen with
readiness status. Webhook logs are visible under Admin → Platby.

## Going live later

Fill Comgate credentials in the vault + config, disable payment mock mode,
verify webhook IP whitelist and HTTPS callback URL, run a 1 CZK test
transaction in Comgate test mode first, reconcile via variable symbol.
