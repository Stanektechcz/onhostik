# Partner commission grace period (owner decision R7)

> R7 (`docs/audit/2026-10-full-readiness/ROZHODNUTI.md`): *Partnerská provize se stává splatnou až 30 dní po zaplacení
> faktury. Přibude unikátní index `(invoice_id, kind)`.* Implemented by TASK-0097.

## What happens to a commission

| State | Meaning | How it gets there |
| --- | --- | --- |
| `pending` | earned, waiting out 30 days from the client's payment (`payable_at`) | `invoice.paid` → `PartnerService::accrueForInvoice`; the marketplace share on acceptance and on every monthly renewal (`MarketplaceService`) — all through `CommissionGrace::book` |
| `payable` | may be paid out | the hourly maturing job, once `payable_at` passed |
| `allocated` / `paid` | in a payout / paid (unchanged, `PartnerPayouts`) | payout request / four-eyes payment |
| `cancelled` | given back in full inside the window; never payable | a credit note while the commission is `pending` |

- **Inside the window** (the commission is still `pending`) a credit note on the commissioned invoice — a refund, a withdrawal,
  a cancellation ("chargeback") refund: each of them writes one — cancels the commission when it gives back the whole net, or
  adds a `pending` reversal in proportion that matures together with the commission. Partial credit notes add up; the one that
  takes the rest cancels the commission and its earlier reversals.
- **After the window** the commission is locked. A later credit note writes a `payable` reversal at once: a negative adjustment
  that the next payouts carry. The balance may go below zero; no payout is possible until new commissions earn it back.
- One reversal per credit note and one commission per (invoice, kind) are kept by the database
  (`partner_commissions_invoice_kind_unique`, partial: the remainder a payout splits off a commission is a `fragment` and is
  left out). When two writers race for one (two deliveries of one event, two acceptances of one order), the second gets the
  existing row back and its own work (the acceptance, the renewal) stands. A credit note reaches only a commission of an
  invoice of its own organization; one that names another organization's invoice changes nothing and is recorded as
  `partner.commission.reverse` / `denied` in the audit log (and logged).
- Payouts (by hand and `onhost:partners:auto-payouts`) take only `payable` rows; nothing pending is ever in a payout.

## The job

`onhost:partners:mature-commissions` (hourly at :20, `onOneServer`) dispatches `PartnerCommand` `commissions.mature` through the
bus as the system (permission `partner.manage`, risk at its floor: maturing moves no money; paying what matured stays the
CRITICAL four-eyes payment). The idempotency key is the minute; each row is flipped by a conditional update, so overlapping runs
mature every commission once and announce it once. A commission matures together with the pending reversals of its credit
notes (one group), and the flips and their events are one transaction. At most 1 000 groups per run (`CommissionGrace::BATCH`).
A commission whose partner has no organization stays pending and is logged (`Partner commissions not matured …`).

Manual run: `php artisan onhost:partners:mature-commissions` (safe to repeat).

## Events (routed in `NotificationRouter`, to the partner organization)

| Event | Aggregate | Payload | Publisher |
| --- | --- | --- | --- |
| `partner.commissions.matured` | partner | `count`, `amount` (Money, net of what matured in this run for the partner) — not published when the net is not positive | `CommissionGrace::mature` |
| `partner.commission.cancelled` | partner_commission (the commission) | `invoice` (number), `credit_note` (number), `amount` (taken off, positive Money), `left` (Money, zero), `payable_at` | `CommissionGrace::reverseForCreditNote` |
| `partner.commission.reduced` | partner_commission | as above; `left` is what will still mature | `CommissionGrace::reverseForCreditNote` |
| `partner.commission.adjusted` | partner_commission | as above; the commission had matured, `amount` is deducted from the next payouts (routed `warn`) | `CommissionGrace::reverseForCreditNote` |

Audit actions: `partner.commission.mature`, `partner.commission.cancel`, `partner.commission.reduce`, `partner.commission.adjust`.

The same rows are in `docs/architecture/events-catalog.md` (customer-facing events).

## Migration `0001_01_01_000950`

R7 applies to commissions created from the deploy on: no existing row changes its state. The migration adds `payable_at`,
`cancelled_at` and `fragment`, fills `payable_at` (paid + 30 days) on rows with a payment date for the record, and creates the
partial unique index.

Existing duplicates of (invoice, kind) are marked fragments only when they look like payout remainders (same partner, base,
rate and currency; positive amounts; every row but the oldest linked to a payout; together not more than base × rate plus a
haler per row). The count of marked rows is printed. Any other duplicate (a genuine double accrual) stops the migration
**before it changes anything**, naming the groups as `invoice/kind`: finance decides which row stands, then the migration is run
again.

Rollback (`migrate:rollback --step=1`): drops the index, turns `pending` and `cancelled` rows back into `payable` (a cancelled
commission and its cancelled reversal net to zero) and drops the columns.

## Known limits

- The grace period is the constant `CommissionGrace::DAYS` (30, R7), not a config key: changing it is an owner decision.
- A fragment row copies the base of the commission it came from, so the tier volume (`recomputeTier`) and the monthly table's
  base count a split commission's base twice. This predates R7 and is not changed here.
