<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Illuminate\Support\Facades\DB;
use Onhost\Platform\Ids\PublicId;

/**
 * Concurrency-safe sequential numbering per legal entity / series / year (§64.3,
 * S56). The sequence row is locked for the duration of the allocation transaction,
 * so two invoices can never share a number and no number is skipped.
 */
final class InvoiceNumberAllocator
{
    /** @return array{number:string, sequence:int} */
    public function allocate(string $legalEntity, string $series, ?int $year = null): array
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($legalEntity, $series, $year) {
            DB::table('invoice_sequences')->insertOrIgnore(['legal_entity' => $legalEntity, 'series' => $series, 'year' => $year, 'next' => 1]);
            $row = DB::table('invoice_sequences')->where(['legal_entity' => $legalEntity, 'series' => $series, 'year' => $year])->lockForUpdate()->first();
            $sequence = (int) $row->next;
            DB::table('invoice_sequences')->where('id', $row->id)->update(['next' => $sequence + 1]);

            return ['number' => PublicId::documentNumber($series, $year, $sequence, 4), 'sequence' => $sequence];
        }, 3);
    }

    /** Variable symbol for bank matching: year + zero-padded sequence (max 10 digits). */
    public static function variableSymbol(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return substr($digits, 0, 10);
    }
}
