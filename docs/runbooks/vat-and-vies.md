# VAT numbers, VIES and reverse charge

Code: `domains/Tax/{VatNumber,VatStanding,VatNumberChecks,VatHealth,TaxEngine}.php`, `domains/Tax/Commands/*`,
`domains/Tax/Jobs/CheckVatNumber.php`, `app/Console/Commands/{VatVerify,VatRecheck}.php`. Provider:
[../provider-adapters/vies.md](../provider-adapters/vies.md). Task: TASK-0031 (decisions D31.1–D31.10).

## Why this page exists

The public knowledge base ("Firemní faktury, DPH a kredit") has always promised that VAT IDs are checked automatically
against VIES and that EU companies with a valid VAT ID are billed under reverse charge. Until TASK-0031 nothing ever asked
VIES: `ViesClient` had no caller, `organizations.vat_status` never became `valid`, and every EU business customer outside
the Czech Republic who gave a VAT ID paid the VAT of their own country (OSS) with no review flag. Partner self-billing asked
for a status `payer` that nothing wrote, so every self-billing document was 0 %.

Now a number that was given is checked, the answer is kept as evidence, and reverse charge rests on that evidence only.

## The one rule for reverse charge

An invoice goes without VAT (category `AE`, the legend *Daň odvede zákazník (reverse charge, čl. 196 směrnice 2006/112/ES).*,
and under it both VAT IDs and the VIES consultation number) only when **all** of these hold:

* the customer is a business (`customer_class = b2b`) in **another** EU member state (not CZ);
* the VAT ID (else the DIČ) has the prefix of that same country (`vat_country_mismatch` otherwise);
* VIES confirmed **exactly this number** at most **30 days** (`onhost.vies.freshness_days`) before the quote (orders) or
  the issue (renewals, usage, other documents); **or** a staff override to `valid` is in force.

Everything else is charged exactly as before TASK-0031: Czech customers pay Czech VAT, consumers and unverified businesses
pay the VAT of their country (OSS). When a VAT ID was given but is not verified now, the quote, order, invoice and tax
calculation carry `vat_review: true` for finance. The flag and the evidence (`versions.vat`) are staff-facing: no customer
notification is sent for them, and the customer's own cart quote answer carries neither (the stored quote, the order and the
invoice do).

**Somebody else's number.** Anybody can type a valid VAT number of a real company. When VIES discloses the trader's name and it
is not the organization's name (compared without case, accents, punctuation, legal form and joining words), the verdict
stays `valid` and reverse charge applies, but the quote, the order, the invoice and the tax calculation carry `vat_review`
(reason `name_mismatch`), and `onhost:vat:verify` lists the organization in the group `name_mismatch` (never asked again —
VIES would say the same). Finance decides; a staff override to `invalid` is the way to stop it (it holds, see below).
Several member states (e.g. DE) do not disclose the name: nothing is compared there.

What the customer pays when **VIES is down**: the VAT of their country, with `vat_review`. The public article says so in
one sentence ("Dokud DIČ ve VIES ověřené není (třeba když VIES zrovna neodpovídá), účtujeme DPH vaší země."). There is no
credit or correction afterwards; a document once issued is never changed (see the end of this page).

**Credits follow the supply they correct.** An SLA credit note takes the tax category and rate of the invoice line of the
service whose period covers the incident: a reverse-charged month is credited without VAT even when the customer's check has
lapsed since, and a month charged with VAT is credited with that VAT. Only when no issued line covers the incident does the
credit note follow today's decision.

The standing is read by `VatStanding` only (an arch test forbids passing the stored column anywhere). The one vocabulary of
`organizations.vat_status` is `unknown | valid | invalid`. Rows from before the check (`vat_status_source` NULL) keep
today's money until an operator re-checks them: a stored `valid` still gives reverse charge but is flagged
`legacy_unverified`; a stored `payer` still counts as a VAT payer for self-billing. Nothing rewrites them by itself.

## What is checked, and when

Only a number with the prefix of an EU member state, for an organization in the EU. A Swiss, British, Norwegian (or XI)
number, and any organization outside the EU, is never asked and never called invalid. A number with an EU prefix that
cannot be one (wrong length or characters) is recorded `invalid` from its shape, without a call.

| When | How | Timeout | Who |
| --- | --- | --- | --- |
| Registration, organization update, guest checkout — the number was set or changed | `CheckVatNumber` job on the queue after commit (retries after 1 min, 10 min, 1 h); a number checked in the last hour is not asked again | 8 s | system |
| Cart quote, guest checkout, staff quote and assisted order — the number is not known now (never checked, older than 30 days, changed) | `VatNumberChecks::refreshBeforeQuote()`, before the bus, never inside a transaction; after an unanswered check the next quote waits 10 min (`retry_after_minutes`) | 5 s | system |
| Every day at 04:20, if switched on | rule `tax.vies_recheck` → `onhost:vat:recheck`: re-checks VIES-valid numbers older than 25 days so reverse charge does not lapse. **Default off.** It does not touch legacy rows | 8 s | system |
| When the operator runs it | `onhost:vat:verify` (below) | 8 s | operator |

An answer that says nothing (VIES or the member state down, busy, breaker open, our requester refused, our own quota spent)
writes nothing and publishes nothing: the organization keeps what it had.

Our own ceiling: at most `ONHOST_VIES_PER_MINUTE` (150) calls a minute, all triggers together (the provider bucket `vies`).
Beyond it a number stays unknown for now — destination VAT, asked again later — so a flood of guest checkouts cannot get our
address or requester blocked at VIES (`IP_BLOCKED` / `VAT_BLOCKED` would end reverse charge for everybody).

And per organization: at most `onhost.vies.per_organization_per_hour` (5) questions an hour across every trigger but the
operator's (the number saved, checkout, the monthly re-check, a partner application). A customer who changes the number
back and forth gets nothing queued and nothing asked once the five are used (outcome `limited`, counted as unknown): the
number stays unknown — destination VAT with a review flag — until the hour is over or the operator's command asks.

The same number queued twice (registration and an immediate organization update): the second job finds the first one's
answer (a number checked in the last hour is not asked again) and makes no call. Two jobs that run truly at the same time,
before either has recorded, can both ask — two calls for one number, bounded by the quota; the row lock and the number match
keep one consistent state. That is accepted rather than a lock around an eight-second HTTP call. A verdict (`valid`/`invalid`) is recorded through
`RecordVatCheckCommand` (system actor only; nobody can declare their own number valid), as a `vat_validations` row
(number, status, consultation number, name and address as VIES returned them, requester VAT ID, time) and on the
organization (`vat_checked_at`, `vat_checked_number`, `vat_consultation_number`, `vat_validation_id`,
`vat_status_source`). The customer class is never written by a check.

Event: `tax.vat_number.checked` (docs/architecture/events-catalog.md). An `invalid` result tells the organization's billing
contacts of a customer in another member state (portal note + mail `vat-number-invalid`: "DIČ … se nepodařilo ověřit ve
VIES. Dokud DIČ neověříme, účtujeme DPH vaší země."). A Czech organization gets only an info note in the portal, "DIČ … není
v registru plátců DPH (VIES). Pokud jste plátce DPH, zkontrolujte ho ve fakturačních údajích." — every Czech legal entity has
a DIČ, one that is not a VAT payer is rightly not in VIES, and domestic VAT is the same either way. A number that became
valid gets an in-app note; a staff override goes to the finance inbox. A number outside the EU is never reported.

## Existing customers: `onhost:vat:verify`

A **dry run by default**: no HTTP call, no write.

```
php artisan onhost:vat:verify                    # list only
php artisan onhost:vat:verify --csv=vat-2026.csv # also write the document list to storage/app/private/reports/vat-2026.csv
php artisan onhost:vat:verify --apply            # ask VIES and record the answers (needs ONHOST_VIES_ENABLED=true)
```

Options: `--limit=200`, `--organization=<id>` (repeatable), `--country=DE` (repeatable), `--pause-ms=500` between two calls,
`--since=Y-m-d` for the documents.

1. **Organizations** a check would decide: EU business numbers never checked, VIES answers older than 30 days, and legacy
   rows (`valid`/`payer` without a source), each with how they are charged today and what `--apply` would do; and, for
   finance only, valid numbers VIES registers to another name (`name_mismatch`, not asked again). Group `partner`: a partner
   (not closed) whose well-formed number — **a Czech DIČ included** — no check has spoken about; the quote never asks about
   a Czech DIČ, so without `--apply` an existing Czech VAT-payer partner keeps self-billing documents without VAT. An
   organization under a staff override is never listed and never asked (the override holds until it ends).
2. **Documents for the accountant**: invoices already issued **with VAT** to EU business customers of another member state
   who had given a VAT ID (the same predicate as the `vat_review` flag on new documents). They are listed, never changed.
   In the CSV a cell the customer typed that starts with `=`, `+`, `-` or `@` is written with a leading apostrophe, so a
   spreadsheet shows it as text instead of running it.

Go-live order: switch VIES on, run the dry run, give the CSV to the accountant, and only then `--apply`.

## Staff override

VIES has been down for days, or the customer proves the registration another way (a certificate from their tax office).

`POST /v1/staff/customers/{organization}/vat-status` with `status` (`valid` | `invalid`), `reason` (10–1000 characters),
`evidence` (5–1000 characters: what you relied on) and `days` (1–30, default 30). Answer `202`.

* Command `tax.vat_status.override`, permission `billing.tax_rule.manage`, risk **CRITICAL**: a fresh step-up and a second
  person ([approvals.md](approvals.md)). The first attempt returns `403 approval_required` and opens the request; the same
  call after the approval goes through. With `ONHOST_FOUR_EYES=false` (single operator) the step-up alone suffices.
* Only a number of an EU member state can be overridden.
* It writes a `vat_validations` row (source `staff`, reason, evidence, actor, `expires_at`), an audit record
  `tax.vat_status.override`, and the event with `source: staff`. It never writes the customer class or an issued document.
* It **holds until it ends**: a customer re-saving the same number, a checkout and the monthly re-check ask VIES nothing and
  record nothing over it (`VatNumberChecks` skips, `RecordVatCheckHandler` refuses `staff_override`); only an explicit
  operator check replaces it, and its event then says `previous_source: staff`.
* It **belongs to the number**, not to the organization row. A new number, or a removed one, resets the row, but the
  override stays with its number: a customer who changes the number and changes it back finds the override in force again
  (the newest `vat_validations` row for that number is the unexpired staff row), and nothing asks VIES about it. Only the
  operator's check records a newer verdict for the number.
* It counts for **one number of one country**: an override to `valid` for a DE number does not reverse-charge once the
  organization's country is AT (`vat_country_mismatch`), the same rule as a VIES answer.
* It **ends by itself** after `days`. The standing then reads `unknown` (`override_expired`) until VIES answers or staff
  confirm again with a new override. The invoice under an override prints "registrace k DPH doložena mimo VIES" instead of
  the VIES line.

The staff customer detail shows the `vat` block (number, effective status and why, source, checked at, consultation number,
override end, whether a check is needed).

## Partner self-billing VAT

The self-billing document (the partner's commission invoice issued by us) uses the same acceptance rules as the customer's
tax decision (D31.6; one helper, `VatStanding::verdict()`, since review round 3): the number must be the current one, of the
partner's own country, and — here unlike a customer — VIES must not register it to another trader name. The partner controls
its own name, country and number, and the VAT on its document is cash we pay out and deduct as input VAT, so a number of
another country or of another trader proves nothing. A genuine name difference is accepted only by a staff override to valid
(CRITICAL, four eyes). Registration does not lapse in a month: no 30-day window for the payer.

| Partner | Rate | Category | Note on the document |
| --- | --- | --- | --- |
| VAT payer in CZ (a CZ DIČ VIES-valid under the partner's name, a staff override to valid, or a legacy `payer`) | `standard_rates.CZ` of the active tax rules (21 %) | `S` | Dodavatel je plátcem DPH. |
| VAT payer in another EU state (a valid number of its own country) | 0 % | `AE` | Daň odvede odběratel (reverse charge, čl. 196 směrnice 2006/112/ES). |
| a valid number of another country than the partner's, or one VIES registers to another trader | 0 % | `E` | Registrace dodavatele k DPH neověřena. (snapshot `vat_review: true`, `vat_review_reason`: `vat_country_mismatch` / `name_mismatch`) |
| a well-formed number nothing has proved either way (never checked, a legacy row, an ended override) | 0 % | `E` | Registrace dodavatele k DPH neověřena. (snapshot `vat_review: true`, `vat_review_reason: unknown`) |
| anybody else (no number, or a check of the number or staff said it is not a payer) | 0 % | `E` | Dodavatel není plátcem DPH. |

The partner sees its documents without `vat_review` / `vat_review_reason` and with the VIES evidence reduced to what the
document prints (checked at, consultation number, VIES or staff); finance sees everything in the staff payout list.

A partner's number is checked on the queue when the organization applies and when it is approved (VIES on). Partners that
already exist are reached by `onhost:vat:verify --apply` only (group `partner`).

A missing standard rate is refused with `409 tax_rate_missing` **before** a payout or a document is written; the rate is
never guessed.

What is paid is the document's **total**: for a VAT-payer partner net + VAT (the payout's `transfer`, shown next to `amount`,
the commission). Marking it paid posts: commission expense (net, debit), `liability:vat` (the VAT, debit — input VAT from the
self-billed document), bank or offset (the total, credit). Reverse charge and non-payers are paid the net as before. Payouts
requested before this change carry their own snapshot and are paid what it says. Payout documents already issued are not changed. For self-billing, registration does not lapse: no 30-day
freshness applies.

Edge case for the accountant: an *identifikovaná osoba* (registered for VAT only for cross-border services) has a DIČ that
VIES confirms, but it is not a VAT payer for domestic supplies. The code treats any VIES-valid CZ partner as a payer.

## Doctor rows (area `tax`, never blocking, no HTTP call)

| Row | Red means |
| --- | --- |
| VIES checks are on and the requester VAT ID is set | `ONHOST_VIES_ENABLED=false`, or `ONHOST_VIES_REQUESTER_VAT_ID` empty or not a Czech VAT ID (VIES then answers without a consultation number) |
| no EU business customer with a VAT ID waits for a VIES answer | N organizations charged destination VAT with a review flag, M legacy rows, P partners whose number was never checked (self-billing without VAT); `onhost:vat:verify` lists them |
| VIES answered recently | no VIES answer in 7 days while customers wait (VIES down, our requester or IP refused, the breaker open) |
| no reverse charge lapses while tax.vies_recheck is off | VIES is on and the rule is off (red before anybody lapses: a customer verified at the order pays destination VAT from its first renewal more than 30 days later), or VIES-valid organizations checked more than 25 days ago; switch the rule on |

## Switching it on (go-live)

1. `.env`: `ONHOST_VIES_ENABLED=true`, `ONHOST_VIES_REQUESTER_VAT_ID=CZ<our DIČ>`. Optional: `ONHOST_VIES_TIMEOUT` (8),
   `ONHOST_VIES_CHECKOUT_TIMEOUT` (5), `ONHOST_VIES_PER_MINUTE` (150), `VIES_ENDPOINT`. The binding is resolved per call from the configuration; with a cached
   configuration run `php artisan config:cache` again, and restart the queue workers so they read it.
2. Switch on `tax.vies_recheck` (staff console → Automatizace, fresh step-up) **together with** the switch — required, not a
   choice: renewals, usage and domain renewals judge the 30 days at issue and never ask VIES themselves, so without the rule
   every customer verified at the order is charged destination VAT (from the wallet) at the first renewal more than 30 days
   later. `php artisan onhost:doctor`: area `tax`, the first and the last row green.
3. A test quote for a DE business customer with a verified VAT ID shows reverse charge (`AE`, 0 %).
4. `php artisan onhost:vat:verify --csv=…` (dry run); the accountant reviews the organizations and the documents.
5. `php artisan onhost:vat:verify --apply`; then the dry run again: the `name_mismatch` group goes to finance.

To switch it off again: `ONHOST_VIES_ENABLED=false`. Nothing is asked any more; recorded answers stay and keep counting until
their 30 days run out, after which those customers pay destination VAT with the review flag.

## What is never changed

Issued invoices, credit notes and self-billing documents are append-only. A document issued with VAT to a customer whose
number turns out valid stays as it is; a correction is the accountant's decision and a new document. The `vat_validations`
rows are evidence and are kept (also on erasure; ComplianceService is unchanged, pending legal sign-off).
