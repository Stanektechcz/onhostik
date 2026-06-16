<?php

declare(strict_types=1);

namespace App\Domains\Backups\Providers;

use App\Domains\Backups\Contracts\BackupProviderInterface;
use App\Domains\Backups\Models\BackupFile;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupRestore;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Str;

/**
 * Local MOCK backup provider — simulates a completed archive without
 * touching the filesystem or any storage API.
 */
final class LocalMockBackupProvider implements BackupProviderInterface
{
    public function createBackup(BackupJob $job): BackupFile
    {
        $retention = $job->policy->retention_days ?? 14;

        return $job->files()->create([
            'disk'       => 'local',
            'path'       => sprintf('mock-backups/service-%d/%s.tar.gz', $job->service_id, now()->format('Ymd-His')),
            'size_mb'    => random_int(40, 480),
            'checksum'   => sha1(Str::uuid()->toString()),
            'expires_at' => now()->addDays($retention)->toDateString(),
        ]);
    }

    public function restoreBackup(BackupRestore $restore): void
    {
        // Mock restore completes instantly.
        $restore->update(['status' => 'done']);
    }

    /** @return list<BackupFile> */
    public function listBackups(Service $service): array
    {
        return array_values(BackupFile::query()
            ->whereHas('job', fn ($query) => $query->where('service_id', $service->id))
            ->latest('id')
            ->get()
            ->all());
    }

    public function deleteBackup(BackupFile $file): void
    {
        $file->delete();
    }

    public function testConnection(): bool
    {
        return true;
    }
}
