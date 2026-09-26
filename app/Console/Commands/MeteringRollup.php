<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Metering\UsageRollup;

/**
 * Rolls the raw usage samples of the last complete days into day rows and rebuilds their months (TASK-0023 metering-core).
 * Telemetry only; nothing here is billed. Staff can switch it off (console → automation, rule `metering.rollup`).
 */
final class MeteringRollup extends Command
{
    protected $signature = 'onhost:metering:rollup {--days=2 : complete days back to roll up (reruns are idempotent)}';

    protected $description = 'Roll raw usage samples into daily and monthly rows';

    public function handle(UsageRollup $rollup, AutomationLedger $ledger): int
    {
        if ($ledger->off('metering.rollup')) {
            $this->warn('switched off by staff (console → automation)');

            return self::SUCCESS;
        }
        $result = $rollup->rollup(max(1, (int) $this->option('days')));
        $ledger->record('metering.rollup', $result);
        $this->table(array_keys($result), [$result]);

        return self::SUCCESS;
    }
}
