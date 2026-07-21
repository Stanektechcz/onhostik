<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Enums\InvoiceSeries;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, gap-aware invoice numbering.
 *
 * Format: {SERIES}-{YYYY}-{NNNNNN}  e.g. CZ-2026-000001
 *
 * Concurrency: the sequence row is locked FOR UPDATE inside a transaction,
 * making duplicate numbers impossible even under parallel issuance.
 */
final class InvoiceNumberGenerator
{
    public function next(InvoiceSeries $series, ?int $year = null): string
    {
        return $this->nextForKey($series->value, $year);
    }

    /**
     * Next number for an arbitrary series KEY (audit 111 — per-reseller series).
     *
     * A reseller acting as the invoicing entity gets their own prefix (e.g.
     * "RS1"), which is just another key in the same sequence table — it gets an
     * independent counter and never collides with the global "CZ"/"EU" series.
     * The format is identical to the enum's ({KEY}-{YYYY}-{NNNNNN}), so a
     * reseller invoice number is indistinguishable in shape from a global one.
     */
    public function nextForKey(string $seriesKey, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($seriesKey, $year): string {
            $row = DB::table('invoice_number_sequences')
                ->where('series', $seriesKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('invoice_number_sequences')->insert([
                    'series'      => $seriesKey,
                    'year'        => $year,
                    'last_number' => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                return $this->format($seriesKey, $year, 1);
            }

            $next = $row->last_number + 1;

            DB::table('invoice_number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $this->format($seriesKey, $year, $next);
        });
    }

    private function format(string $seriesKey, int $year, int $number): string
    {
        return sprintf('%s-%d-%06d', $seriesKey, $year, $number);
    }
}
