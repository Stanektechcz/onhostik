<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\Tax\Models\ExchangeRate;

/**
 * A tax document in another currency states its VAT in CZK (§ 29 (1) l) of the Czech VAT act), converted at the rate of the
 * Czech National Bank valid for the day the tax is due (§ 4). A document in EUR carried neither: the customer could not
 * deduct from it and the seller's return had no source for the figure.
 *
 *  · the rate is the list valid for the document's supply day (`CnbRates::rateFor`);
 *  · a credit note is converted at the rate of the document it corrects (§ 42), not at today's;
 *  · each VAT rate of the summary is converted on its own and the totals are their sums, so the recap adds up;
 *  · a bank that does not answer never stops a document: it is issued with `meta.czk_pending` and completed by
 *    `onhost:fx:sync` — the amounts of the document do not change, the statutory CZK recap is added.
 */
final class CzkTaxStatement
{
    /** The documents that are tax documents: a proforma is a request to pay, a statement only lists what the credit paid for. */
    public const TYPES = ['invoice', 'receipt', 'credit_note'];

    public function __construct(private readonly CnbRates $rates) {}

    public function concerns(Invoice $invoice): bool
    {
        return strtoupper((string) $invoice->currency) !== 'CZK' && in_array($invoice->type, self::TYPES, true);
    }

    /**
     * The `meta` of the document with its CZK recap — or with `czk_pending` when the rate is not known yet.
     *
     * @return array<string,mixed>
     */
    public function stamp(Invoice $invoice): array
    {
        $meta = (array) $invoice->meta;
        if (! $this->concerns($invoice)) {
            return $meta;
        }
        $statement = $this->statement($invoice);
        unset($meta['czk'], $meta['czk_pending']);

        return $statement === null ? $meta + ['czk_pending' => true] : $meta + ['czk' => $statement];
    }

    /** @return array<string,mixed>|null */
    public function statement(Invoice $invoice): ?array
    {
        $basis = 'supply_date';
        $rate = null;
        $original = $invoice->corrects_invoice_id !== null ? Invoice::query()->find($invoice->corrects_invoice_id) : null;
        $inherited = is_array($original?->meta['czk'] ?? null) ? $original->meta['czk'] : null;
        if ($inherited !== null && (int) ($inherited['rate_micro'] ?? 0) > 0) {
            $basis = 'original';
            $rate = ['rate_micro' => (int) $inherited['rate_micro'], 'amount' => max(1, (int) ($inherited['amount'] ?? 1)), 'valid_on' => (string) ($inherited['valid_on'] ?? ''), 'source' => (string) ($inherited['source'] ?? CnbRates::SOURCE)];
        } else {
            $row = $this->rates->rateFor((string) $invoice->currency, $invoice->supply_date ?? $invoice->issued_at ?? now());
            if ($row === null) {
                return null;
            }
            $rate = ['rate_micro' => $row->rate_micro, 'amount' => $row->amount, 'valid_on' => $row->valid_on->format('Y-m-d'), 'source' => $row->source];
        }
        $summary = [];
        foreach ((array) $invoice->tax_summary as $line) {
            $summary[] = [
                'rate' => (string) ($line['rate'] ?? '0'), 'category' => (string) ($line['category'] ?? ''),
                'net_minor' => ExchangeRate::convert((int) ($line['net'] ?? 0), $rate['rate_micro'], $rate['amount']),
                'tax_minor' => ExchangeRate::convert((int) ($line['tax'] ?? 0), $rate['rate_micro'], $rate['amount']),
            ];
        }
        $net = array_sum(array_column($summary, 'net_minor'));
        $tax = array_sum(array_column($summary, 'tax_minor'));

        return [
            'source' => $rate['source'], 'basis' => $basis, 'currency' => strtoupper((string) $invoice->currency), 'valid_on' => $rate['valid_on'], 'amount' => $rate['amount'],
            'rate_micro' => $rate['rate_micro'], 'rate' => ExchangeRate::decimalOf($rate['rate_micro']),
            'net_minor' => $net, 'tax_minor' => $tax, 'total_minor' => $net + $tax, 'summary' => $summary,
        ];
    }
}
