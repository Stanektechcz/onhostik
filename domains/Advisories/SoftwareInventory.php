<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\PanelVersionGate;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\WordPressService;
use Throwable;

/**
 * What runs where (SN-2), the side the advisories are matched against:
 *
 *  - **WordPress** on customers' sites: core, plugins and themes with their versions, read through WP-CLI on the node
 *    (`WordPressService::status`). One shell round-trip per site, so a run takes `wordpress_per_run` sites, the longest unread first.
 *  - **Panels** the platform runs (Proxmox, PBS, ISPConfig, aaPanel, Pterodactyl, PowerDNS, Kubernetes): the version the health
 *    probe reads every minute, as a CPE product.
 *  - **The control plane itself**: the Composer libraries it ships (`composer.lock`, production packages only).
 *
 * Only services the platform provisioned are services: a historical site on a shared panel is never listed, so it is never touched
 * (owner rule 2026-09-24). Reading changes nothing anywhere.
 */
final class SoftwareInventory
{
    /** The panel's software as NVD names it (`vendor:product`). */
    public const PANEL_PRODUCTS = [
        'proxmox' => 'proxmox:virtual_environment', 'pbs' => 'proxmox:backup_server', 'ispconfig' => 'ispconfig:ispconfig', 'aapanel' => 'aapanel:aapanel',
        'pterodactyl' => 'pterodactyl:panel', 'powerdns' => 'powerdns:authoritative', 'kubernetes' => 'kubernetes:kubernetes',
    ];

    public function __construct(private readonly WordPressService $wordpress) {}

    /** @return array{sites:int, wordpress:int, panels:int, platform:int, errors:int} */
    public function refresh(): array
    {
        [$sites, $wp, $errors] = $this->wordpressSites();

        return ['sites' => $sites, 'wordpress' => $wp, 'panels' => $this->panels(), 'platform' => $this->platform(), 'errors' => $errors];
    }

    /** @return array{0:int, 1:int, 2:int} sites read, WordPress items written, errors */
    public function wordpressSites(?int $limit = null): array
    {
        $limit ??= max(1, (int) config('onhost.advisories.wordpress_per_run', 200));
        $services = Service::query()->where('state', ServiceStateMachine::ACTIVE)->where('family', 'web')->get()
            ->sortBy(fn (Service $s) => (int) Cache::get("advisories:wp-read:{$s->id}", 0))->take($limit);
        $read = 0;
        $items = 0;
        $errors = 0;
        foreach ($services as $service) {
            try {
                $status = $this->wordpress->status($service, true);
            } catch (Throwable) {
                $errors++;

                continue;
            }
            Cache::put("advisories:wp-read:{$service->id}", now()->getTimestamp(), now()->addDays(30));
            $read++;
            if (($status['installed'] ?? null) === null && ($status['available'] ?? false)) {
                continue; // the panel's agent user is still being prepared: what we knew stays
            }
            $seen = [];
            if (($status['installed'] ?? false) === true && is_string($status['version'] ?? null) && $status['version'] !== '') {
                $seen[] = $this->put($service, 'wordpress-core', 'wordpress', (string) $status['version'], 'WordPress', []);
                foreach (['plugins' => 'wordpress-plugin', 'themes' => 'wordpress-theme'] as $list => $ecosystem) {
                    foreach ((array) ($status[$list] ?? []) as $entry) {
                        $slug = strtolower((string) ($entry['name'] ?? ''));
                        $version = (string) ($entry['version'] ?? '');
                        if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,159}$/', $slug) === 1 && AdvisoryVersion::normalize($version) !== '') {
                            $seen[] = $this->put($service, $ecosystem, $slug, $version, mb_substr((string) ($entry['title'] ?? $slug), 0, 120), ['status' => (string) ($entry['status'] ?? '')]);
                        }
                    }
                }
            }
            $items += count($seen);
            InventoryItem::query()->where('subject_type', InventoryItem::SERVICE)->where('subject_id', $service->id)->where('ecosystem', 'like', 'wordpress-%')->whereNotIn('id', $seen)->delete();
        }
        // services that ended take their software with them
        InventoryItem::query()->where('subject_type', InventoryItem::SERVICE)
            ->whereNotIn('subject_id', Service::query()->where('state', ServiceStateMachine::ACTIVE)->select('id'))->delete();

        return [$read, $items, $errors];
    }

    public function panels(): int
    {
        $seen = [];
        foreach (ProviderInstance::query()->whereIn('provider', PanelVersionGate::SELF_HOSTED)->where('state', '!=', 'disabled')->get() as $instance) {
            $version = (string) ($instance->vendor_version ?? '');
            $product = self::PANEL_PRODUCTS[$instance->provider] ?? null;
            if ($product === null || AdvisoryVersion::normalize($version) === '') {
                continue;
            }
            $seen[] = InventoryItem::query()->updateOrCreate(
                ['subject_type' => InventoryItem::INSTANCE, 'subject_id' => $instance->id, 'ecosystem' => 'cpe', 'product' => $product],
                ['organization_id' => null, 'version' => mb_substr($version, 0, 60), 'label' => mb_substr((string) $instance->name, 0, 200), 'meta' => ['provider' => $instance->provider], 'seen_at' => now()],
            )->id;
        }
        InventoryItem::query()->where('subject_type', InventoryItem::INSTANCE)->whereNotIn('id', $seen)->delete();

        return count($seen);
    }

    public function platform(): int
    {
        $lock = json_decode((string) @file_get_contents(base_path('composer.lock')), true);
        $seen = [];
        foreach ((array) ($lock['packages'] ?? []) as $package) {
            $name = strtolower((string) ($package['name'] ?? ''));
            $version = ltrim((string) ($package['version'] ?? ''), 'v');
            if (preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#', $name) !== 1 || AdvisoryVersion::normalize($version) === '') {
                continue;
            }
            $seen[] = InventoryItem::query()->updateOrCreate(
                ['subject_type' => InventoryItem::PLATFORM, 'subject_id' => '', 'ecosystem' => 'Packagist', 'product' => $name],
                ['organization_id' => null, 'version' => mb_substr($version, 0, 60), 'label' => 'Control plane · '.$name, 'meta' => null, 'seen_at' => now()],
            )->id;
        }
        InventoryItem::query()->where('subject_type', InventoryItem::PLATFORM)->whereNotIn('id', $seen)->delete();

        return count($seen);
    }

    /** @param array<string,mixed> $meta */
    private function put(Service $service, string $ecosystem, string $product, string $version, string $name, array $meta): string
    {
        $site = (string) ($service->hostname ?: $service->label ?: $service->name);

        return InventoryItem::query()->updateOrCreate(
            ['subject_type' => InventoryItem::SERVICE, 'subject_id' => $service->id, 'ecosystem' => $ecosystem, 'product' => $product],
            ['organization_id' => $service->organization_id, 'version' => mb_substr($version, 0, 60), 'label' => mb_substr($site.' · '.$name, 0, 200), 'meta' => $meta + ['name' => $name, 'site' => $site], 'seen_at' => now()],
        )->id;
    }
}
