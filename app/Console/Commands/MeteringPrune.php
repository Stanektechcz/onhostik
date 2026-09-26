<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Metering\UsageRollup;

/**
 * The owner's retention for usage samples (decision 12, TASK-0023 metering-core): raw samples 45 days, daily rows 400 days,
 * monthly rows for ever (`onhost.metering.retention`). Staff can switch it off (rule `metering.prune`).
 */
final class MeteringPrune extends Command
{
    protected $signature = 'onhost:metering:prune';

    protected $description = 'Delete usage samples older than the retention (raw 45 days, daily 400 days; monthly rows are kept)';

    public function handle(UsageRollup $rollup, AutomationLedger $ledger): int
    {
        if ($ledger->off('metering.prune')) {
            $this->warn('switched off by staff (console → automation)');

            return self::SUCCESS;
        }
        $result = $rollup->prune();
        $ledger->record('metering.prune', $result);
        $this->table(array_keys($result), [$result]);

        return self::SUCCESS;
    }
}
