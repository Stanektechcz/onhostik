<?php

declare(strict_types=1);

/*
 * The seller's VAT mode (G2, owner decision G-R1). It is configuration, written to the legal entity — never code:
 *
 *  · `payer` — whether the operator's company is registered for VAT in the Czech Republic (ONHOST_VAT_PAYER, default true).
 *    The legal entity carries the mode documents are issued in (`legal_entities.vat_payer`); `php artisan onhost:vat:payer-mode
 *    --apply` (or LegalEntitySeeder / onhost:production:prepare --legal) writes this value to it. `onhost:doctor` shows the mode
 *    in force and says what to do when the two disagree. A document keeps the seller it was issued by: switching never changes
 *    one that was already issued (docs/runbooks/vat-payer-mode.md).
 *  · `filed_through` — see below;
 *  · `kh_threshold_czk` — a domestic tax document to a VAT payer above this amount incl. VAT is listed one by one in section A.4
 *    of the control statement (§ 101d); the rest is summed in A.5. Statutory: 10 000 Kč.
 */
return [
    'payer' => (bool) env('ONHOST_VAT_PAYER', true),
    'kh_threshold_czk' => (int) env('ONHOST_VAT_KH_THRESHOLD_CZK', 10000),
    // the last VAT period finance filed (YYYY-MM, empty = none): a tax document whose DUZP falls into it or before is flagged
    // (`meta.filed_period`, audit `invoice.duzp_in_filed_period`) and the report of that period warns — a correction is due
    'filed_through' => (string) env('ONHOST_VAT_FILED_THROUGH', ''),
];
