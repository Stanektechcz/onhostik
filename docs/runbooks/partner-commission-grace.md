# Partner commission grace period (owner decision R7)

> R7 (`docs/audit/2026-10-full-readiness/ROZHODNUTI.md`): *Partnerská provize se stává splatnou až 30 dní po zaplacení
> faktury. Přibude unikátní index `(invoice_id, kind)`.* Implemented by TASK-0097.

## What happens to a commission

| State | Meaning | How it gets there |
| --- | --- | --- |
| `pending` | earned, waiting out 30 days from the client's payment (`payable_at`) | `invoice.paid` → `PartnerService::accrueForInvoice`; the marketplace share on acceptance and on every monthly renewal (`MarketplaceService`) — all through `CommissionGrace::book` |
| `payable` | may be paid out | the hourly maturing job, once `payable_at` passed |
| `allocated` / `paid` | in a payout / paid (unchanged, `PartnerPayouts`) | payout request / four-eyes payment |
| `cancelled` | given back in full inside the window; never payable | a credit note while the commission is `pending`; finance's cancellation of a set-aside double (below) |
| `duplicate` | the newer row of a genuine double commission, set aside before 000950; never payable | migration `000949` (I-R6, K5) |

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

| `partner.commission.duplicate_cancelled` | partner_commission (the cancelled newer row) | `invoice` (number of the invoice it was accrued for), `amount` (positive Money), `outcome` (`cancelled` · `deducted` · `credited`) — `deducted` routed `warn` | `DuplicateCommissions::cancel` (K5) |

Audit actions: `partner.commission.mature`, `partner.commission.cancel`, `partner.commission.reduce`, `partner.commission.adjust`,
`partner.commission.cancel_duplicate` (the bus's own record of the command: finance's reason, the step-up, the approval).

The same rows are in `docs/architecture/events-catalog.md` (customer-facing events).

## Migration `0001_01_01_000950`

R7 applies to commissions created from the deploy on: no existing row changes its state. The migration adds `payable_at`,
`cancelled_at` and `fragment`, fills `payable_at` (paid + 30 days) on rows with a payment date for the record, and creates the
partial unique index.

Existing duplicates of (invoice, kind) are marked fragments only when they look like payout remainders (same partner, base,
rate and currency; positive amounts; every row but the oldest linked to a payout; together not more than base × rate plus a
haler per row). The count of marked rows is printed. Any other duplicate (a genuine double accrual) stops the migration
**before it changes anything**, naming the groups as `invoice/kind`: finance decides which row stands, then the migration is run
again. Since K5 that can no longer happen on a deploy: `000949` runs first and has set every such newer row aside (below); the
refusal stays in `000950` as written.

**Owner decision I-R6 (2026-10-08), the rule in advance:** for every group the migration names, finance cancels the **newer** row
by an ordinary correction (a new record; never an amount edited by hand), then the deploy is run again — no meeting in between.

### A genuine double: set aside by `000949`, cancelled by finance (K5, TASK-0158; owner decisions I-R6, J-R3)

`000950` is never edited (J-R3: it already ran on staging copies and in CI). A new migration, `0001_01_01_000949`, sorts before it
and runs first on a database that has not run `000950` yet:

1. **Deploy.** For every group of (invoice, kind) that `000950` would refuse, `000949` applies the owner's rule decided in advance
   (I-R6: the **newer** row goes) as far as a migration may — it **sets the newer row aside**: the row's invoice link moves to
   `duplicate_invoice_id` (it no longer claims the invoice, so `000950` builds its index), `duplicate_of_id` names the commission that
   stands, and a row nobody was paid for (`payable`) becomes `duplicate`, which no payout takes. Amounts, partner and dates are not
   touched. The deploy prints `partner_commissions: N newer row(s) of a double commission set aside (I-R6) … <ids>` (also in the log).
   Groups that look like payout remainders stay `000950`'s (fragments, as before).
2. **The migration stops instead** (before it changes anything) when a newer row sits in an open payout (`allocated`): a rejected
   payout would turn a held row back into `payable`. The message names `invoice/kind (payout …)`. The deployer stops with rc 4 (site in
   maintenance, recovery command printed); finance finishes or rejects that payout on the running (last good) release, then the deploy
   runs again. Nothing else waits on engineering.
3. **Finance cancels each set-aside row** on the released code: `POST /v1/staff/partners/commissions/{id}/cancel-duplicate` with
   `reason` (10–500 characters), as `partner.manage` (BillingFinanceAdmin). The command `partner.commission.cancel_duplicate` is **HIGH**:
   a fresh step-up, and the first attempt opens a request a **second person** approves (the staff approvals queue, `GET /v1/staff/approvals`); the repeat with
   `approval_ids` runs it. With `ONHOST_FOUR_EYES=false` the sole approver waits the time lock. The staff partner detail
   (`GET /v1/staff/partners/{partner}`, `duplicates`) lists the rows still waiting.
   - a `duplicate` row (nobody was paid) becomes `cancelled`, and a new `reversal` row (`cancels_id` → the row, `invoice_id` empty,
     the amount negated, also `cancelled`) nets it to zero — nothing is ever paid;
   - a row already paid out stays `paid` (the money left) and the reversal is `payable` at once: a minus on the partner's next payouts
     (`deducted`; for a doubled reversal a plus, `credited`), as a credit note after the grace window;
   - a second cancellation of the same row is refused (`409 commission_already_cancelled`; the partial unique index on `cancels_id`
     stops two at once); a row that is not a set-aside duplicate is refused (`422 commission_not_duplicate`) — any other correction
     is a credit note on the client invoice. The partner is told (`partner.commission.duplicate_cancelled`).

Rollback of `000949` (`migrate:rollback` past it): every set-aside row gets its invoice back, `duplicate` turns back into `payable`, the
columns go; a cancellation's reversal row stays (a record of its own). On a database that already ran `000950` the unique index keeps
(invoice, kind) single, so `000949` only adds its columns.

Rollback (`migrate:rollback --step=1`): drops the index, turns `pending` and `cancelled` rows back into `payable` (a cancelled
commission and its cancelled reversal net to zero) and drops the columns.

## Known limits

- The grace period is the constant `CommissionGrace::DAYS` (30, R7), not a config key: changing it is an owner decision.
- A fragment row copies the base of the commission it came from, so the tier volume (`recomputeTier`) and the monthly table's
  base count a split commission's base twice. This predates R7 and is not changed here.
- A group that mixes a payout remainder and a genuine double does not look like remainders, so `000949` sets every newer row of it
  aside (as `000950` judges groups as a whole); finance compares the amounts with the payout before cancelling one (K5).
