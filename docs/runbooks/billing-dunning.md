# Billing, dunning and suspension

## Dunning ladder (config `onhost.billing.dunning`)

DUE → OVERDUE_NOTICE (day 3, 7, 14 notices) → GRACE → SUSPENDED (day 30) → TERMINATION_SCHEDULED (day 60)
→ TERMINATED, or RESOLVED as soon as the invoice is paid or the wallet covers the renewal.

* `onhost:billing:dunning` runs daily 06:00; `POST /v1/staff/dunning/run` runs it on demand.
* Suspension runs the regular suspend saga per service (provider-side, reversible); resume is automatic on
  payment (`SettleBillingAfterPayment`). Termination keeps the retention window
  (`compliance.retention_after_termination_days`) with a final backup.
* Domains are excluded from suspension: a domain renewal that cannot be paid follows the renewal runbook.

## Manual actions

| Need | Endpoint | Notes |
| --- | --- | --- |
| Mark a bank transfer as received | `POST /v1/invoices/{id}/mark-paid` | reference required; posts the ledger and resolves dunning |
| Credit note | `POST /v1/invoices/{id}/credit-note` | full or per line; postpaid receivables are reversed |
| Wallet adjustment | wallet adjust command | reason mandatory; large amounts need four-eyes approval |
| Refund | refund command | step-up; `billing.refund.execute_large` above the threshold |
| Change billing mode to postpaid | credit line command | finance only; opens a receivable account |
| Cancel an unpaid order | `POST /v1/orders/{id}/transition` `{to: CANCELLED}` (customer or staff) | voids the proforma (`invoice.cancelled`, audit `invoice.cancel`) and cancels the bank payment intent, so a late transfer with that symbol lands in reconciliation instead of paying a dead order; paid documents are never voided — use a credit note |
| Bank-transfer top-up | `POST /v1/payments/init` `{provider: bank}` | variable symbol series `9` + year + sequence (`TU` sequence), distinct from document symbols (year + sequence); every attempt is its own intent |

## The customer's monthly budget (Brain card H30)

`GET/PUT /v1/wallet/budget` (`billing.budget.manage` to change, `billing.wallet.read` to see):
`{limit, hard?, alert_thresholds?[1..100], max_single_service?}`; `limit: 0` removes it. One budget per organization
in its currency, counted from the first of the month — the first touch in a new month starts it from zero and arms
its warnings again (`budget.threshold`, once per share and month).

* A **hard** budget refuses what would go over it: an order's hold (money already held by open orders counts as
  spent) **and direct charges** — renewals and metered usage. A refused renewal or usage charge is handled exactly
  like one the credit does not cover: the subscription goes past due, a dunning case opens, and the customer is told
  the cause is the budget, not the credit (`cause: budget` on `subscription.renewal_failed` / `usage.charge_deferred`).
  Raising the budget and the next retry settle it.
* **Settling an invoice that already exists is never refused** (`charge(..., enforceBudget: false)`): a debt is not
  a purchase. It still counts as money spent this month, so the warnings stay truthful.
* A repeated charge with the same key is one charge and is counted against the budget once.

## Paid work on a ticket (Brain card H29)

Support covers the infrastructure. Administering the customer's own system, repairing their application or custom
development is work outside the plan and needs a price the customer approved:

1. Support offers it on the ticket: `POST /v1/staff/tickets/{ticket}/work-offers` `{scope: administration|application|development,
   description, price_net, minutes?}` (`support.ticket.manage`). `scope: infrastructure` is refused (`work_in_scope`) —
   that is what the plan pays for. The customer reads the offer in the ticket and gets the ordinary reply mail.
2. The customer answers: `POST /v1/tickets/{ticket}/work-offers/{offer}/decision` `{approve, note?}`. Approving takes
   `catalog.order.create` (the right that places orders — a support contact can read the offer and decline it, not accept
   it). Nothing is charged at approval. An offer is valid `ONHOST_WORK_OFFER_VALID_DAYS` (14) days; a declined or
   expired one is not revived — make a new offer.
3. When the work is done: `POST …/work-offers/{offer}/complete`. It bills exactly the approved net price (the amount is
   not a parameter) plus tax: from credit when it covers the total, otherwise by an invoice with the usual due date and
   a dunning case. Anything not approved answers 409 `work_offer_not_approved`. A second "complete" bills nothing.
4. `…/withdraw {reason}` takes back an open or approved offer that will not be carried out; nothing is billed.

## Bank transfers (proformas and top-ups)

Transfers have no webhook. Incoming statement lines are matched by variable symbol + amount to the pending bank
intent (`PaymentService::matchBankLine`); a match settles the intent, pays the proforma (fulfilment starts) or credits
the wallet, and issues the receipt. Sources of lines:

| Source | How |
| --- | --- |
| Fio API | `ONHOST_BANK_FIO_TOKEN` (read-only token of the incoming-payments account); `onhost:bank:sync` runs every 5 minutes (`--from=YYYY-MM-DD --to=` for a date range); Fio keeps the download bookmark, one request per 30 s |
| Another bank / by hand | Nastavení → Bankovní platby (`POST /v1/staff/payments/bank/lines`, permission `billing.reconcile`): VS, amount, currency, optional statement id — the pending list has a "Do formuláře" shortcut |

Every line is stored once (`bank_statement_lines.external_id`); a wrong amount opens a `bank_amount_mismatch`
reconciliation item instead of paying, a symbol nobody waits for stays `unmatched` for finance to look at.

## Reconciliation

`finance.reconciliation.mismatch` means the bank statement import does not match ledger postings. Compare the
statement line with `ledger_transactions` (idempotency key `payment:<intent>`), never edit postings; post a
correcting transaction with a reference to the statement line.

## Reports

`GET /v1/staff/reports/mrr | collections | churn | revenue` feed the admin `#/reporty` view; the numbers are
computed from ledger and subscriptions, not from orders.
