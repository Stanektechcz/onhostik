<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\CustomIso;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Files\UploadGuard;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * The organization's own installation images (TASK-0110, owner decision G-R5). An upload is taken in two halves:
 *
 *  1. `stage()` (the HTTP request, before the bus): the plan allows it, the server runs, the scanner passed its self-test, the
 *     file is no larger than the plan's size and is an ISO 9660 image. Then, under a lock on the organization, a **staging row**
 *     reserves its bytes against the quota and counts against the uploads in flight (review H1: two big uploads at once could
 *     both pass the quota, and a burst of uploads filled the disk before any of them was counted). Only then is the file copied to
 *     `incoming/<id>.part` and scanned: an infected file is deleted and reported (`files.infected`), a file nobody could scan is
 *     refused with 503 and deleted, never kept "to scan later". Whatever goes wrong after the reservation — a refusal, a full
 *     disk, a crash of the scanner — the row and the files are removed before the error leaves (review H2).
 *  2. `store()` (UploadCustomIsoCommand, the bus): the staging row of this organization and server, scanned clean, becomes the
 *     image; the same file a second time is the image it already is ("already exists" is success).
 *
 * What a process that died left behind (a staging row, an `incoming/` file) is removed by `sweep()` (`onhost:isos:sweep`, hourly).
 * The files live on the `custom_isos` disk (`ONHOST_CUSTOM_ISO_ROOT`, a dedicated mount): never under the web root — the disk
 * refuses to work if it is configured there.
 */
final class CustomIsoLibrary
{
    public const INCOMING = 'incoming';

    public function __construct(
        private readonly IsoScanner $scanner,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public function disk(): Filesystem
    {
        $name = (string) config('onhost.custom_iso.disk', 'custom_isos');
        $root = (string) config("filesystems.disks.{$name}.root", '');
        $public = rtrim(str_replace('\\', '/', (string) realpath(public_path()) ?: public_path()), '/');
        $resolved = rtrim(str_replace('\\', '/', (string) (realpath($root) ?: $root)), '/');
        if ($root === '' || ($public !== '' && ($resolved === $public || str_starts_with($resolved.'/', $public.'/')))) {
            throw new DomainError('custom_iso_storage_unsafe', 'Úložiště vlastních ISO není nastavené mimo veřejný adresář webu; nahrávání je vypnuté.', 503);
        }

        return Storage::disk($name);
    }

    /**
     * The first half of an upload (see the class comment). Returns the token the command carries (the staging row's id) and the
     * image's SHA-256 (the request's key: two different files are two uploads, the same file twice is one).
     *
     * @return array{token:string, sha256:string}
     */
    public function stage(Service $service, UploadedFile $file, CommandContext $context): array
    {
        CustomIsoPolicy::assertInPlan($service);
        CustomIsoPolicy::assertActive($service);
        $check = $this->scanner->selfTest();
        if (! $check['ok']) {
            throw new DomainError('iso_scanner_untrusted', 'Antivirová kontrola teď neprošla vlastní zkouškou, a bez ní vlastní ISO nepřijímáme. Zkuste to později.', 503, ['field' => 'file', 'retryable' => true]);
        }
        $real = (string) $file->getRealPath();
        $size = $real !== '' && is_file($real) ? (int) filesize($real) : 0; // what arrived, never what the browser claims
        $max = CustomIsoPolicy::maxBytes($service);
        if ($size > $max) {
            throw new DomainError('iso_too_large', 'Obraz je větší, než tarif dovoluje ('.self::mb($max).' MB).', 422, ['field' => 'file', 'max_bytes' => $max, 'size_bytes' => $size]);
        }
        if (! self::isIsoFile($real, $size)) {
            throw new DomainError('iso_not_iso9660', 'Soubor není obraz disku ISO 9660.', 422, ['field' => 'file']);
        }
        $sha256 = (string) hash_file('sha256', $real);
        $name = CustomIsoPolicy::displayName((string) $file->getClientOriginalName());
        $row = $this->reserve($service, $size, $sha256, $name, $context);
        try {
            $this->copyAndScan($service, $row, $real, $context);
        } catch (Throwable $e) {
            $this->drop($row); // the reservation, the .part — nothing of a refused or broken upload stays (review H2)
            throw $e;
        }

        return ['token' => $row->id, 'sha256' => $sha256];
    }

    /**
     * The second half of an upload (UploadCustomIsoCommand): this organization's and server's staging row, scanned clean, becomes the
     * image. The reservation already holds its room; the lock keeps a concurrent upload's view of the quota consistent.
     */
    public function store(Service $service, string $token, CommandContext $context): CustomIso
    {
        CustomIsoPolicy::assertInPlan($service);
        Organization::query()->whereKey($service->organization_id)->lockForUpdate()->first(); // one upload of an organization at a time
        $row = preg_match(CustomIsoPolicy::ID_PATTERN, $token) === 1
            ? CustomIso::query()->where('organization_id', $service->organization_id)->where('state', CustomIso::STAGING)
                ->where('uploaded_via_service_id', $service->id)->where('scan_result', IsoScanner::CLEAN)->lockForUpdate()->find($token)
            : null;
        if ($row === null) {
            throw new DomainError('iso_upload_unknown', 'Nahraný soubor nebyl nalezen; nahrajte ho znovu.', 422, ['field' => 'file']);
        }
        $same = CustomIso::reachableFrom($service)->where('sha256', $row->sha256)->first();
        if ($same !== null) {
            $this->drop($row);

            return $same; // the same file again is the image it already is: no second copy, no second share of the quota
        }
        $path = $service->organization_id.'/'.$row->id.'.iso';
        $this->disk()->move((string) $row->path, $path);
        $row->forceFill(['state' => CustomIso::READY, 'path' => $path, 'node_copies' => []])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.iso.upload', 'succeeded', ['iso_id' => $row->id, 'name' => $row->name, 'size_bytes' => $row->size_bytes], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.iso.uploaded', 'service', $service->id, ['iso_id' => $row->id, 'name' => $row->name, 'size_bytes' => $row->size_bytes, 'label' => (string) ($service->label ?: $service->name)], $service->organization_id));

        return $row;
    }

    /** Removes what an upload left staged (a refusal after staging, a replayed request). Never touches a kept image. */
    public function discard(string $token): void
    {
        if (preg_match(CustomIsoPolicy::ID_PATTERN, $token) !== 1) {
            return;
        }
        $row = CustomIso::query()->where('state', CustomIso::STAGING)->find($token);
        if ($row !== null) {
            $this->drop($row);
        }
    }

    /**
     * What dead uploads left behind (review H2): staging rows older than `$hours` with their files, and files in `incoming/` older
     * than that which no staging row claims — in folders under it too (H1). `onhost:isos:sweep`, hourly.
     *
     * H1 (TASK-0121): and `orphans` — an image file outside `incoming/` that no kept image (READY) or upload in flight (STAGING)
     * claims: the row is gone or DELETED (a delete whose file removal failed, a database restored from before the upload). The
     * same age window protects a file an upload may be moving right now. A kept image's file is never touched.
     *
     * @return array{rows:int, files:int, orphans:int}
     */
    public function sweep(?int $hours = null): array
    {
        $hours = max(1, $hours ?? (int) config('onhost.custom_iso.staging_hours', 6));
        $cutoff = now()->subHours($hours);
        $stats = ['rows' => 0, 'files' => 0, 'orphans' => 0];
        foreach (CustomIso::query()->where('state', CustomIso::STAGING)->where('created_at', '<', $cutoff)->get() as $row) {
            $this->drop($row);
            $stats['rows']++;
        }
        $disk = $this->disk();
        $live = CustomIso::query()->where('state', CustomIso::STAGING)->pluck('id')->all();
        foreach ($disk->allFiles(self::INCOMING) as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($id, $live, true) || $disk->lastModified($file) >= $cutoff->getTimestamp()) {
                continue;
            }
            $disk->delete($file);
            $stats['files']++;
        }
        $claimed = array_flip(CustomIso::query()->whereIn('state', [CustomIso::READY, CustomIso::STAGING])->pluck('path')->map(fn ($p) => (string) $p)->all());
        foreach ($disk->directories() as $folder) {
            if (preg_match('/^org_[0-9a-z]+$/', (string) $folder) !== 1) {
                continue; // only an organization's folder (store() writes <organization>/<id>.iso) — never incoming/, lost+found or the operator's
            }
            foreach ($disk->allFiles($folder) as $file) {
                if (isset($claimed[$file]) || $disk->lastModified($file) >= $cutoff->getTimestamp()) {
                    continue;
                }
                $disk->delete($file);
                $stats['orphans']++;
            }
        }

        return $stats;
    }

    /** What the organization holds and has reserved: kept images and uploads in flight. @return array{used_bytes:int, images:int} */
    public static function usage(string $organizationId, ?string $except = null): array
    {
        $rows = CustomIso::query()->where('organization_id', $organizationId)->whereIn('state', [CustomIso::READY, CustomIso::STAGING])
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except));

        return ['used_bytes' => (int) (clone $rows)->sum('size_bytes'), 'images' => (int) (clone $rows)->count()];
    }

    /** The library as the customer sees it through one server. @return array<string,mixed> */
    public static function listing(Service $service): array
    {
        $usage = self::usage((string) $service->organization_id);
        $images = CustomIso::reachableFrom($service)->orderBy('created_at')->orderBy('id')->get(); // the quota is the organization's, the list the project's

        return [
            'offered' => CustomIsoPolicy::inPlan($service),
            'max_bytes' => CustomIsoPolicy::inPlan($service) ? CustomIsoPolicy::maxBytes($service) : null,
            'quota' => ['used_bytes' => $usage['used_bytes'], 'quota_bytes' => CustomIsoPolicy::quotaBytes(), 'images' => $usage['images'], 'max_images' => CustomIsoPolicy::maxImages()],
            'images' => $images->map(fn (CustomIso $iso) => self::present($iso, $service))->values()->all(),
        ];
    }

    /** One image for the customer: no path, no storage, no node — those are the platform's (VendorNeutralityTest). @return array<string,mixed> */
    public static function present(CustomIso $iso, ?Service $through = null): array
    {
        return [
            'id' => $iso->id, 'name' => $iso->name, 'size_bytes' => $iso->size_bytes, 'sha256' => $iso->sha256, 'scan' => $iso->scan_result,
            'attached_service_id' => $iso->attached_service_id, 'attached_here' => $through !== null && $iso->attached_service_id === $through->id,
            'attached_at' => $iso->attached_at?->toIso8601String(), 'uploaded_at' => $iso->created_at?->toIso8601String(),
        ];
    }

    /**
     * The reservation (review H1): under a lock on the organization, the uploads in flight and the quota — kept images and
     * reservations together — are counted, and a staging row takes this upload's bytes before a single byte is copied.
     */
    private function reserve(Service $service, int $size, string $sha256, string $name, CommandContext $context): CustomIso
    {
        return DB::transaction(function () use ($service, $size, $sha256, $name, $context): CustomIso {
            Organization::query()->whereKey($service->organization_id)->lockForUpdate()->first();
            $inFlight = CustomIso::query()->where('organization_id', $service->organization_id)->where('state', CustomIso::STAGING)
                ->where('created_at', '>=', now()->subHours(max(1, (int) config('onhost.custom_iso.staging_hours', 6))))->count();
            $limit = max(1, (int) config('onhost.custom_iso.org_max_inflight', 2));
            if ($inFlight >= $limit) {
                throw new DomainError('iso_upload_in_progress', "Organizace právě nahrává {$inFlight} obraz(y); počkejte, až doběhnou.", 429, ['in_flight' => $inFlight, 'limit' => $limit, 'retry_after' => 60]);
            }
            $this->assertRoom($service, $size, $sha256);
            $row = new CustomIso;
            $row->id = $row->newUniqueId();
            $row->forceFill([
                'organization_id' => $service->organization_id, 'project_id' => $service->project_id, 'uploaded_via_service_id' => $service->id, 'uploaded_by' => $context->actorId,
                'name' => $name, 'path' => self::INCOMING.'/'.$row->id.'.part', 'size_bytes' => $size, 'sha256' => $sha256,
                'scan_result' => 'pending', 'state' => CustomIso::STAGING, 'node_copies' => [],
            ])->save();

            return $row;
        });
    }

    private function copyAndScan(Service $service, CustomIso $row, string $real, CommandContext $context): void
    {
        $disk = $this->disk();
        $source = fopen($real, 'rb');
        if ($source === false) {
            throw new DomainError('iso_upload_unknown', 'Nahraný soubor nebyl nalezen; nahrajte ho znovu.', 422, ['field' => 'file']);
        }
        try {
            $disk->writeStream((string) $row->path, $source);
        } finally {
            self::close($source);
        }
        $stream = $disk->readStream((string) $row->path);
        try {
            $scan = is_resource($stream) ? $this->scanner->scan($stream) : ['result' => IsoScanner::UNAVAILABLE, 'signature' => null];
        } finally {
            self::close($stream);
        }
        if ($scan['result'] !== IsoScanner::CLEAN) {
            $this->refuse($scan, $service, (string) $row->name, $context);
        }
        $row->forceFill(['scan_result' => IsoScanner::CLEAN, 'scanned_at' => now()])->save();
    }

    /** A staging row and whatever it staged. */
    private function drop(CustomIso $row): void
    {
        try {
            $disk = $this->disk();
            $disk->delete([self::INCOMING.'/'.$row->id.'.part', self::INCOMING.'/'.$row->id.'.json']);
        } finally {
            if ($row->state === CustomIso::STAGING) {
                $row->delete();
            }
        }
    }

    /** The organization's quota: all its images and reservations together, and how many. The same file again needs no room. */
    private function assertRoom(Service $service, int $size, string $sha256): void
    {
        if (CustomIso::reachableFrom($service)->where('sha256', $sha256)->exists()) {
            return;
        }
        $usage = self::usage((string) $service->organization_id);
        $quota = CustomIsoPolicy::quotaBytes();
        $max = CustomIsoPolicy::maxImages();
        if ($usage['images'] >= $max || $quota < $usage['used_bytes'] + $size) {
            throw new DomainError('iso_quota_exceeded', 'Organizace má vyčerpané místo pro vlastní ISO ('.$usage['images'].'/'.$max.' obrazů, '.self::mb($usage['used_bytes']).'/'.self::mb($quota).' MB); nejdřív některý smažte.', 422, [
                'field' => 'file', 'used_bytes' => $usage['used_bytes'], 'quota_bytes' => $quota, 'images' => $usage['images'], 'max_images' => $max,
            ]);
        }
    }

    /** @param array{result:string, signature:?string} $scan */
    private function refuse(array $scan, Service $service, string $name, CommandContext $context): never
    {
        if ($scan['result'] === IsoScanner::INFECTED) { // audited, reported to security, deleted (audit §5r-4)
            UploadGuard::refused(['result' => VirusScanner::INFECTED, 'signature' => $scan['signature'], 'at' => now()->toIso8601String()], 'service', $service->id, $name, $context->withScope($service->organization_id));
        }
        $this->audit->record($context->withScope($service->organization_id), 'service.iso.upload', 'denied', ['name' => $name, 'scan' => $scan['result']], 'service', $service->id);
        if ($scan['result'] === IsoScanner::INCOMPLETE) {
            throw new DomainError('iso_scan_incomplete', 'Antivirová kontrola obraz neprošla celý (je příliš velký nebo složitý); soubor nebyl přijat.', 422, ['field' => 'file']);
        }
        if ($scan['result'] === IsoScanner::TOO_LARGE) { // the file, not an outage: retrying does not help (review L1)
            throw new DomainError('iso_too_large_for_scan', 'Obraz je větší, než antivirová kontrola dokáže přečíst; soubor nebyl přijat.', 422, ['field' => 'file']);
        }

        throw new DomainError('iso_scan_unavailable', 'Antivirová kontrola teď není dostupná, a bez ní vlastní ISO nepřijímáme. Soubor nebyl uložen; zkuste to později.', 503, ['field' => 'file', 'retryable' => true]);
    }

    private static function isIsoFile(string $real, int $size): bool
    {
        $in = $size > 0 ? fopen($real, 'rb') : false;
        if ($in === false) {
            return false;
        }
        try {
            return CustomIsoPolicy::looksLikeIso($in);
        } finally {
            fclose($in);
        }
    }

    /** A stream the filesystem or the HTTP client may already have closed is closed only while it is open. */
    private static function close(mixed $stream): void
    {
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private static function mb(int $bytes): int
    {
        return (int) floor($bytes / 1048576);
    }
}
