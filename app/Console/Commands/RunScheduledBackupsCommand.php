<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Jobs\RunBackupJob;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Dispatches RunBackupJob for every active BackupPolicy whose schedule is due.
 *
 * Frequency values: 'daily' (24 h), 'weekly' (7 d), 'monthly' (30 d).
 * A policy is considered due when last_run_at is null or older than the
 * threshold — idempotent because RunBackupJob checks job status before acting.
 */
final class RunScheduledBackupsCommand extends Command
{
    protected $signature = 'backups:run-scheduled {--dry-run : Print policies due without dispatching jobs}';

    protected $description = 'Dispatch backup jobs for all active policies that are due';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $now       = Carbon::now();
        $dispatched = 0;
        $skipped   = 0;

        $policies = BackupPolicy::query()
            ->where('is_active', true)
            ->with('service')
            ->get();

        foreach ($policies as $policy) {
            if (! $this->isDue($policy, $now)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Policy #{$policy->id} service_id={$policy->service_id} frequency={$policy->frequency} due");
                $dispatched++;
                continue;
            }

            $job = BackupJob::create([
                'backup_policy_id' => $policy->id,
                'service_id'       => $policy->service_id,
                'type'             => 'scheduled',
                'status'           => BackupJobStatus::Pending,
            ]);

            RunBackupJob::dispatch($job->id);
            $dispatched++;

            activity('backup')
                ->performedOn($job)
                ->withProperties(['policy_id' => $policy->id, 'service_id' => $policy->service_id])
                ->log('backup.scheduled_dispatch');
        }

        $this->info("Backup scheduler: {$dispatched} dispatched, {$skipped} not due.");

        return self::SUCCESS;
    }

    private function isDue(BackupPolicy $policy, Carbon $now): bool
    {
        if ($policy->last_run_at === null) {
            return true;
        }

        $threshold = match ($policy->frequency) {
            'weekly'  => 7 * 24,
            'monthly' => 30 * 24,
            default   => 24, // daily
        };

        return $policy->last_run_at->diffInHours($now) >= $threshold;
    }
}
