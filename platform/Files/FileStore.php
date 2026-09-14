<?php

declare(strict_types=1);

namespace Onhost\Platform\Files;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Compliance\Models\DataRequest;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The one disk behind customer files (audit §5q-4): marketplace evidence and data exports live on
 * `ONHOST_FILES_DISK` — the local private disk by default, an S3-compatible bucket in production. A download is a
 * short signed link when the disk can sign (S3, MinIO, R2) and a stream otherwise, so the application server never
 * proxies large files; retention deletes evidence older than the configured months and exports past their expiry.
 */
final class FileStore
{
    public const EVIDENCE_PREFIX = 'marketplace-evidence';

    public const EXPORT_PREFIX = 'exports';

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('onhost.storage.disk', 'local') ?: 'local';
    }

    /** A signed link when the disk provides them (S3 and friends), otherwise a stream through the application. */
    public function download(string $path, string $name, string $mime, array $headers = []): Response
    {
        $disk = $this->disk();
        if ($disk instanceof FilesystemAdapter && $this->diskName() !== 'local' && $disk->providesTemporaryUrls()) {
            $url = $disk->temporaryUrl($path, now()->addMinutes(max(1, (int) config('onhost.storage.signed_ttl_minutes', 15))), [
                'ResponseContentType' => $mime, 'ResponseContentDisposition' => 'attachment; filename="'.addcslashes($name, '"\\').'"',
            ]);

            return new RedirectResponse($url, 302, ['Cache-Control' => 'no-store'] + $headers);
        }

        /** @var StreamedResponse $response */
        $response = $disk->download($path, $name, ['Content-Type' => $mime] + $headers);

        return $response;
    }

    /**
     * Retention (rule `files.prune`): evidence files older than `evidence_retention_months`, empty evidence folders,
     * export files whose request expired (the compliance service already forgets the row's path).
     *
     * @return array{evidence_deleted:int, exports_deleted:int, disk:string}
     */
    public function prune(): array
    {
        $disk = $this->disk();
        $stats = ['evidence_deleted' => 0, 'exports_deleted' => 0, 'disk' => $this->diskName()];
        $cutoff = now()->subMonths(max(1, (int) config('onhost.storage.evidence_retention_months', 36)))->getTimestamp();
        foreach ($disk->allFiles(self::EVIDENCE_PREFIX) as $file) {
            if (str_starts_with($file, self::EVIDENCE_PREFIX.'/tmp/')) {
                continue; // the upload flow prunes its own temporary files
            }
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $stats['evidence_deleted']++;
            }
        }
        $exportCutoff = now()->subDays(1)->getTimestamp();
        foreach ($disk->allFiles(self::EXPORT_PREFIX) as $file) {
            $keep = DataRequest::query()->where('file_path', $file)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->exists();
            if (! $keep && $disk->lastModified($file) < $exportCutoff) {
                $disk->delete($file);
                $stats['exports_deleted']++;
            }
        }

        return $stats;
    }
}
