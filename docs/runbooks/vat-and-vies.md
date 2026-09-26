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
calculation carry `vat_review: true` for finance. No customer notification is sent for the flag (the customer's own quote
response does carry it in `versions`).

What the customer pays when **VIES is down**: the VAT of their country, with `vat_review`. The public article says so in
one sentence ("Dokud DIČ ve VIES ověřené není (třeba když VIES zrovna neodpovídá), účtujeme DPH vaší země."). There is no
credit or correction afterwards; a document once issued is never changed (see the end of this page).

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

An answer that says nothing (VIES or the member state down, busy, breaker open, our requester refused) writes nothing and
publishes nothing: the organization keeps what it had. A verdict (`valid`/`invalid`) is recorded through
`RecordVatCheckCommand` (system actor only; nobody can declare their own number valid), as a `vat_validations` row
(number, status, consultation number, name and address as VIES returned them, requester VAT ID, time) and on the
organization (`vat_checked_at`, `vat_checked_number`, `vat_consultation_number`, `vat_validation_id`,
`vat_status_source`). The customer class is never written by a check.

Event: `tax.vat_number.checked` (docs/architecture/events-catalog.md). An `invalid` result tells the organization's billing
contacts (portal note + mail `vat-number-invalid`: "DIČ … se nepodařilo ověřit ve VIES", then the effect — "Dokud DIČ
neověříme, účtujeme DPH vaší země." for a customer of another state, the neutral "Pokud je číslo správné, napište nám a
ověříme ho ručně." for a Czech organization); a number that became valid gets an in-app note; a staff override goes to the
finance inbox. A number outside the EU is never reported.

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
   rows (`valid`/`payer` without a source), each with how they are charged today and what `--apply` would do.
2. **Documents for the accountant**: invoices already issued **with VAT** to EU business customers of another member state
   who had given a VAT ID (the same predicate as the `vat_review` flag on new documents). They are listed, never changed.

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
* It **ends by itself** after `days`. The standing then reads `unknown` (`override_expired`) until VIES answers or staff
  confirm again with a new override. The invoice under an override prints "registrace k DPH doložena mimo VIES" instead of
  the VIES line.

The staff customer detail shows the `vat` block (number, effective status and why, source, checked at, consultation number,
override end, whether a check is needed).

## Partner self-billing VAT

The self-billing document (the partner's commission invoice issued by us) uses the same standing (D31.6):

| Partner | Rate | Category | Note on the document |
| --- | --- | --- | --- |
| VAT payer in CZ (the DIČ is VIES-valid, a staff override to valid, or a legacy `payer`) | `standard_rates.CZ` of the active tax rules (21 %) | `S` | Dodavatel je plátcem DPH. |
| VAT payer in another EU state | 0 % | `AE` | Daň odvede odběratel (reverse charge, čl. 196 směrnice 2006/112/ES). |
| anybody else | 0 % | `E` | Dodavatel není plátcem DPH. |

A missing standard rate is refused with `409 tax_rate_missing` **before** a payout or a document is written; the rate is
never guessed. Payout documents already issued are not changed. For self-billing, registration does not lapse: no 30-day
freshness applies.

Edge case for the accountant: an *identifikovaná osoba* (registered for VAT only for cross-border services) has a DIČ that
VIES confirms, but it is not a VAT payer for domestic supplies. The code treats any VIES-valid CZ partner as a payer.

## Doctor rows (area `tax`, never blocking, no HTTP call)

| Row | Red means |
| --- | --- |
| VIES checks are on and the requester VAT ID is set | `ONHOST_VIES_ENABLED=false`, or `ONHOST_VIES_REQUESTER_VAT_ID` empty or not a Czech VAT ID (VIES then answers without a consultation number) |
| no EU business customer with a VAT ID waits for a VIES answer | N organizations charged destination VAT with a review flag, M legacy rows; `onhost:vat:verify` lists them |
| VIES answered recently | no VIES answer in 7 days while customers wait (VIES down, our requester or IP refused, the breaker open) |
| no reverse charge lapses while tax.vies_recheck is off | VIES-valid organizations checked more than 25 days ago; switch the rule on or run `--apply` |

## Switching it on (go-live)

1. `.env`: `ONHOST_VIES_ENABLED=true`, `ONHOST_VIES_REQUESTER_VAT_ID=CZ<our DIČ>`. Optional: `ONHOST_VIES_TIMEOUT` (8),
   `ONHOST_VIES_CHECKOUT_TIMEOUT` (5), `VIES_ENDPOINT`. The binding is resolved per call from the configuration; with a cached
   configuration run `php artisan config:cache` again, and restart the queue workers so they read it.
2. `php artisan onhost:doctor`: area `tax`, the first row green.
3. A test quote for a DE business customer with a verified VAT ID shows reverse charge (`AE`, 0 %).
4. `php artisan onhost:vat:verify --csv=…` (dry run); the accountant reviews the organizations and the documents.
5. `php artisan onhost:vat:verify --apply`; decide whether to switch on `tax.vies_recheck` (staff console → Automatizace,
   fresh step-up).

To switch it off again: `ONHOST_VIES_ENABLED=false`. Nothing is asked any more; recorded answers stay and keep counting until
their 30 days run out, after which those customers pay destination VAT with the review flag.

## What is never changed

Issued invoices, credit notes and self-billing documents are append-only. A document issued with VAT to a customer whose
number turns out valid stays as it is; a correction is the accountant's decision and a new document. The `vat_validations`
rows are evidence and are kept (also on erasure; ComplianceService is unchanged, pending legal sign-off).
