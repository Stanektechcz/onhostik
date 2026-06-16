<?php

declare(strict_types=1);

namespace App\Domains\Backups\Providers;

use App\Domains\Backups\Contracts\BackupProviderInterface;
use App\Domains\Backups\Models\BackupFile;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupRestore;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;

/**
 * S3-compatible backup storage — PLACEHOLDER slot, dry-run only.
 * Real implementation (S3 SDK + lifecycle rules) arrives when backups go
 * live; until then every operation refuses so no real bucket can be hit.
 */
final class S3CompatibleBackupProvider implements BackupProviderInterface
{
    public function createBackup(BackupJob $job): BackupFile
    {
        throw $this->notImplemented();
    }

    public function restoreBackup(BackupRestore $restore): void
    {
        throw $this->notImplemented();
    }

    /** @return list<BackupFile> */
    public function listBackups(Service $service): array
    {
        return []; // read-only placeholder
    }

    public function deleteBackup(BackupFile $file): void
    {
        throw $this->notImplemented();
    }

    public function testConnection(): bool
    {
        return false; // not configured — honest answer
    }

    private function notImplemented(): ProvisioningException
    {
        return new ProvisioningException(
            'S3 backup provider is a placeholder — use local_mock backups.',
            driver: 's3_backups',
            retryable: false,
        );
    }
}
