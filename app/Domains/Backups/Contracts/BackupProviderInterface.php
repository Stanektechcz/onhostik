<?php

declare(strict_types=1);

namespace App\Domains\Backups\Contracts;

use App\Domains\Backups\Models\BackupFile;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupRestore;
use App\Domains\Provisioning\Models\Service;

/**
 * Backup storage backend contract (local mock now, S3/B2 later).
 */
interface BackupProviderInterface
{
    /** Perform the backup for the job and persist the resulting file row. */
    public function createBackup(BackupJob $job): BackupFile;

    /** Execute (or simulate) a restore request. */
    public function restoreBackup(BackupRestore $restore): void;

    /** @return list<BackupFile> stored backups for the service, newest first */
    public function listBackups(Service $service): array;

    public function deleteBackup(BackupFile $file): void;

    public function testConnection(): bool;
}
