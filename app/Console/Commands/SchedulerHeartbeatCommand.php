<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Monitoring\Services\SchedulerHeartbeat;
use Illuminate\Console\Command;

/**
 * Writes the scheduler heartbeat. Scheduled every minute — if these stop, the
 * system-health page flags the scheduler as stale (cron is down).
 */
final class SchedulerHeartbeatCommand extends Command
{
    protected $signature = 'scheduler:heartbeat';

    protected $description = 'Record that the task scheduler ran (dead-cron detection).';

    public function handle(SchedulerHeartbeat $heartbeat): int
    {
        $heartbeat->record();

        return self::SUCCESS;
    }
}
