# Billing, dunning and suspension

## Dunning ladder (config `onhost.billing.dunning`)

DUE → OVERDUE_NOTICE (day 3, 7, 14 notices) → GRACE → SUSPENDED (day 30) → TERMINATION_SCHEDULED (day 60)
→ TERMINATED, or RESOLVED as soon as the invoice is paid or the wallet covers the renewal.

* `onhost:billing:dunning` runs daily 06:00; `POST /v1/staff/dunning/run` runs it on demand (fresh step-up: the run can
  suspend and terminate, so it asks for the same step-up the bus would — TASK-0030 WP-B).
* Suspension runs the regular suspend saga per service (provider-side, reversible); resume is automatic on
  payment (`SettleBillingAfterPayment`) — for a *suspension* only. Once the case is TERMINATED the service is
  cancelled (deactivated, restore window running) and a later payment brings it back only through "Pay and restore"
  below, which is off until the owner switches it on. Termination keeps the retention window
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
* **A quantity is that many lines** (2026-09-20). One line is one service; a quantity used to be priced × N (renewals too)
  while ONE service was delivered, then it was refused outright — and the storefront cart offers a quantity, so an order for
  two servers could not be placed at all. `QuoteService::expandQuantities()` turns `qty: 3` into the lines `l1`, `l1#2`,
  `l1#3`: each has its own price, its own share of a discount (a fixed-amount code is still spent once), its own service,
  subscription and document line — so a return or a credit note for one of the three servers works like any other. The
  add-on lines of a line are copied with it, each copy attached to its own parent (`addon_quantity_mismatch` when an add-on
  comes in another quantity than its service). Refused: a domain name, a plan change, a line that names one site
  (`quantity_unsupported`), more than `ONHOST_ORDER_MAX_QUANTITY` (10) of one line (`quantity_too_large`), more than
  `ONHOST_ORDER_MAX_LINES` (50) lines on one order (`order_too_large`). Test: `tests/Feature/Orders/PaidForIsWhatYouGetTest.php`.

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

## Dunning acts on the service, not on the case (2026-09-20, night)

The case said `SUSPENDED` and `TERMINATED`; nobody looked at the service.

* A suspension the panel refused — another operation in the way, the node down, the operation failing later — was **never
  asked for again**: the tick suspends only from `OVERDUE_NOTICE`/`GRACE`, and the case had already moved on. The site ran;
  the next thing that happened to it was the termination date.
* At the termination date the service was asked to terminate only when it was `SUSPENDED`. A service that had never been
  suspended was skipped, and the case was closed as `TERMINATED` — the site then ran for nothing, with no case left to
  notice (an invoice-driven case has no renewal loop that would open another one).
* Now: while a case is `SUSPENDED` or `TERMINATION_SCHEDULED` and its service still runs, the suspension is requested
  again once a day (a new idempotency key — the old one answers with the failed operation), action `suspend_retry`, and
  staff get *Neplacená služba stále běží* (`dunning.enforcement_failed`, internal, hot). At the termination date a
  running service is cancelled like a suspended one (the final backup first, as always), and the case turns `TERMINATED`
  only once the service is down (`TERMINATING`/`TERMINATED`, or deactivated with `terminate_at` set); until then the
  request is repeated daily and a refusal is reported the same way. While the platform's mail does not leave, nothing is
  enforced (H24) — unchanged.

Tests: `tests/Feature/Billing/DunningEnforcementTest.php` (both holes proven against the old code first).

## VAT in CZK on documents in another currency (2026-09-20)

A tax document issued in EUR has to state its VAT **in CZK** (§ 29 (1) l) of the Czech VAT act), converted at the rate of the
Czech National Bank valid for the day the tax is due (§ 4). It carried neither the rate nor the amount.

* **Which documents:** `invoice`, `receipt` (the tax document of a top-up) and `credit_note`. A proforma is a request to pay
  and a statement only lists what the credit paid for — neither is a tax document.
* **Which rate:** the list valid for the document's supply day (`CnbRates::rateFor`): the latest stored list that is not
  younger than the day and not older than `ONHOST_FX_MAX_AGE_DAYS` (7). The bank publishes at 14:30 on working days; before
  that, and over a weekend, the previous list is the valid one — the document prints the date of the list it used.
  **A credit note uses the rate of the document it corrects** (§ 42), never the rate of the day it is written.
* **How it is computed:** every VAT rate of the summary is converted on its own (integers, half away from zero) and the
  totals are their sums, so the recap adds up. `meta.czk` = `source, basis, valid_on, amount, rate, rate_micro, net_minor,
  tax_minor, total_minor, summary[]`; printed on the PDF, exported in the e-invoice (`TaxCurrencyCode` = CZK and a second
  `TaxTotal` in CZK — EN 16931 BT-6 / BT-111) and returned by the API (`czk`).
* **Where the rates come from:** `onhost:fx:sync` (working days 14:40 and every day 06:10 Prague time) stores the list for the
  currencies in `onhost.billing.currencies`. While a document is being issued and the day's list is not stored yet, the
  bank is asked — at most once in a quarter of an hour, 5 s timeout (`ONHOST_FX_FETCH=false` turns that off; the scheduled
  sync stays).
* **The bank does not answer:** the document is issued all the same, with `meta.czk_pending`; the next `onhost:fx:sync`
  adds the recap, makes the structure and the PDF again and writes `invoice.czk_statement.completed` to the audit. Nothing
  the document was issued for changes. `onhost:doctor` (area `money`) shows documents that wait for more than a day, and
  says when the latest list is older than five days.
* **For the accountant to confirm:** the company's directive uses the daily rate of the ČNB (the default the law gives). A
  fixed monthly rate would need another source here.

Tests: `tests/Feature/Finance/ForeignCurrencyVatTest.php`.

## Pay and restore (owner decision 23, TASK-0025)

A cancelled service waits out its restore window deactivated (state SUSPENDED, `terminate_at`, `tags.deletion`). Before
TASK-0025 only the customer's own cancellation could be taken back, and nothing restarted its billing. Now, with the
automation rule **`services.reinstate`** switched on (staff console → Automatizace; `default_off`):

| Situation | What happens |
| --- | --- |
| Dunning cancelled the service (case TERMINATED) and the invoice is paid later | `invoice.paid` → `ServiceReinstatement::afterInvoicePaid`: when the paid invoice still covers the period, the service comes back with no further charge; otherwise the customer is told what is missing (`service.reinstatement.awaiting_payment`) |
| The subscription expired or dunning cancelled a wallet renewal | the customer uses **Zaplatit a obnovit** (`GET /v1/services/{id}/reinstatement` quote, `POST /v1/services/{id}/reinstate`); one new period from today at the subscription's own price is charged from the credit (statement, `meta.reinstatement = true`), or invoiced for a postpaid organization |
| The credit is short | the wish is recorded (`tags.reinstatement`, bound to this cancellation and to who asked), the answer is `awaiting_payment` with the shortfall; the next top-up restores it (never at a higher price than quoted — then the customer is told instead). A top-up alone never charges a service nobody asked to restore. The wish ends with its cancellation (any resume, a new cancellation) and is dropped before any charge when who asked may no longer spend the credit (`service.reinstatement.dropped`) |
| An overdue invoice still belongs to the service | `awaiting_invoices`: the invoice is paid through the ordinary invoice payment and the restore follows it |
| The customer takes back their own cancellation | taking it back bills the service again, so two things are asked (TASK-0027 + review round 1): first the restore command's own permission `billing.wallet.topup` (the permission of paying an invoice from the credit) held by a user at the organization — a developer, cloud or game operator, a `svc_manage`/`svc_console` guest of the one service, their `services:power` token, an assistant or a service account get `403 credit_spend_not_allowed` with `permission: billing.wallet.topup` whatever the switch says; then the one credit gate (`Orders\CreditOrderPolicy`): with `ONHOST_ORDER_CREDIT_APPROVAL` on only the owner and the billing admin (`billing.wallet.spend`), so an org_admin gets `403 credit_spend_not_allowed` with `permission: billing.wallet.spend`; with it off (default) the owner and the org_admin (the billing admin holds no `service.manage` for the resume itself and pays with `POST /reinstate`), as for paying an invoice from the credit. Staff are not asked. The plain resume works while the paid period runs, and the subscription runs on (`RestartBillingAfterRestore`); after the period ended the resume answers `402 reinstatement_payment_required` with the quote (the credit and the invoices in it only for `billing.wallet.read`) |
| Abuse or staff hold, legal hold, purged, window over, add-on, carried site | refused (`409 reinstatement_refused`, `reason`); money never lifts a quarantine |

Order of the restore (inside one transaction, the service row locked): charge (key `sub_reinstate:{subscription}:{cancellation}`,
so a retry or a second click never charges twice) → the scheduled removal is called off (`service.deletion.cancelled`) →
the ordinary `resume` as the platform, lifting only the `payment` hold → `service.reinstated`. If the resume is refused
on the spot (a provisioning freeze, a maintenance window), all of it is rolled back — nothing is charged, the removal stays
scheduled — and the answer is `409 reinstatement_refused` (`reason` resume_refused, `cause`); when a payment triggered it,
`service.reinstatement.failed` goes to staff.
While the parent's resume has not run yet, the nightly purge refuses its carried sites (`parent_reinstated`).

Spending the credit goes through the one credit gate every other payment from the credit uses (`Orders\CreditOrderPolicy`,
TASK-0027): `POST …/reinstate` needs `billing.wallet.topup` on the bus (the permission of paying an invoice from the credit;
never an API token), and while `ONHOST_ORDER_CREDIT_APPROVAL` is on only the owner and the billing admin
(`billing.wallet.spend`) pass — anybody else gets `403 credit_spend_not_allowed`. A recorded request is paid only while who
asked still holds `billing.wallet.topup` and passes the gate as it stands at the payment, otherwise it is dropped
(`service.reinstatement.dropped`). The next renewals end on the day the period restarted (`tags.billing_anchor_day`).

A chargeback-cancelled service (the unused period was returned as credit) can never be resumed by the customer for
free: with the rule off the resume answers `409 chargeback_cancelled`; with it on, a whole new period is owed. Staff can
still resume; its billing then restarts from today. A service the consumer withdrew from cannot get a chargeback at any
stage (`409 withdrawn` for the request, the decision and the cancellation).

A restore never switches auto-renew on (TASK-0027), whoever triggers it — a payment, staff, the audit command's `--apply`,
the customer: the subscription keeps the auto-renew recorded for it at this cancellation (`deletion.subscription` /
`deletion_cancelled.subscription`), else the cancelled row's own value, and nothing recorded means off. A service whose
customer had auto-renew off and whose paid period is over therefore ends again at the next renewal pass unless it is paid
for (`reinstate`) or the owner or billing admin switches auto-renew on — `--apply` warns about exactly that, and so does
the answer to a staff resume (`warning.code: restore_ends_at_renewal`). The renewal pass asks for one termination per
period that ended (`sub_expire:<subscription>:<period end>`, review round 1): with a key of the subscription alone the
second expiry got the first expiry's operation back and the restored service ran on unbilled.

**Before switching the rule on:** `php artisan onhost:billing:reinstatement-audit` (read-only) lists (a) undone
cancellations whose subscription stayed CANCELLED and run unbilled, (b) services in the window whose dunning invoice is
already paid, (c) services in the window held for payment, (d) undone cancellations whose carried sites were purged
(only a support `archive.restore` helps those). `--apply --service=<id>` restarts the billing of one service of list (a)
from today, without billing the free time back — one service at a time, the owner decides each. Not restored: add-ons
cancelled with the parent (the quote lists them in `addons_not_restored`) and delegated panel logins removed at the
deactivation.

## Consumer withdrawal within 14 days (owner decision 17, TASK-0025)

Off until the owner switches on the automation rule `billing.withdrawal` (staff console → automation). Before that a lawyer
reviews the mechanism (`resources/legal/LEGAL_REVIEW_withdrawal.md`); afterwards set `ONHOST_WITHDRAWAL_LEGAL_REVIEWED=true`
on the server — until then the doctor row "consumer withdrawal reviewed by a lawyer" warns while the rule is on.

- **Who:** consumers only — the class the order was placed as (`orders.meta.customer_class`, older orders: a recorded
  `withdrawal_waiver` consent means a consumer). An IČO added later keeps the right; a business order never had it.
- **Until when:** 14 days from the order day (`orders.placed_at`, accounting day), to the end of the 14th day. The day the
  notice was **sent** decides. A registered domain is never withdrawn (`withdrawal_not_applicable`, `why=domain_registered`);
  the cart says so (`withdrawal_notice` in the quote). An add-on goes with its service; a carried site has no contract.
- **Panel:** `GET /v1/services/{id}/withdrawal` (eligibility, deadline, estimate); `POST` with
  `confirm_refund_to_credit: true` (the consumer's express agreement to a refund to the credit, recorded as a consent),
  `service.delete`, HIGH, fresh step-up. A paid order nothing of which was delivered: `GET/POST /v1/orders/{id}/withdrawal`
  (the order is cancelled, every line credited, the reserved credit freed).
- **Letter or e-mail:** finance records it with the day it was sent: `POST /v1/staff/withdrawals`
  (`organization_id`, `service_id` or `order_id`, `sent_at`, `refund_to_credit_agreed`, `reason`), `billing.refund.execute`,
  step-up and a second person. Without the consumer's agreement to a credit refund the old manual path stands: a refund by
  the original payment method through the finance tools.
- **What happens, in this order:** the service is suspended (hold `withdrawal`), then a credit note for exactly the unused
  part of each paid line (prorated by days, the notice day counts as used; add-on lines included; an unpaid invoice is
  reduced instead of money being paid out) goes back to the credit with `returnToCredit`, then the service is cancelled
  through the ordinary terminate saga with its final backup. Auto-renewal is switched off at the notice; an open chargeback
  request becomes `withdrawn`; one cancelling blocks the withdrawal (`chargeback_in_progress`).
- **Once:** one withdrawal per order line (`withdrawals.subject_key` unique); the refund is made once under a row lock; the
  operations carry fixed keys (`withdrawal:{id}:suspend|terminate`).
- **Stuck:** a refused step (legal hold, frozen provisioning, panel refusal) is kept on the row (`error`), sent once to the
  finance inbox (`withdrawal.stalled`) and retried hourly by `onhost:withdrawals:finish` (`--dry-run` lists). The refund is
  never taken back because the cancellation waits. `GET /v1/staff/withdrawals?state=open` lists them; the doctor row
  "consumer withdrawals move on" counts refused steps and notices not refunded after 7 days.
- **Afterwards:** the customer cannot resume the service (`service_suspension_held`, hold `withdrawal`) and pay and restore
  refuses it (`held`); staff can resume it with a reason — the refund stays, so that is a deliberate decision.

## Who may pay from the credit (owner decision 20, TASK-0021)

With `ONHOST_ORDER_CREDIT_APPROVAL` off (the default) whoever holds a payment's own permission pays from the credit, as
before. Switched on, only the owner and the billing admin (`billing.wallet.spend`) do: a credit order of anybody else is
held for their approval, and every immediate payment from the credit — an invoice, a manual domain renewal, the
marketplace, a work offer, the archive download fee, pay-and-restore — asks the one gate (`Orders\CreditOrderPolicy`,
`credit_spend_not_allowed`). The approval flow and the operator steps are in `docs/runbooks/approvals.md`, the rule in
`docs/runbooks/security-boundaries.md` §22.

Known wording gap: the customer notice `service.deletion.scheduled` says *Obnovit službu můžete do N dnů*. That is true
for a cancellation the customer takes back, and for pay-and-restore only once `services.reinstate` is on; with the rule
off a service cancelled for non-payment comes back only through support (TASK-0025 handoff, concern 6).
