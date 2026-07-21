<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Shared\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Offsite database backup (audit INFRA #7).
 *
 * Scheduled daily. Dumps the database, gzips it, and uploads it to the offsite
 * disk (default `backup-s3`), keeping a rolling window. Fails loudly — a backup
 * that silently stopped running is worse than none, because you find out when
 * you need to restore.
 */
final class BackupDatabaseCommand extends Command
{
    protected $signature = 'db:backup
        {--disk= : Target filesystem disk (defaults to config backup.disk)}
        {--keep= : Days of backups to retain (defaults to config backup.keep_days)}';

    protected $description = 'Dump the database and upload it to the offsite backup disk';

    public function handle(DatabaseBackupService $service): int
    {
        $disk = (string) ($this->option('disk') ?: config('backup.disk', 'backup-s3'));
        $keep = (int) ($this->option('keep') ?: config('backup.keep_days', 14));

        try {
            $result = $service->run($disk, $keep);
        } catch (Throwable $e) {
            $this->error('Database backup failed: ' . $e->getMessage());

            activity('backup')
                ->withProperties(['disk' => $disk, 'error' => mb_substr($e->getMessage(), 0, 200)])
                ->log('db_backup.failed');

            return self::FAILURE;
        }

        if ($result['skipped'] !== null) {
            // A skip (e.g. in-memory DB in a test) is not an error, but it must
            // be visible so a misconfigured production job is noticed.
            $this->warn('Backup skipped: ' . $result['skipped']);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Backup stored: %s (%s KB) on [%s]; pruned %d old backup(s).',
            $result['stored'],
            number_format($result['bytes'] / 1024, 1),
            $disk,
            $result['pruned'],
        ));

        activity('backup')
            ->withProperties(['disk' => $disk, 'file' => $result['stored'], 'bytes' => $result['bytes'], 'pruned' => $result['pruned']])
            ->log('db_backup.completed');

        return self::SUCCESS;
    }
}
