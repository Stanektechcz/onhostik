<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Str;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;

/**
 * Local staging area for files travelling between the customer and a hosting node: uploads (SQL imports, site
 * archives, file-manager uploads) and downloads (dumps, backups) live under storage/app/onhost/web-tools per
 * service, addressed by random ids, and are swept after a short retention.
 */
final class WebFileStore
{
    public function root(): string
    {
        $root = storage_path('app/onhost/web-tools');
        if (! is_dir($root)) {
            mkdir($root, 0770, true);
        }

        return $root;
    }

    /** Store an uploaded file (moved, not copied) and return its id. */
    public function putUpload(Service $service, string $sourcePath, string $originalName): string
    {
        $id = 'up_'.Str::lower(Str::random(20));
        $dir = $this->dir($service, 'uploads');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $target = $dir.'/'.$id.($ext !== '' ? '.'.preg_replace('/[^a-z0-9.]/', '', $ext) : '');
        if (! @rename($sourcePath, $target) && ! @copy($sourcePath, $target)) {
            throw new DomainError('upload_failed', 'The uploaded file could not be stored.', 500);
        }
        file_put_contents($target.'.meta', json_encode(['name' => basename($originalName), 'size' => filesize($target), 'at' => now()->toIso8601String()]));

        return basename($target);
    }

    public function uploadPath(Service $service, string $uploadId): string
    {
        $path = $this->dir($service, 'uploads').'/'.basename($uploadId);
        if (! preg_match('/^up_[a-z0-9]{20}(\.[a-z0-9.]{1,12})?$/', basename($uploadId)) || ! is_file($path)) {
            throw new DomainError('upload_not_found', 'The upload does not exist any more (uploads are kept for a few hours).', 404);
        }

        return $path;
    }

    /** @return array{name:string,size:int,at:string}|null */
    public function uploadMeta(Service $service, string $uploadId): ?array
    {
        $meta = @file_get_contents($this->uploadPath($service, $uploadId).'.meta');
        $decoded = $meta === false ? null : json_decode($meta, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Reserve a download file; returns [token, absolute path]. @return array{0:string,1:string} */
    public function newDownload(Service $service, string $name): array
    {
        $token = 'dl_'.Str::lower(Str::random(24));
        $dir = $this->dir($service, 'downloads');
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'download';
        file_put_contents($dir.'/'.$token.'.meta', json_encode(['name' => $safe, 'at' => now()->toIso8601String()]));

        return [$token, $dir.'/'.$token.'.bin'];
    }

    /** @return array{path:string,name:string}|null */
    public function download(Service $service, string $token): ?array
    {
        if (! preg_match('/^dl_[a-z0-9]{24}$/', $token)) {
            return null;
        }
        $dir = $this->dir($service, 'downloads');
        if (! is_file($dir.'/'.$token.'.bin')) {
            return null;
        }
        $meta = json_decode((string) @file_get_contents($dir.'/'.$token.'.meta'), true);

        return ['path' => $dir.'/'.$token.'.bin', 'name' => (string) ($meta['name'] ?? 'download.bin')];
    }

    /** Remove uploads and downloads older than the retention window. */
    public function prune(int $hours = 6): int
    {
        $removed = 0;
        $cutoff = time() - $hours * 3600;
        foreach (glob($this->root().'/*/*/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }

    private function dir(Service $service, string $kind): string
    {
        $dir = $this->root().'/'.preg_replace('/[^A-Za-z0-9_-]/', '', $service->id).'/'.$kind;
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        return $dir;
    }
}
