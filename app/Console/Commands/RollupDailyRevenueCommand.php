<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Reporting\Models\RevenueDaily;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refreshes the daily-revenue rollup from paid invoices (audit 500 #11).
 *
 * Recomputes a trailing window (default 35 days) so late payments and
 * backdated adjustments are picked up. Idempotent — re-running overwrites the
 * same (date, currency) rows.
 */
final class RollupDailyRevenueCommand extends Command
{
    protected $signature = 'reporting:rollup-revenue {--days=35}';

    protected $description = 'Rebuild the daily revenue rollup from paid invoices.';

    public function handle(): int
    {
        $days  = max(1, (int) $this->option('days'));
        $since = now()->subDays($days)->startOfDay();

        $rows = DB::table('invoices')
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->selectRaw('DATE(paid_at) as d, currency, SUM(total) as gross, SUM(subtotal) as net, COUNT(*) as cnt')
            ->groupBy('d', 'currency')
            ->get();

        foreach ($rows as $row) {
            RevenueDaily::updateOrCreate(
                ['date' => $row->d, 'currency' => $row->currency],
                [
                    'gross_minor'    => (int) $row->gross,
                    'net_minor'      => (int) $row->net,
                    'invoices_count' => (int) $row->cnt,
                ],
            );
        }

        $this->info("Rolled up {$rows->count()} day/currency buckets from the last {$days} days.");

        return self::SUCCESS;
    }
}
