<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

/**
 * The organization's own installation images (TASK-0110, owner decision G-R5). An upload is taken in two halves:
 *
 *  1. `stage()` (the HTTP request, before the bus): the plan allows it, the file is no larger than the plan's size, it is an
 *     ISO 9660 image, and clamd found it clean — an infected file is deleted and reported (`files.infected`), a file nobody could
 *     scan is refused with 503 and deleted, never kept "to scan later". What passed is written to `incoming/` with a sidecar the
 *     platform signs (app key): only `stage()` writes there, so the second half cannot be handed a file that skipped the scan.
 *  2. `store()` (UploadCustomIsoCommand, the bus): the organization's quota under a row lock (two uploads at once cannot both
 *     squeeze in), the same file a second time is the image it already is ("already exists" is success), then the file moves to
 *     `<organization>/<id>.iso` and the row is written, audited and announced.
 *
 * The files live on the `custom_isos` disk (`ONHOST_CUSTOM_ISO_ROOT`, a dedicated mount): never under the web root — the disk
 * refuses to work if it is configured there.
 */
final class CustomIsoLibrary
{
    public const INCOMING = 'incoming';

    private const TOKEN = '/^[a-z0-9]{40}$/';

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
     * The first half of an upload (see the class comment). Returns the token the command carries and the image's SHA-256 (the
     * request's key: two different files are two uploads, the same file twice is one).
     *
     * @return array{token:string, sha256:string}
     */
    public function stage(Service $service, UploadedFile $file, CommandContext $context): array
    {
        CustomIsoPolicy::assertInPlan($service);
        $real = (string) $file->getRealPath();
        $size = $real !== '' && is_file($real) ? (int) filesize($real) : 0; // what arrived, never what the browser claims
        $max = CustomIsoPolicy::maxBytes($service);
        if ($size > $max) {
            throw new DomainError('iso_too_large', 'Obraz je větší, než tarif dovoluje ('.self::mb($max).' MB).', 422, ['field' => 'file', 'max_bytes' => $max, 'size_bytes' => $size]);
        }
        $in = $size > 0 ? fopen($real, 'rb') : false;
        if ($in === false) {
            throw new DomainError('iso_not_iso9660', 'Soubor není obraz disku ISO 9660.', 422, ['field' => 'file']);
        }
        try {
            if (! CustomIsoPolicy::looksLikeIso($in)) {
                throw new DomainError('iso_not_iso9660', 'Soubor není obraz disku ISO 9660.', 422, ['field' => 'file']);
            }
        } finally {
            fclose($in);
        }
        $sha256 = (string) hash_file('sha256', $real);
        $this->assertRoom($service, $size, $sha256); // a first look before a long scan; store() asks again under the lock
        $token = Str::lower(Str::random(40));
        $disk = $this->disk();
        $part = self::INCOMING."/{$token}.part";
        $source = fopen($real, 'rb');
        try {
            $disk->writeStream($part, $source);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }
        $stream = $disk->readStream($part);
        try {
            $scan = is_resource($stream) ? $this->scanner->scan($stream) : ['result' => IsoScanner::UNAVAILABLE, 'signature' => null];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $name = CustomIsoPolicy::displayName((string) $file->getClientOriginalName());
        if ($scan['result'] !== IsoScanner::CLEAN) {
            $disk->delete($part);
            $this->refuse($scan, $service, $name, $context);
        }
        $meta = ['organization_id' => $service->organization_id, 'service_id' => $service->id, 'size_bytes' => $size, 'sha256' => $sha256, 'name' => $name, 'scan' => IsoScanner::CLEAN, 'scanned_at' => now()->toIso8601String()];
        $disk->put(self::INCOMING."/{$token}.json", (string) json_encode($meta + ['mac' => self::mac($token, $meta)]));

        return ['token' => $token, 'sha256' => $sha256];
    }

    /**
     * The second half of an upload (UploadCustomIsoCommand): quota under the organization's row lock, the same file once, the row.
     */
    public function store(Service $service, string $token, CommandContext $context): CustomIso
    {
        CustomIsoPolicy::assertInPlan($service);
        $meta = $this->staged($service, $token);
        Organization::query()->whereKey($service->organization_id)->lockForUpdate()->first(); // one upload of an organization at a time
        $same = CustomIso::reachableFrom($service)->where('sha256', $meta['sha256'])->first();
        if ($same !== null) {
            $this->discard($token);

            return $same; // the same file again is the image it already is: no second copy, no second share of the quota
        }
        try {
            $this->assertRoom($service, (int) $meta['size_bytes'], (string) $meta['sha256']);
        } catch (DomainError $e) {
            $this->discard($token);
            throw $e;
        }
        $iso = new CustomIso;
        $iso->id = $iso->newUniqueId();
        $path = $service->organization_id.'/'.$iso->id.'.iso';
        $disk = $this->disk();
        $disk->move(self::INCOMING."/{$token}.part", $path);
        $disk->delete(self::INCOMING."/{$token}.json");
        $iso->forceFill([
            'organization_id' => $service->organization_id, 'project_id' => $service->project_id, 'uploaded_via_service_id' => $service->id, 'uploaded_by' => $context->actorId,
            'name' => (string) $meta['name'], 'path' => $path, 'size_bytes' => (int) $meta['size_bytes'], 'sha256' => (string) $meta['sha256'],
            'scan_result' => IsoScanner::CLEAN, 'scanned_at' => $meta['scanned_at'], 'state' => CustomIso::READY, 'node_copies' => [],
        ])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.iso.upload', 'succeeded', ['iso_id' => $iso->id, 'name' => $iso->name, 'size_bytes' => $iso->size_bytes], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.iso.uploaded', 'service', $service->id, ['iso_id' => $iso->id, 'name' => $iso->name, 'size_bytes' => $iso->size_bytes], $service->organization_id));

        return $iso;
    }

    /** Removes what an upload left in `incoming/` (a refusal after staging, a replayed request). Safe for any token. */
    public function discard(string $token): void
    {
        if (preg_match(self::TOKEN, $token) !== 1) {
            return;
        }
        $disk = $this->disk();
        $disk->delete([self::INCOMING."/{$token}.part", self::INCOMING."/{$token}.json"]);
    }

    /** What the organization holds. @return array{used_bytes:int, images:int} */
    public static function usage(string $organizationId): array
    {
        $rows = CustomIso::query()->where('organization_id', $organizationId)->where('state', CustomIso::READY);

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

    /** @return array<string,mixed> the signed sidecar of a staged upload of this organization and server */
    private function staged(Service $service, string $token): array
    {
        if (preg_match(self::TOKEN, $token) !== 1) {
            throw new DomainError('iso_upload_unknown', 'Nahraný soubor nebyl nalezen; nahrajte ho znovu.', 422, ['field' => 'file']);
        }
        $disk = $this->disk();
        $raw = $disk->exists(self::INCOMING."/{$token}.json") ? (string) $disk->get(self::INCOMING."/{$token}.json") : '';
        $meta = json_decode($raw, true);
        $meta = is_array($meta) ? $meta : [];
        $mac = (string) ($meta['mac'] ?? '');
        unset($meta['mac']);
        if ($meta === [] || ! hash_equals(self::mac($token, $meta), $mac) || ($meta['scan'] ?? null) !== IsoScanner::CLEAN
            || ($meta['organization_id'] ?? null) !== $service->organization_id || ($meta['service_id'] ?? null) !== $service->id
            || ! $disk->exists(self::INCOMING."/{$token}.part")) {
            throw new DomainError('iso_upload_unknown', 'Nahraný soubor nebyl nalezen; nahrajte ho znovu.', 422, ['field' => 'file']);
        }

        return $meta;
    }

    /** The organization's quota: all its images together, and how many. The same file again needs no room. */
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

        throw new DomainError('iso_scan_unavailable', 'Antivirová kontrola teď není dostupná, a bez ní vlastní ISO nepřijímáme. Soubor nebyl uložen; zkuste to později.', 503, ['field' => 'file', 'retryable' => true]);
    }

    /** @param array<string,mixed> $meta */
    private static function mac(string $token, array $meta): string
    {
        ksort($meta);

        return hash_hmac('sha256', $token.'|'.json_encode($meta), (string) config('app.key'));
    }

    private static function mb(int $bytes): int
    {
        return (int) floor($bytes / 1048576);
    }
}
