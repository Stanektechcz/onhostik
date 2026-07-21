<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Communication\Services\CriticalAlertDispatcher;
use App\Domains\Provisioning\Services\FleetCapacityReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Warns when a driver's server fleet is running out of room (audit E52).
 *
 * ServerSelector only complains once every server is full — at which point a
 * paid order is already landing on an over-committed box. This runs ahead of
 * that so capacity is a planning decision, not an incident.
 */
class CheckFleetCapacityCommand extends Command
{
    protected $signature   = 'provisioning:check-capacity {--threshold= : Override the warn percentage}';
    protected $description = 'Alert when a provisioning driver is close to running out of server capacity.';

    public function handle(FleetCapacityReport $report, CriticalAlertDispatcher $alerts): int
    {
        $threshold = $this->option('threshold') !== null
            ? (float) $this->option('threshold')
            : null;

        $rows = $report->perDriver();

        if ($rows === []) {
            $this->line('No active servers to report on.');

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->line(sprintf(
                '%-14s %d servers  %d used  %s',
                $row['driver'],
                $row['servers'],
                $row['used'],
                $row['unlimited'] ? 'unlimited' : "{$row['used']}/{$row['capacity']} ({$row['used_percent']}%)",
            ));
        }

        $breaching = $report->breaching($threshold);

        if ($breaching === []) {
            $this->info('All drivers within capacity threshold.');

            return self::SUCCESS;
        }

        foreach ($breaching as $row) {
            $message = sprintf(
                'Driver **%s** je na %s%% kapacity (%d/%d, volno %d) napříč %d servery.',
                $row['driver'],
                $row['used_percent'],
                $row['used'],
                $row['capacity'],
                $row['free'],
                $row['servers'],
            );

            $this->warn($message);

            Log::warning('provisioning.capacity_warning', $row);

            // Reuses the I126 operator alerting path — silent unless a webhook
            // is configured, and a delivery failure never breaks the check.
            $alerts->send('Docházející kapacita serverů', $message, [
                'Driver' => $row['driver'],
                'Využito' => $row['used_percent'] . '%',
                'Volno'  => $row['free'],
            ]);
        }

        return self::SUCCESS;
    }
}
