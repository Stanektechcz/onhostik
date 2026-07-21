<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the data-retention policy (audit INFRA #180).
 *
 * Prunes old rows from operational/telemetry tables per config/retention.php.
 * Business and legal records (invoices, orders, payments, consents, credit
 * ledger) are deliberately out of scope — those have statutory retention and
 * are never touched here.
 *
 * Scheduled daily. `--dry-run` reports what would be deleted without deleting.
 */
final class ApplyDataRetentionCommand extends Command
{
    protected $signature = 'retention:apply {--dry-run : Report counts without deleting}';

    protected $description = 'Prune operational data older than the configured retention window';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $policies = (array) config('retention.policies', []);
        $total    = 0;

        foreach ($policies as $name => $policy) {
            $days = (int) ($policy['days'] ?? 0);

            // 0 = keep forever; skip.
            if ($days <= 0) {
                continue;
            }

            $table  = (string) ($policy['table'] ?? '');
            $column = (string) ($policy['column'] ?? 'created_at');

            if ($table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            $cutoff = now()->subDays($days);

            $query = DB::table($table)->where($column, '<', $cutoff);

            // Optional extra constraints (e.g. only resolved incidents).
            foreach ((array) ($policy['where'] ?? []) as $col => $val) {
                $query->where($col, $val);
            }

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('  [dry-run] %s: would delete %d row(s) older than %d days.', $name, $count, $days));
                $total += $count;

                continue;
            }

            // Delete in chunks so a huge backlog does not lock the table.
            $deleted = 0;
            do {
                $chunk = (clone $query)->limit(5000)->delete();
                $deleted += $chunk;
            } while ($chunk > 0);

            $this->line(sprintf('  %s: deleted %d row(s).', $name, $deleted));
            $total += $deleted;
        }

        if (! $dryRun && $total > 0) {
            activity('retention')
                ->withProperties(['pruned' => $total])
                ->log('data_retention.applied');
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Retention complete — {$total} row(s).");

        return self::SUCCESS;
    }
}
