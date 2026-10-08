# VAT payer mode, advances and the KH/SH drafts (G2)

Owner decision G-R1 (2026-10-05): only the invoice is a tax document, and the platform must run both as a VAT payer and as a
seller who is not one, with proforma (advance) invoices. This runbook is for the operator and finance; the accountant's open
questions are at the end.

## Owner decision H-R0 (2026-10-06): not a VAT payer now

ONhost is **not** a VAT payer today and becomes one later. Therefore:

* the declaration defaults to `ONHOST_VAT_PAYER=false` (`config/vat.php`, `.env.example`); a legal entity created by
  `LegalEntitySeeder` / `onhost:production:prepare --legal` is a non-payer (before H0 the seeder never wrote the declaration — the
  re-fetched row was never "recently created" — so every new entity kept the column default *payer*);
* a platform without its legal entity follows the declaration (it used to count as a payer whatever was declared);
* an **existing** legal entity is never switched by a deploy or a seeder run: if it still says *payer*, `onhost:doctor` (row
  *VAT payer mode*) and `php artisan onhost:vat:payer-mode` (exit 1) show the disagreement, and finance switches it with the path
  below (step-up, second person) **before the first document is issued**;
* **becoming a payer later** is the same path the other way: `ONHOST_VAT_PAYER=true` + `config:cache`, then
  `POST /v1/staff/tax/vat-payer-mode {payer: true, reason}` with a step-up and a second person; documents issued before keep
  their seller (no VAT on them).

Non-payer documents show no VAT (no DUZP, no VAT columns, "Neplátce DPH", no CZK recap): `G2VatPayerTest` ("keeps a
non-payer's invoice …", "gives a non-payer's advance a payment confirmation …", "… a non-payer's EUR invoice has no CZK VAT
recap") and `tests/Feature/OwnerDecisionsH0/VatNonPayerDefaultTest.php`. The test suite itself runs as a payer (`phpunit.xml`
sets `ONHOST_VAT_PAYER=true`), because most of its VAT tests describe the payer.

## The mode

| Where | What |
| --- | --- |
| `ONHOST_VAT_PAYER` → `config/vat.php` `payer` (default **`false`** since owner decision H-R0, 2026-10-06: ONhost is not a VAT payer now) | the mode the operator declares |
| `legal_entities.vat_payer` (+ `meta.vat_payer_history`) | the mode documents are issued in |
| active tax rules `supplier.vat_payer` | can only narrow it (a rule set that says `false` makes the seller a non-payer) |
| `Onhost\Domain\Tax\VatPayerMode` | the one answer: payer = legal entity **and** tax rules |

**Switching** is configuration plus the legal entity, never code — and it is a person's CRITICAL action (security review of
#106: the first version switched from the command line as the system, with no step-up and no second person):

1. declare the mode: `.env` `ONHOST_VAT_PAYER=false` (or `true`), `php artisan config:cache`; `php artisan onhost:vat:payer-mode`
   shows the mode and exits 1 while the declaration and the legal entity disagree (it never switches: `--apply` is refused);
2. a member of finance (`billing.tax_rule.manage`) with a fresh step-up sends `POST /v1/staff/tax/vat-payer-mode`
   `{payer: false, reason: "Registrace k DPH zrušena od 1. 11. 2026"}` (`GET` shows the report). The answer is 403
   `approval_required` with the `approval_id` of the request;
3. somebody else approves it (`POST /v1/staff/approvals/{id}/decision`, Nastavení → Schvalování);
4. the same request is repeated with `approval_ids: [id]`: `SetVatPayerModeCommand` runs once (audit `tax.vat_payer_mode.set`,
   the legal entity keeps the history: from when, which mode, who, the approval, why). The approval binds this very body; the
   idempotency key is the approval being consumed. With `ONHOST_FOUR_EYES=false` the sole approver's own switch waits the time lock
   (docs/runbooks/approvals.md).

`LegalEntitySeeder` (and `onhost:production:prepare --legal`) writes the declared mode only into a legal entity it creates, never
over an existing one.

`onhost:doctor`, area `documents`, row **VAT payer mode**: the mode in force, the legal entity's, the declaration and the tax
rules'; not OK (blocking in production) while they disagree, with the remedy (the staff switch above, or a tax rule version that
agrees).

**What the mode changes, from the moment it is applied:**

| | VAT payer | not a VAT payer |
| --- | --- | --- |
| tax engine (quotes, renewals, orders) | rates of the tax rules, reverse charge, OSS | 0 %, category `E`, "Supplier is not a VAT payer" — no VAT is charged |
| invoice | *Faktura – daňový doklad*, all § 29 particulars | *Faktura*, "Neplátce DPH", "Tento doklad není daňovým dokladem", no DUZP, no VAT columns |
| payment of an order / proforma / top-up | tax document for the received payment (`receipt`) per G1 | payment confirmation (`confirmation`) |
| EUR document | VAT in CZK at the ČNB rate of the DUZP | **no CZK recap** (`CzkTaxStatement::concerns` is false — it carried a "VAT in CZK" of zero before G2) |
| KH / SH drafts | the document is in them | the document is in none |

**Switching never changes a document that was issued.** Every document froze its seller (`invoices.seller`, `vat_payer`
included) when it was issued; the PDF, the HTML, the UBL, the CZK recap (also when `onhost:fx:sync` completes it later) and the
reports read the frozen seller, never today's mode (`G2VatPayerTest`: "never changes a document issued before the mode was
switched"). Public prices on the web (`SurfacePricing::vat()`) still show the tax rules' standard rate: publish a tax rule version
with `supplier.vat_payer: false` when the seller stops being a payer, so the surfaces and the engine agree.

## What a VAT payer's tax document says (§ 29)

One template for every document (`resources/views/invoices/invoice.blade.php`, `InvoicePdfRenderer::html()` / `render()`),
Czech and English side by side: seller (name, address, IČO, DIČ, registry entry), buyer (name, address, IČO, DIČ), number, date
of issue, **DUZP** (only on tax documents), description, quantity and unit, unit price without VAT, base, rate, VAT, total per
line, the **VAT summary per rate** (base, rate, VAT, total), the total; the CZK recap at the ČNB rate of the DUZP for another
currency (§ 29 (1) l), the reverse-charge note with both VAT IDs and the VIES check (category `AE`), "outside the scope of Czech
VAT" (category `O`).

**Rounding (§ 37) — the document's choice**, `Onhost\Domain\Tax\VatRounding`, recorded as `meta.rounding` on every tax document:

* VAT is computed from the base (§ 37 (1)) line by line, rounded to the haléř **half away from zero**; the summary per rate is the
  sum of its lines, so lines, summary and total always add up;
* VAT extracted from a received amount (a top-up's tax receipt, § 37 (2)) is amount × rate / (100 + rate), rounded the same way.
  Before G2 the base was truncated, which gave one haléř of VAT too much whenever its fraction was above one half (1 000,06 Kč
  at 21 %: 173,57 Kč instead of 173,56 Kč);
* the total is never rounded to whole crowns: every payment is cashless.

**DUZP:** an invoice — the day it is issued (a prepaid supply starts that day; a renewal invoice is issued on its accounting
day); a tax document for a received payment — **the day the money was received**: the bank's booking day of the matched
transfer (`bank_statement_lines.booked_at`), else the day the payment was settled; a credit note — the day it is issued (its CZK
rate is the corrected document's, § 42).

## Proforma → payment → tax document → final invoice

| Step | VAT payer | not a VAT payer |
| --- | --- | --- |
| order paid by bank transfer | proforma `PF-…` — *Zálohová faktura (není daňový doklad)*, no DUZP, no CZK recap, UBL type 386 | the same, *Zálohová faktura* |
| the transfer arrives (statement line matched) | `receipt` `PP-…` — *Daňový doklad k přijaté platbě*: one line per VAT rate with the **order's** base and VAT (not one rate on the gross), `meta.advance_for` = order and proforma, DUZP = day received | `confirmation` — *Potvrzení o přijetí platby*, no VAT |
| the order is paid | **final invoice** `FV-…` (`type invoice`, `meta.postpaid: false`, `meta.advances`): the supply in full, then *Zúčtování zálohy* — the advance's tax document by number and DUZP, the base and VAT deducted per rate, the difference (0), *Zbývá uhradit 0* | credit statement `VY-…` as before (no tax document to deduct) |
| the advance is corrected (refund, cancelled before delivery) | credit note `DK-…` of the receipt (`POST /v1/invoices/{id}/credit-note`) — it corrects the advance tax document, at its rate and VAT | — |

A card order (no proforma) keeps G1: its receipt is the sale's tax document and the order gets the credit statement. The money
path of the final invoice is the statement's (paid from the credit hold, nothing booked at issue), so settlement, cancellation,
chargeback and credit notes of undelivered lines work on it unchanged (they look for `statement` or `invoice` of the order).
The UBL of the final invoice carries `PrepaidAmount` = total and `PayableAmount` 0.

## KH and SH drafts

```
php artisan onhost:vat:export kh --period=2026-10 --format=xml > kh-2026-10.xml
php artisan onhost:vat:export kh --period=2026-10 --format=csv
php artisan onhost:vat:export sh --period=2026-Q4 --format=xml
```

Read only (`Onhost\Domain\Tax\VatReports`): nothing is written or submitted; the XML is a **draft** in the EPO structure (DPHKH1
03.01 / DPHSHV 02.01, a comment says so) that the accountant completes (tax office, filing details), checks in EPO and files.

* documents: the tax documents (`invoice`, `receipt`, `credit_note`) of a seller who was a VAT payer when it issued them,
  assigned to the period by **DUZP**; amounts in CZK (another currency by its CZK recap; a document still waiting for its rate
  is reported as a warning on stderr and left out);
* **KH A.4**: domestic supply (category `S`, buyer in CZ) to a buyer with a Czech VAT ID that VIES confirmed (the frozen
  `buyer.vat_status = valid`), document total above `ONHOST_VAT_KH_THRESHOLD_CZK` (10 000 Kč) incl. VAT — one row per document
  and rate (DIČ, number, DUZP, base and tax in the column of the rate: 21 % → 1, 12 % → 2); a credit note goes where the document
  it corrects went; **A.5**: the rest, summed per rate; **C**: `obrat23` / `obrat5` from A.4 + A.5 only. A final invoice reports
  only what is left after the advance it deducts (the advance's tax document reported the rest). OSS supplies and supplies outside
  the EU are not in the KH;
* **SH**: reverse-charged services (category `AE`) per buyer: country, VAT number without its prefix, code 3, number of supplies
  (a credit note adds none), value in whole CZK (credit notes reduce it).

Tests: `tests/Feature/Tax/G2VatPayerTest.php`.

## Advances that cannot be deducted twice, and periods already filed

* **One advance per order** (per proforma without an order): the first payment of an order gets the tax document for the
  received payment with `meta.advance_for`; a second payment of the same order (the proforma paid by card and the transfer, a
  payment made twice) gets a plain receipt with `meta.prepayment_for` — a prepayment that stays credit — decided under the
  order's lock. The final invoice deducts the advances in the order they arrived, each whole or not at all, never more than it
  states in total or at any rate; an advance a credit note touched is not deducted. The PDF, the UBL (`PrepaidAmount` ≤ total,
  `PayableAmount` ≥ 0) and the KH (a row below zero is counted as 0 with a warning) never go below zero, also for a document
  whose advances were written by hand.
* **One document per payment**: the payment intent is locked while its document is decided (two callbacks wait for each other).
* **One final document per order**: decided under the order's lock; a retry returns the document already made.
* **CZK of the advance** in the KH is read from the receipt at report time; a final invoice whose advance still waits for its
  rate is left out with a warning until `onhost:fx:sync` completes the receipt.
* **Filed periods**: `ONHOST_VAT_FILED_THROUGH=YYYY-MM` (the last period finance filed). A tax document whose DUZP falls into it
  or before (a transfer booked on 31 October and matched on 1 November) gets `meta.filed_period` and the audit row
  `invoice.duzp_in_filed_period`; the KH/SH of a filed period warns that it is a correction draft and lists those documents.

## For the accountant (open questions)

**Owner decisions I-R5 and I-R8 (2026-10-08):** ONhost stays a non-payer for **at least a year** and launches with the non-payer
procedure as it is. These questions — and those of the owner's decision brief (loyalty discount, a refund's credit note beside the
payment confirmation, the multi-purpose voucher, VAT on forfeited credit) — go to the accountant together with the switch to a payer
(the day of the VAT registration, announced ahead), not before the launch.

1. **Rounding**: VAT per line, half away from zero, to the haléř; summary = sum of lines (not recomputed from the summed base).
   Confirm, or the summary must be computed from the base per rate and the difference carried by a line.
2. **DUZP of a received payment** = the bank's booking day; an import that arrives in the next month puts the receipt into the
   month of the booking day — confirm this is how the payments should be reported.
3. **Advance with reverse charge** (an EU business paying a proforma): the platform issues the receipt with category `AE` and no
   VAT. Confirm whether a tax document for such an advance is wanted at all (§ 28 / § 24a).
4. **Final invoice** deducts the advance as a document block (supply in full, advance deducted per rate, difference), not as
   negative lines; the UBL carries the advance as `PrepaidAmount`. Confirm the form, and whether a card order (no proforma)
   should also end with a final invoice instead of the G1 credit statement.
5. **KH/SH drafts**: attribute names of the EPO structures, section C beyond `obrat23`/`obrat5`, the KH rate columns, and the
   quarterly SH for services only — to be checked in EPO before the first filing.
6. **Multi-purpose voucher** question of G1 (credit as a voucher, § 10a–10c) is still open and decides whether top-ups need a
   tax receipt at all.
