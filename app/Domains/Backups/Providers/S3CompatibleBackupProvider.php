<?php

declare(strict_types=1);

namespace App\Domains\Backups\Providers;

use App\Domains\Backups\Contracts\BackupProviderInterface;
use App\Domains\Backups\Models\BackupFile;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupRestore;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * S3-compatible backup provider.
 *
 * Stores backup manifests + any externally-sourced archives on an S3 bucket.
 * Uses the 'backup-s3' disk from config/filesystems.php.
 *
 * Required env vars: S3_BACKUP_KEY, S3_BACKUP_SECRET, S3_BACKUP_BUCKET,
 *                    S3_BACKUP_ENDPOINT (for non-AWS like Hetzner / MinIO),
 *                    S3_BACKUP_REGION (default: eu-central-1).
 *
 * The initial implementation stores a JSON manifest of service metadata.
 * Full file-level backup requires aaPanel integration to provide archive URLs.
 */
final class S3CompatibleBackupProvider implements BackupProviderInterface
{
    private const DISK = 'backup-s3';

    public function createBackup(BackupJob $job): BackupFile
    {
        $this->assertConfigured();

        $timestamp = now()->format('Ymd-His');
        $path      = sprintf('backups/service-%d/%s.json', $job->service_id, $timestamp);

        $manifest = [
            'backup_job_id' => $job->id,
            'service_id'    => $job->service_id,
            'type'          => $job->type,
            'created_at'    => now()->toISOString(),
            'service'       => [
                'label'         => $job->service?->label,
                'status'        => $job->service?->status?->value,
                'next_due_date' => $job->service?->next_due_date?->toDateString(),
            ],
        ];

        $content = (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            Storage::disk(self::DISK)->put($path, $content);
        } catch (Throwable $e) {
            throw new ProvisioningException(
                "S3 backup upload failed: {$e->getMessage()}",
                driver: 's3_backups',
                retryable: true,
            );
        }

        $retention = $job->policy->retention_days ?? 14;
        $sizeMb    = max(round(strlen($content) / 1_048_576, 4), 0.001);

        Log::info('S3 backup created', ['service_id' => $job->service_id, 'path' => $path, 'size_mb' => $sizeMb]);

        return $job->files()->create([
            'disk'       => self::DISK,
            'path'       => $path,
            'size_mb'    => $sizeMb,
            'checksum'   => md5($content),
            'expires_at' => now()->addDays($retention)->toDateString(),
        ]);
    }

    public function restoreBackup(BackupRestore $restore): void
    {
        $file = $restore->file;

        if ($file === null) {
            $restore->update(['status' => 'failed', 'error' => 'BackupFile not found.']);
            return;
        }

        if (!Storage::disk(self::DISK)->exists($file->path)) {
            $restore->update(['status' => 'failed', 'error' => "File not found on S3: {$file->path}"]);
            return;
        }

        // Real restore (file extraction) requires aaPanel integration.
        // For now: record the restore as pending manual action.
        $restore->update(['status' => 'pending_manual', 'error' => null]);

        Log::info('S3 restore requested', ['restore_id' => $restore->id, 'path' => $file->path]);
    }

    /** @return list<BackupFile> */
    public function listBackups(Service $service): array
    {
        return array_values(BackupFile::query()
            ->whereHas('job', fn ($q) => $q->where('service_id', $service->id))
            ->where('disk', self::DISK)
            ->latest('id')
            ->get()
            ->all());
    }

    public function deleteBackup(BackupFile $file): void
    {
        try {
            Storage::disk(self::DISK)->delete($file->path);
        } catch (Throwable $e) {
            Log::warning('S3 backup delete failed', ['path' => $file->path, 'error' => $e->getMessage()]);
        }

        $file->delete();
    }

    public function testConnection(): bool
    {
        try {
            Storage::disk(self::DISK)->exists('_ping.txt');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function assertConfigured(): void
    {
        if (empty(config('filesystems.disks.backup-s3.key'))
            || empty(config('filesystems.disks.backup-s3.bucket'))) {
            throw new ProvisioningException(
                'S3 backup provider not configured — set S3_BACKUP_KEY and S3_BACKUP_BUCKET.',
                driver: 's3_backups',
                retryable: false,
            );
        }
    }
}
