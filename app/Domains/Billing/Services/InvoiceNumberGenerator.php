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
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($series, $year): string {
            $row = DB::table('invoice_number_sequences')
                ->where('series', $series->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('invoice_number_sequences')->insert([
                    'series'      => $series->value,
                    'year'        => $year,
                    'last_number' => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                return $series->format($year, 1);
            }

            $next = $row->last_number + 1;

            DB::table('invoice_number_sequences')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $series->format($year, $next);
        });
    }
}
