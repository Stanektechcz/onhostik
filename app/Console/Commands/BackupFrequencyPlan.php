<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Web\BackupScheduler;

/**
 * Owner decision 18 (TASK-0024): before `backups.as_sold` is switched on, this lists, read-only, what it would change for
 * every web and managed service — the frequency sold, the frequency and history the scheduler gives today, what it would
 * give under the rule, and roughly how much more the backup disk would hold. Nothing is written, nothing is asked of a panel.
 */
final class BackupFrequencyPlan extends Command
{
    protected $signature = 'onhost:backups:frequency-plan {--limit=500 : services read per chunk; every one is listed}';

    protected $description = 'Dry run: what the backups.as_sold rule would change for web and managed backups (writes nothing)';

    public function handle(BackupScheduler $scheduler, AutomationLedger $ledger): int
    {
        $rows = $scheduler->frequencyPlan(max(1, (int) $this->option('limit')));
        $this->table(
            ['service', 'plan', 'sold', 'now', 'as sold', 'history now', 'history as sold', 'extra copies', 'extra storage (est.)'],
            array_map(fn (array $r) => [$r['service'], $r['plan'], $r['sold'], $r['now'], $r['as_sold'], self::duration($r['history_now']), self::duration($r['history_as_sold']),
                $r['extra_copies'], $r['extra_bytes'] === null ? ($r['extra_copies'] > 0 ? 'no backup yet' : '-') : self::bytes($r['extra_bytes'])], $rows),
        );
        $changing = array_filter($rows, fn (array $r) => $r['changes']);
        $bytes = array_sum(array_map(fn (array $r) => (int) ($r['extra_bytes'] ?? 0), $changing));
        $this->info(sprintf('%d service(s) change · about %s more on the backup disk · rule %s: %s · nothing was changed',
            count($changing), self::bytes($bytes), BackupScheduler::AS_SOLD_RULE, $ledger->enabled(BackupScheduler::AS_SOLD_RULE) ? 'on' : 'off'));

        return self::SUCCESS;
    }

    private static function duration(int $minutes): string
    {
        return match (true) {
            $minutes >= 1440 && $minutes % 1440 === 0 => intdiv($minutes, 1440).' d',
            $minutes >= 60 => round($minutes / 60, 1).' h',
            default => $minutes.' min',
        };
    }

    private static function bytes(int $bytes): string
    {
        return number_format($bytes / 1024 ** 3, 1, '.', ' ').' GB';
    }
}
