<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax;

use Illuminate\Support\Carbon;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Platform\Errors\DomainError;

/**
 * Drafts of the control statement (kontrolní hlášení, KH, § 101c) and the EC sales list (souhrnné hlášení, SH, § 102) of a
 * period, from the tax documents the platform issued — for the accountant, who checks and files them (G2). Read only: nothing is
 * written, nothing is submitted.
 *
 *  · which documents: the tax documents (CzkTaxStatement::TYPES) of a seller who was a VAT payer when it issued them (the frozen
 *    seller), assigned to the period by their DUZP; a non-payer's document is in no report;
 *  · amounts in CZK: a CZK document as it is, another currency by its CZK recap (`meta.czk`); a document still waiting for its
 *    rate is listed in `warnings` and left out;
 *  · KH A.4: a domestic supply (category S, buyer in CZ) to a buyer with a Czech VAT number registered for VAT, of more than
 *    `vat.kh_threshold_czk` incl. VAT, one row per document and rate; a credit note goes where the document it corrects went;
 *    KH A.5: every other domestic supply, summed per rate. A final invoice reports what is left after the advance it deducts (the
 *    advance's tax document reported the rest). OSS supplies (VAT of another member state) and supplies outside the EU are in
 *    neither section;
 *  · SH: reverse-charged services (category AE) to a business of another member state, per buyer: country, VAT number, code 3
 *    (services, § 102 (1) d), the number of supplies and their value in whole CZK (credit notes reduce it).
 */
final class VatReports
{
    /** The KH rate columns: základní (1), první snížená (2), druhá snížená (3). */
    private const KH_RATES = ['21' => 1, '12' => 2, '10' => 3];

    /** @return array{from:string, to:string, year:int, month:?int, quarter:?int} */
    public static function period(string $period): array
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $m) === 1) {
            $from = Carbon::create((int) $m[1], (int) $m[2], 1);

            return ['from' => $from->format('Y-m-d'), 'to' => $from->copy()->endOfMonth()->format('Y-m-d'), 'year' => (int) $m[1], 'month' => (int) $m[2], 'quarter' => null];
        }
        if (preg_match('/^(\d{4})-Q([1-4])$/i', $period, $m) === 1) {
            $from = Carbon::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1);

            return ['from' => $from->format('Y-m-d'), 'to' => $from->copy()->addMonths(2)->endOfMonth()->format('Y-m-d'), 'year' => (int) $m[1], 'month' => null, 'quarter' => (int) $m[2]];
        }
        throw new DomainError('vat_period_invalid', 'The period is a month (YYYY-MM) or a quarter (YYYY-Qn).', 422, ['field' => 'period']);
    }

    /**
     * @return array{period:array<string,mixed>, seller:array<string,?string>, a4:list<array<string,mixed>>, a5:list<array{rate:string, base_minor:int, tax_minor:int}>, warnings:list<string>}
     */
    public function kh(string $period): array
    {
        $p = self::period($period);
        $threshold = max(0, (int) config('vat.kh_threshold_czk', 10000)) * 100;
        $a4 = [];
        $a5 = [];
        $documents = $this->documents($p);
        $warnings = $this->filedWarnings($p, $documents);
        foreach ($documents as $doc) {
            $rows = $this->czkRows($doc, $warnings);
            if ($rows === null) {
                continue;
            }
            $domestic = array_values(array_filter($rows, fn (array $r) => $r['category'] === TaxEngine::CAT_STANDARD));
            if ($domestic === [] || strtoupper((string) ($doc->buyer['country'] ?? 'CZ')) !== 'CZ') {
                continue; // OSS (another member state's VAT), reverse charge (SH) or outside the EU: not in the KH
            }
            if ($this->inA4($doc, $threshold)) {
                foreach ($domestic as $r) {
                    $a4[] = ['buyer_vat_id' => strtoupper((string) $this->rootBuyer($doc)['vat_id']), 'document' => (string) $doc->number, 'document_type' => (string) $doc->type, 'supply_date' => $doc->supply_date?->format('Y-m-d'),
                        'rate' => $r['rate'], 'column' => self::KH_RATES[$r['rate']] ?? null, 'base_minor' => $r['net'], 'tax_minor' => $r['tax']];
                }

                continue;
            }
            foreach ($domestic as $r) {
                $a5[$r['rate']] ??= ['rate' => $r['rate'], 'column' => self::KH_RATES[$r['rate']] ?? null, 'base_minor' => 0, 'tax_minor' => 0];
                $a5[$r['rate']]['base_minor'] += $r['net'];
                $a5[$r['rate']]['tax_minor'] += $r['tax'];
            }
        }
        foreach (array_merge($a4, array_values($a5)) as $row) {
            if ($row['column'] === null) {
                $warnings[] = "rate {$row['rate']} % has no KH column — check by hand";
            }
        }

        return ['period' => $p, 'seller' => $this->seller(), 'a4' => $a4, 'a5' => array_values($a5), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * @return array{period:array<string,mixed>, seller:array<string,?string>, rows:list<array{country:string, vat_number:string, code:string, count:int, value_czk:int}>, warnings:list<string>}
     */
    public function sh(string $period): array
    {
        $p = self::period($period);
        $byBuyer = [];
        $documents = $this->documents($p);
        $warnings = $this->filedWarnings($p, $documents);
        foreach ($documents as $doc) {
            $rows = $this->czkRows($doc, $warnings);
            if ($rows === null) {
                continue;
            }
            $net = array_sum(array_map(fn (array $r) => $r['net'], array_filter($rows, fn (array $r) => $r['category'] === TaxEngine::CAT_REVERSE_CHARGE)));
            $hasReverse = array_filter($rows, fn (array $r) => $r['category'] === TaxEngine::CAT_REVERSE_CHARGE) !== [];
            if (! $hasReverse) {
                continue;
            }
            $vatId = strtoupper(preg_replace('/\s+/', '', (string) $this->rootBuyer($doc)['vat_id']) ?? '');
            $country = strtoupper((string) ($doc->buyer['country'] ?? substr($vatId, 0, 2)));
            $number = str_starts_with($vatId, $country) || ($country === 'GR' && str_starts_with($vatId, 'EL')) ? substr($vatId, 2) : $vatId;
            $key = $country.'|'.$number;
            $byBuyer[$key] ??= ['country' => $country, 'vat_number' => $number, 'code' => '3', 'count' => 0, 'value_minor' => 0];
            $byBuyer[$key]['count'] += $doc->type === 'credit_note' ? 0 : 1; // a correction is no further supply
            $byBuyer[$key]['value_minor'] += $net;
        }
        $rows = [];
        foreach ($byBuyer as $row) {
            $rows[] = ['country' => $row['country'], 'vat_number' => $row['vat_number'], 'code' => $row['code'], 'count' => $row['count'], 'value_czk' => (int) round($row['value_minor'] / 100)];
        }

        return ['period' => $p, 'seller' => $this->seller(), 'rows' => $rows, 'warnings' => array_values(array_unique($warnings))];
    }

    /** @param array<string,mixed> $kh */
    public static function khCsv(array $kh): string
    {
        $out = [['section', 'buyer_vat_id', 'document', 'document_type', 'supply_date', 'rate', 'base_czk', 'tax_czk']];
        foreach ($kh['a4'] as $r) {
            $out[] = ['A.4', $r['buyer_vat_id'], $r['document'], $r['document_type'], $r['supply_date'], $r['rate'], self::decimal($r['base_minor']), self::decimal($r['tax_minor'])];
        }
        foreach ($kh['a5'] as $r) {
            $out[] = ['A.5', '', '', '', '', $r['rate'], self::decimal($r['base_minor']), self::decimal($r['tax_minor'])];
        }

        return self::csv($out);
    }

    /** @param array<string,mixed> $sh */
    public static function shCsv(array $sh): string
    {
        $out = [['country', 'vat_number', 'code', 'count', 'value_czk']];
        foreach ($sh['rows'] as $r) {
            $out[] = [$r['country'], $r['vat_number'], $r['code'], (string) $r['count'], (string) $r['value_czk']];
        }

        return self::csv($out);
    }

    /**
     * The KH in the EPO structure (DPHKH1) as a DRAFT: the accountant completes the header (tax office, filing details), checks
     * it in the EPO application and files it. Section C carries only the bases this platform knows.
     *
     * @param  array<string,mixed>  $kh
     */
    public static function khXml(array $kh): string
    {
        $x = new \XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->writeComment(' DRAFT generated by ONhost (onhost:vat:export) — check in EPO before filing; nothing was submitted ');
        $x->startElement('Pisemnost');
        $x->writeAttribute('nazevSW', 'ONhost Control Plane');
        $x->startElement('DPHKH1');
        $x->writeAttribute('verzePis', '03.01');
        self::header($x, $kh['period'], 'KH1', 'khdph_forma');
        self::sellerRow($x, $kh['seller']);
        $line = 0;
        foreach ($kh['a4'] as $r) {
            $x->startElement('VetaA4');
            $x->writeAttribute('c_radku', (string) ++$line);
            $x->writeAttribute('dic_odb', preg_replace('/^CZ/i', '', (string) $r['buyer_vat_id']) ?? '');
            $x->writeAttribute('c_evid_dd', (string) $r['document']);
            $x->writeAttribute('dppd', Carbon::parse((string) $r['supply_date'])->format('d.m.Y'));
            $column = $r['column'] ?? 1;
            $x->writeAttribute('zakl_dane'.$column, self::decimal($r['base_minor']));
            $x->writeAttribute('dan'.$column, self::decimal($r['tax_minor']));
            $x->writeAttribute('kod_rezim_pl', '0');
            $x->writeAttribute('zdph_44', 'N');
            $x->endElement();
        }
        if ($kh['a5'] !== []) {
            $x->startElement('VetaA5');
            foreach ($kh['a5'] as $r) {
                $column = $r['column'] ?? 1;
                $x->writeAttribute('zakl_dane'.$column, self::decimal($r['base_minor']));
                $x->writeAttribute('dan'.$column, self::decimal($r['tax_minor']));
            }
            $x->endElement();
        }
        $bases = [1 => 0, 2 => 0, 3 => 0];
        foreach (array_merge($kh['a4'], $kh['a5']) as $r) {
            $bases[$r['column'] ?? 1] += (int) $r['base_minor'];
        }
        $x->startElement('VetaC');
        $x->writeAttribute('obrat23', self::decimal($bases[1]));
        $x->writeAttribute('obrat5', self::decimal($bases[2] + $bases[3]));
        $x->endElement();
        $x->endElement();
        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }

    /**
     * The SH in the EPO structure (DPHSHV) as a DRAFT, values in whole CZK.
     *
     * @param  array<string,mixed>  $sh
     */
    public static function shXml(array $sh): string
    {
        $x = new \XMLWriter;
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->writeComment(' DRAFT generated by ONhost (onhost:vat:export) — check in EPO before filing; nothing was submitted ');
        $x->startElement('Pisemnost');
        $x->writeAttribute('nazevSW', 'ONhost Control Plane');
        $x->startElement('DPHSHV');
        $x->writeAttribute('verzePis', '02.01');
        self::header($x, $sh['period'], 'SHV', 'shvies_forma');
        self::sellerRow($x, $sh['seller']);
        $line = 0;
        foreach ($sh['rows'] as $r) {
            $x->startElement('VetaR');
            $x->writeAttribute('c_rad', (string) ++$line);
            $x->writeAttribute('k_stat', $r['country']);
            $x->writeAttribute('c_vat', $r['vat_number']);
            $x->writeAttribute('k_pln_eu', $r['code']);
            $x->writeAttribute('pln_pocet', (string) $r['count']);
            $x->writeAttribute('pln_hodnota', (string) $r['value_czk']);
            $x->endElement();
        }
        $x->endElement();
        $x->endElement();
        $x->endDocument();

        return $x->outputMemory();
    }

    /**
     * The tax documents of a period: a VAT payer's, by DUZP.
     *
     * @param  array{from:string, to:string}  $p
     * @return list<Invoice>
     */
    private function documents(array $p): array
    {
        return Invoice::query()->whereIn('type', CzkTaxStatement::TYPES)->whereNotIn('state', [Invoice::DRAFT])->whereNotNull('number')
            ->whereDate('supply_date', '>=', $p['from'])->whereDate('supply_date', '<=', $p['to'])->orderBy('supply_date')->orderByRaw("case when type = 'credit_note' then 1 else 0 end")->orderBy('issued_at')->orderBy('number')->get()
            ->filter(fn (Invoice $doc) => CzkTaxStatement::isTaxDocument($doc))->values()->all();
    }

    /**
     * The document's recap in CZK per rate; a final invoice less the advances it deducts. Null while the CZK rate is not known.
     *
     * @param  list<string>  $warnings
     * @return list<array{rate:string, category:string, net:int, tax:int}>|null
     */
    private function czkRows(Invoice $doc, array &$warnings): ?array
    {
        $czk = strtoupper((string) $doc->currency) === 'CZK';
        if (! $czk && ! is_array($doc->meta['czk'] ?? null)) {
            $warnings[] = "{$doc->number}: the CZK rate is not known yet (onhost:fx:sync) — left out";

            return null;
        }
        $rows = [];
        foreach ($czk ? (array) $doc->tax_summary : (array) $doc->meta['czk']['summary'] as $i => $row) {
            $category = (string) ($row['category'] ?? ($doc->tax_summary[$i]['category'] ?? 'S'));
            $rows[self::rate((string) ($row['rate'] ?? '0')).'|'.$category] = ['rate' => self::rate((string) ($row['rate'] ?? '0')), 'category' => $category,
                'net' => (int) ($czk ? ($row['net'] ?? 0) : ($row['net_minor'] ?? 0)), 'tax' => (int) ($czk ? ($row['tax'] ?? 0) : ($row['tax_minor'] ?? 0))];
        }
        // the advance's tax document reported this part already. Its amounts are read from the receipt itself now (M3, security
        // review of #106): the snapshot on the final invoice was taken when the advance's CZK rate may not have been known yet
        foreach ((array) ($doc->meta['advances'] ?? []) as $advance) {
            $deduct = $this->advanceRows(is_array($advance) ? $advance : [], $czk);
            if ($deduct === null) {
                $warnings[] = "{$doc->number}: its advance ".((string) ($advance['number'] ?? '?')).' has no CZK rate yet (onhost:fx:sync) — left out';

                return null;
            }
            foreach ($deduct as $key => $amounts) {
                if (isset($rows[$key])) {
                    $rows[$key]['net'] -= $amounts['net'];
                    $rows[$key]['tax'] -= $amounts['tax'];
                }
            }
        }
        foreach ($rows as $key => $row) { // H2: an advance never takes a final invoice below zero — cut, and said
            if ($doc->type !== 'credit_note' && ($row['net'] < 0 || $row['tax'] < 0)) {
                $warnings[] = "{$doc->number}: the advances it deducts exceed what it states at {$row['rate']} % — counted as 0, check by hand";
                $rows[$key]['net'] = max(0, $row['net']);
                $rows[$key]['tax'] = max(0, $row['tax']);
            }
        }

        return array_values($rows);
    }

    /**
     * What one deducted advance stated per rate, in CZK: a CZK advance as the receipt states it, another currency by the receipt's
     * CZK recap as it is now. Null while that recap is not known.
     *
     * @param  array<string,mixed>  $advance
     * @return array<string, array{net:int, tax:int}>|null
     */
    private function advanceRows(array $advance, bool $czk): ?array
    {
        $receipt = isset($advance['id']) ? Invoice::query()->find((string) $advance['id']) : null;
        $out = [];
        if ($czk) {
            foreach ((array) ($receipt->tax_summary ?? $advance['summary'] ?? []) as $row) {
                $key = self::rate((string) ($row['rate'] ?? '0')).'|'.(string) ($row['category'] ?? 'S');
                $out[$key] = ['net' => ($out[$key]['net'] ?? 0) + (int) ($row['net'] ?? 0), 'tax' => ($out[$key]['tax'] ?? 0) + (int) ($row['tax'] ?? 0)];
            }

            return $out;
        }
        $recap = $receipt !== null ? ($receipt->meta['czk'] ?? null) : ($advance['czk'] ?? null);
        if (! is_array($recap)) {
            return null;
        }
        foreach ((array) ($recap['summary'] ?? []) as $row) {
            $key = self::rate((string) ($row['rate'] ?? '0')).'|'.(string) ($row['category'] ?? 'S');
            $out[$key] = ['net' => ($out[$key]['net'] ?? 0) + (int) ($row['net_minor'] ?? 0), 'tax' => ($out[$key]['tax'] ?? 0) + (int) ($row['tax_minor'] ?? 0)];
        }

        return $out;
    }

    /**
     * M4: the period, or part of it, is marked filed (ONHOST_VAT_FILED_THROUGH) and documents were issued into it afterwards
     * (`meta.filed_period`): the filed return and KH/SH need a correction.
     *
     * @param  array{from:string, to:string}  $p
     * @param  list<Invoice>  $documents
     * @return list<string>
     */
    private function filedWarnings(array $p, array $documents): array
    {
        $filed = (string) config('vat.filed_through', '');
        if (preg_match('/^\d{4}-\d{2}$/', $filed) !== 1 || substr($p['from'], 0, 7) > $filed) {
            return [];
        }
        $late = array_values(array_map(fn (Invoice $d) => (string) $d->number, array_filter($documents, fn (Invoice $d) => isset($d->meta['filed_period']))));
        $out = ['period '.substr($p['from'], 0, 7).($p['from'] !== $p['to'] && substr($p['to'], 0, 7) !== substr($p['from'], 0, 7) ? '–'.substr($p['to'], 0, 7) : '')." is marked filed (ONHOST_VAT_FILED_THROUGH={$filed}) — this is a draft of a correction"];
        if ($late !== []) {
            $out[] = 'issued into the filed period afterwards (a corrective/následné report is due): '.implode(', ', $late);
        }

        return $out;
    }

    /** A4: a buyer registered for VAT in CZ and a document above the threshold — a credit note follows the document it corrects. */
    private function inA4(Invoice $doc, int $thresholdMinor): bool
    {
        $root = $doc->corrects_invoice_id !== null ? (Invoice::query()->find($doc->corrects_invoice_id) ?? $doc) : $doc;
        $vatId = strtoupper((string) ($root->buyer['vat_id'] ?? ''));
        if (! str_starts_with($vatId, 'CZ') || ($root->buyer['vat_status'] ?? null) !== VatStanding::VALID) {
            return false;
        }
        $totalCzk = strtoupper((string) $root->currency) === 'CZK' ? (int) $root->total_minor : (int) ($root->meta['czk']['total_minor'] ?? 0);

        return abs($totalCzk) > $thresholdMinor;
    }

    /** @return array<string,mixed> the buyer of the document — of the corrected one for a credit note */
    private function rootBuyer(Invoice $doc): array
    {
        $root = $doc->corrects_invoice_id !== null ? Invoice::query()->find($doc->corrects_invoice_id) : null;

        return (array) ($root->buyer ?? $doc->buyer);
    }

    /** @return array<string,?string> */
    private function seller(): array
    {
        $entity = LegalEntity::query()->find((string) config('onhost.billing.legal_entity', 'onhost-cz'));

        return ['name' => $entity?->name, 'dic' => $entity === null ? null : (string) (($entity->dic ?: $entity->vat_id) ?? ''), 'ico' => $entity?->ico];
    }

    /** @param array<string,mixed> $p */
    private static function header(\XMLWriter $x, array $p, string $document, string $formAttribute): void
    {
        $x->startElement('VetaD');
        $x->writeAttribute('k_uladis', 'DPH');
        $x->writeAttribute('dokument', $document);
        $x->writeAttribute('rok', (string) $p['year']);
        if ($p['month'] !== null) {
            $x->writeAttribute('mesic', (string) $p['month']);
        } else {
            $x->writeAttribute('ctvrt', (string) $p['quarter']);
        }
        $x->writeAttribute($formAttribute, 'B');
        $x->writeAttribute('d_poddp', now()->setTimezone((string) config('onhost.billing.timezone', 'Europe/Prague'))->format('d.m.Y'));
        $x->endElement();
    }

    /** @param array<string,?string> $seller */
    private static function sellerRow(\XMLWriter $x, array $seller): void
    {
        $x->startElement('VetaP');
        $x->writeAttribute('dic', preg_replace('/^CZ/i', '', (string) ($seller['dic'] ?? '')) ?? '');
        $x->writeAttribute('typ_ds', 'P');
        $x->writeAttribute('zkrobchjm', (string) ($seller['name'] ?? ''));
        $x->endElement();
    }

    private static function rate(string $rate): string
    {
        return str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    }

    private static function decimal(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @param list<list<string|null>> $rows */
    private static function csv(array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($h, $row, ',', '"', '');
        }
        rewind($h);
        $out = (string) stream_get_contents($h);
        fclose($h);

        return $out;
    }
}
