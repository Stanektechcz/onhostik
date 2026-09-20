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

## What an order does with the money (blueprint §5.2)

An order **reserves** its total when it is paid (`wallet_holds`, purpose `order`, no expiry). `OrderSettlement::settle()`
runs once every line reached its end (`active` or `failed`):

| Line | Money | Document | Customer |
| --- | --- | --- | --- |
| delivered | captured from the reservation: `DR liability:wallet / CR revenue:<family> / CR liability:vat` | the statement issued at payment stands | `order.active` |
| could not be delivered | its share of the reservation is released — it is spendable credit again | a credit note for exactly those lines; the line turns `refunded` | `order.refunded` (notification + mail `order-refunded`) |

* A **postpaid** order has nothing to capture — the invoice booked the receivable. Its reservation keeps the credit
  line occupied until the invoice is paid (`ReleaseOrderReservation`, and before the payment when it is paid from credit).
* Paying a postpaid invoice from credit or by card **settles the receivable** (`WalletService::settleReceivable`:
  `DR liability:wallet / CR asset:receivable`). It used to go through `charge()` and book revenue and VAT a second time.
* `onhost:orders:settle` (every ten minutes) settles orders whose settlement was interrupted. `orders.meta.settlement`
  holds `captured_minor`, `returned_minor`, `credit_note_id`.
* A line that was given back is **not** delivered by retrying its operation (`order_item_refunded`, 409) — the customer
  orders it again. `order.settlement_failed` (staff) means something was delivered but the reservation was gone and the
  credit did not cover the charge.
* An order nobody paid is cancelled after `ONHOST_ORDER_UNPAID_EXPIRE_DAYS` (14): the proforma is voided, the transfer is
  no longer matched; a late payment arrives as an unmatched bank line for finance.
* A quantity above one is refused at the quote (`quantity_unsupported`): fulfilment builds one service per line.

**Check on staging after deploying:** orders `ACTIVE` whose `wallet_holds.state` is `released`/`expired` and whose
`meta.settlement` is missing were never charged (the old behaviour) — list them and decide per customer:

```sql
select o.number, o.total_minor, h.state from orders o join wallet_holds h on h.id = o.wallet_hold_id
where o.state in ('ACTIVE','PARTIALLY_ACTIVE') and h.state <> 'captured' and o.payment_mode <> 'postpaid';
```

## Periods

`BillingPeriod::end()` — a period ends on the anchor day (the day the service was activated) where the month has one and
on its last day where it has not: 31 Jan → 28 Feb → 31 Mar. `addMonth()` overflowed to 3 March and the renewal day
drifted for good.

## Which dunning case a payment closes

A renewal or a usage charge that went through closes the case **it** caused (`service_id` = that service, no invoice).
A case that hangs on an invoice is closed only by that invoice being paid. (The old rule also closed every case of the
organization that had no service — an unpaid work invoice was marked paid by a 149 Kč renewal.)

## The accounting day and the limits of automatic top-ups (2026-09-20)

* **A date on a document is the day at the seller's seat** (`AccountingClock`, `ONHOST_BILLING_TIMEZONE`, default
  `Europe/Prague`). The servers run in UTC and the tax date and the year of the number series were taken from UTC: an
  invoice issued on 1 January at 00:30 in Prague got **last year's number** and a tax date of 31 December, while its PDF
  already printed 1 January; every document issued between midnight and one or two in the morning carried the previous
  day. Number series (invoices and orders), `supply_date`, line periods and the UBL dates now use the accounting day;
  instants (`issued_at`, `due_at`, `paid_at`) are unchanged. Documents already issued are not touched.
* **Automatic top-ups keep both ceilings the customer set.** The day counter answered 0 or 1, so a cap of two never
  tripped, and the monthly limit was stored and never read: a renewal loop could charge a stored card every hour. Both
  are now counted on the charges really made (payment intents of the stored method): attempts today — a declined card
  is an attempt — against `max_per_day`, and this month's sum plus the new charge against `monthly_limit`, in the
  accounting day and month. The answer is `limited` with the reason; the renewal guard then tells the customer the
  credit is short, as before.

Tests: `tests/Feature/Finance/InvoiceTest.php`, `tests/Feature/Finance/AutoTopupLimitsTest.php`.

## Corrections that hold: credit notes, returns, cancelled orders (2026-09-20, night)

**A credit note knows which line it corrects** (`invoice_lines.corrects_line_id`, `invoices.credited_minor`, migration
`000750`). What a line has left is what it was issued for minus every credit note written against it.

* Before, nothing counted: the same document — or the same lines — could be credited again and again (each time taking a
  postpaid customer's debt down once more), a credit note could itself be credited, and a document with a partial credit
  note was still asked to be paid in full (`pay from credit` charged the printed total). Now: `invoice_line_already_credited`,
  `invoice_credit_exceeds_line`, `invoice_nothing_to_credit`, `invoice_not_creditable` (409); `Invoice::outstanding()` is
  total − credited − paid, and everything that says "what is owed" uses it (payment, bank intent, reports, the panel, the
  assistant). A document whose payment already covers what is left after a credit note turns `PAID`.
* A part of a line can be credited (`amounts`: line id → gross). The VAT is split in the line's own proportion; the last
  part takes the remainder, so the parts add up to the line to the haler.
* A document booked at issue (postpaid) gives back its revenue **and its VAT**: DR `revenue:credit_note` (net), DR
  `liability:vat` (tax), CR receivable. The whole gross used to be debited to revenue, so every credit note left the VAT
  account too high by its tax. What the customer had paid beyond what is still owed returns to their credit (DR receivable
  / CR wallet) — once (`meta.overpaid_returned_minor`).
* Staff API: `POST /v1/invoices/{id}/credit-note` takes `line_ids`, `amounts` and `return_to_credit`. Without the flag
  the note is a document (as before — finance move the money themselves); with it, what was really paid for those
  amounts returns to the customer's credit, once.

**Money that comes back is not a top-up** (`WalletService::returnToCredit`). The return of a cancelled service's unused
period and a refunded marketplace order were booked as `DR asset:bank:chargeback|marketplace / CR wallet` — money arriving
at a bank that does not exist — and as *purchased* credit: a service bought with bonus credit and then given back became
cash that could be paid out. A return is now taken from what the correction takes back (revenue + VAT, or the receivable),
the wallet row is `source=return`, `bucket=returned`, `refundable=false`, and `refundableBalance()` — purchased top-ups
minus refunds — does not grow. The event stays `wallet.topup.completed` (`purpose: return`), so a past-due renewal waiting
for money is retried.

**The return of an unused period is computed from what was paid** (`ChargebackService::estimate`). It was
`subscription.amount_minor × unused share`: the list price of ONE period without VAT. Twelve months paid in advance
returned a share of one month; everybody lost the VAT; an order bought with an 80 % code returned more than was paid.
Now: every document line of the service whose period has not run out (by `service_id`, or by the order item that created
or upgraded the service), the unused days of what the line has left, times the share. Today counts as used. The lines and
amounts are fixed when the cancellation starts (`chargeback_requests.basis`), and the settlement writes a credit note for
exactly those parts (the period on it is the unused one). An **unpaid** postpaid invoice gets smaller instead — no credit
is paid out for money that never came (`to_credit` / `off_documents` in the API). A service nobody paid for (created by
staff without an order) has nothing to return: `chargeback_no_subscription`. The settlement locks the request row and
reads its state again — an event delivered twice gives nothing back twice.

**A paid order that is cancelled gives everything back.** `PAID → CANCELLED` (a rejected review, staff on the customer's
request) released the reservation but left the document standing: a postpaid customer kept owing — and was dunned — for an
order never delivered, a prepaid one kept a statement for services they never got. Every tax document of the order is now
credited for the lines that were never delivered, the lines turn `refunded`, and the customer is told where the money is
and which document corrects the first one (`order.cancelled` with `returned`, mail `order-cancelled`). The staff reason
goes to the audit and into the credit note's `meta.reason`; it is never shown to the customer.

Document periods: an upgrade inside a running period ends where that period ends (it said "a whole period from today");
a renewal line's period is accounting days and ends on the last day of the period.

Tests: `tests/Feature/Finance/CreditNoteTest.php`, `tests/Feature/Billing/ChargebackTest.php`,
`tests/Feature/Orders/OrderTransitionGateTest.php`.

Look at on staging: cancel a held order from the console (the reason prompt, the credit note among the customer's
documents, the credit back); a chargeback of a service paid a year in advance (the estimate in the panel names the
document); `onhost:doctor` area `money`.
