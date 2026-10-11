<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\DomainError;
use Throwable;

/**
 * Whether the installation can deliver a custom ISO it would sell (owner decision I-R9, 2026-10-08).
 *
 * The catalogue revision `2026-10-custom-iso` is published only after the rehearsal steps R15–R17 are green — go-live checklist
 * G-4 (clamd), G-5 (the image volume) and G-7 (the Proxmox storage for customers' images). Published earlier, a customer buys a
 * feature whose every upload is refused (503 `iso_scanner_untrusted` / `custom_iso_storage_unsafe`) or that the server does not
 * offer (`reason: node`). `CatalogRevisions` asks this class before it publishes the revision (`requires: custom_iso`).
 *
 * Three questions, the same ones the feature itself asks on every upload:
 *  - R15: the virus scan passes its self-test (EICAR found, a file beyond clamd's limits reported) — `IsoScanner::selfTest`;
 *  - R16: the image disk is outside the web root (`CustomIsoLibrary::disk`) and has room for one organization's quota;
 *  - R17: every usable Proxmox instance the platform may call has a storage for customers' images (`custom_iso_storage`), and
 *    there is at least one — a plan sold in a region whose instance has none would answer `reason: node`.
 *
 * What it cannot see stays a step by hand: the PHP and nginx upload limits (R16, G-6) and the smoke upload (R19).
 * Read-only: no call leaves the host (the self-test talks to clamd only, and caches its answer for minutes).
 */
final class CustomIsoReadiness
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    /** @return list<array{step: string, ok: bool, detail: string}> */
    public function checks(): array
    {
        return [$this->scanner(), $this->storage(), $this->hypervisor()];
    }

    /** What is not ready, one line per step; empty when the revision may be published. @return list<string> */
    public function unmet(): array
    {
        $unmet = [];
        foreach ($this->checks() as $check) {
            if (! $check['ok']) {
                $unmet[] = $check['step'].': '.$check['detail'];
            }
        }

        return $unmet;
    }

    /** Free bytes on the volume that holds `$path` (its nearest existing parent while the directory does not exist); null when unknown. */
    public static function freeBytes(string $path): ?int
    {
        while ($path !== '' && ! is_dir($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return null;
            }
            $path = $parent;
        }
        $free = $path === '' ? false : @disk_free_space($path);

        return $free === false ? null : (int) $free;
    }

    /** @return array{step: string, ok: bool, detail: string} */
    private function scanner(): array
    {
        $step = 'R15 clamd (G-4)';
        try {
            $test = app(IsoScanner::class)->selfTest();
        } catch (Throwable $e) {
            $test = ['ok' => false, 'detail' => 'the self-test could not run ('.class_basename($e).')'];
        }
        $ok = (bool) ($test['ok'] ?? false);

        return ['step' => $step, 'ok' => $ok, 'detail' => $ok ? 'the virus scan passes its self-test'
            : (string) ($test['detail'] ?? 'the self-test failed').' — php artisan onhost:isos:scanner-check --fresh (docs/runbooks/custom-iso.md, step 2)'];
    }

    /** @return array{step: string, ok: bool, detail: string} */
    private function storage(): array
    {
        $step = 'R16 image volume (G-5)';
        try {
            app(CustomIsoLibrary::class)->disk();
        } catch (DomainError) {
            return ['step' => $step, 'ok' => false, 'detail' => 'ONHOST_CUSTOM_ISO_ROOT is empty or inside the web root (docs/runbooks/custom-iso.md, step 1)'];
        }
        $free = self::freeBytes((string) config('filesystems.disks.'.config('onhost.custom_iso.disk', 'custom_isos').'.root', ''));
        if ($free === null) {
            return ['step' => $step, 'ok' => false, 'detail' => 'the free space of ONHOST_CUSTOM_ISO_ROOT cannot be read'];
        }
        $quota = CustomIsoPolicy::quotaBytes();
        $gib = static fn (int $bytes): string => number_format($bytes / 1073741824, 1, '.', '').' GiB';

        return ['step' => $step, 'ok' => $free >= $quota, 'detail' => $gib($free).' free, one organization may hold '.$gib($quota)
            .($free >= $quota ? '' : ' — grow the volume or lower ONHOST_CUSTOM_ISO_ORG_QUOTA_MB (docs/runbooks/custom-iso.md, step 1)')];
    }

    /** @return array{step: string, ok: bool, detail: string} */
    private function hypervisor(): array
    {
        $step = 'R17 Proxmox ISO storage (G-7)';
        $ready = [];
        $missing = [];
        $instances = ProviderInstance::query()->platform()->allowedToCall()->where('provider', 'proxmox')->orderBy('key')->get();
        foreach ($instances as $instance) {
            if (! $instance->isUsable()) {
                continue; // takes no new order (maintenance, a held panel version): nothing is sold onto it
            }
            try {
                $adapter = $this->providers->forInstance($instance);
            } catch (Throwable) {
                $adapter = null;
            }
            if (CustomIsoPolicy::nodeReady($adapter)) {
                $ready[] = (string) $instance->key;
            } else {
                $missing[] = (string) $instance->key;
            }
        }
        if ($ready === [] && $missing === []) {
            return ['step' => $step, 'ok' => false, 'detail' => 'no usable Proxmox instance'];
        }
        if ($missing !== []) {
            return ['step' => $step, 'ok' => false, 'detail' => 'no custom_iso_storage on '.implode(', ', $missing)
                .' — set the instance option custom_iso_storage (Nastavení systému → Integrace providerů; docs/runbooks/custom-iso.md, step 4)'];
        }

        return ['step' => $step, 'ok' => true, 'detail' => 'custom_iso_storage on '.implode(', ', $ready)];
    }
}
