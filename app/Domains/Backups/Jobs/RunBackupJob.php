<?php

declare(strict_types=1);

namespace App\Domains\Backups\Jobs;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Providers\LocalMockBackupProvider;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Executes one backup job via the (mock) provider.
 * Idempotent: a job row already in a final state is never re-run.
 */
final class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public int $backupJobId,
    ) {
        $this->onQueue(Config::string('provisioning.queues.default', 'provisioning'));
    }

    public function handle(): void
    {
        $job = BackupJob::find($this->backupJobId);

        if ($job === null || in_array($job->status, [BackupJobStatus::Success, BackupJobStatus::Failed], true)) {
            return;
        }

        if (config('provisioning.mock_mode', true) !== true) {
            throw new ProvisioningException(
                'Real backup providers are not implemented — enable PROVISIONING_MOCK_MODE.',
                driver: 'backups',
                retryable: false,
            );
        }

        $job->update(['status' => BackupJobStatus::Running, 'started_at' => now()]);

        try {
            $file = app(LocalMockBackupProvider::class)->createBackup($job);

            $job->update([
                'status'      => BackupJobStatus::Success,
                'finished_at' => now(),
                'size_mb'     => $file->size_mb,
            ]);

            $job->policy?->update(['last_run_at' => now()]);

            activity('backup')
                ->performedOn($job)
                ->withProperties(['service_id' => $job->service_id, 'size_mb' => $file->size_mb, 'mock' => true])
                ->log('backup.completed');
        } catch (Throwable $e) {
            $job->update([
                'status'        => BackupJobStatus::Failed,
                'finished_at'   => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            activity('backup')
                ->performedOn($job)
                ->withProperties(['service_id' => $job->service_id, 'error' => mb_substr($e->getMessage(), 0, 200)])
                ->log('backup.failed');
        }
    }
}
