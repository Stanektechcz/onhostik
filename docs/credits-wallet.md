# Credit wallet (zálohový účet)

## Ledger invariants (unchanged from Phase 1/2)

Append-only `credit_transactions`: no UPDATE/DELETE ever (model guards +
MySQL triggers), signed minor units, `balance_after` computed under a
customer-row lock, authoritative balance = SUM(amount). Overdraw is
impossible (`InsufficientCreditException`).

## Top-up flow (Phase 3)

1. Customer chooses an amount (100 – 50 000 CZK,
   `billing.credit_topup.min/max_minor`) on `/panel/fakturace/kredit`.
2. `CreateCreditTopUpInvoiceAction` issues a proforma with
   `purpose=credit_topup` (audit `invoice.topup_issued`).
3. Customer pays via the MOCK gateway (top-ups cannot be paid from credit —
   guarded in the controller).
4. `HandleInvoicePaid` sees the purpose and deposits via `CreditLedger`
   with the invoice as the ledger reference
   (audit `credit.topup_completed`).

**No double-credit:** the listener checks for an existing deposit
referencing the invoice before writing; the payment path itself is
idempotent (deterministic gateway_transaction_id + InvoicePaid fires once).
Covered by tests (`CustomerPortalTest`).

## Paying invoices from credit

`PayInvoiceWithCreditAction` — one DB transaction, row-locked ledger
deduction, payment row `method=credit`, InvoicePaid event. Unchanged.

## Admin adjustments

Admin → customer detail → "Korekce kreditu": signed amount, **reason
required**, ledger row records the admin id, plus audit
`credit.admin_adjusted`.

## TAX NOTE (accountant review required)

Top-up proformas are issued at 0 % VAT as an advance. Czech VAT treatment
of wallet credit depends on single- vs multi-purpose voucher rules and on
how the credit is consumed. This is a conservative placeholder — confirm
the regime with the accountant before production.
