<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\CustomIso\CustomIsoReadiness;
use Onhost\Domain\Services\CustomIso\IsoScanner;
use Onhost\Platform\Commands\CommandContext;

/*
 * Owner decision I-R9 (2026-10-08): the catalogue revision `2026-10-custom-iso` runs only after the rehearsal steps R15–R17 are
 * green (go-live checklist G-4 clamd, G-5 the image volume, G-7 the Proxmox ISO storage) — run earlier, a customer would buy a
 * feature whose every upload is refused. The order used to be a line in a runbook; now `--apply` (and CatalogRevisions::apply
 * itself) refuses while any of the three is not ready, names what is missing, and publishes nothing. The dry run still shows
 * the plans, and says what is not ready.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class]);
    Http::preventStrayRequests();
    config(['onhost.custom_iso.org_quota_mb' => 1]); // the test disk always has room for one MiB
});

function irgScanner(bool $ok): void
{
    app()->instance(IsoScanner::class, new class($ok) implements IsoScanner
    {
        public function __construct(private readonly bool $ok) {}

        public function scan($stream): array
        {
            return ['result' => self::UNAVAILABLE, 'signature' => null];
        }

        public function selfTest(): array
        {
            return ['ok' => $this->ok, 'detail' => $this->ok ? 'fake: EICAR found' : 'fake: clamd not reachable'];
        }
    });
}

/** The lab Proxmox instance, with or without a storage for customers' images. */
function irgProxmox(bool $isoStorage): void
{
    $instance = pveLab();
    $options = array_diff_key((array) $instance->options, ['custom_iso_storage' => true]);
    $instance->forceFill(['options' => $isoStorage ? $options + ['custom_iso_storage' => 'isostore'] : $options])->save();
    app(ProviderRegistry::class)->forget($instance); // the registry keeps an adapter per instance; a changed option needs a new one
}

function irgVersion(string $product, string $plan): int
{
    return (int) Plan::query()->whereHas('product', fn ($q) => $q->where('key', $product))->where('key', $plan)->value('current_version');
}

it('refuses to publish the custom ISO plans before clamd, the image volume and the Proxmox ISO storage are ready', function () {
    irgScanner(false);
    irgProxmox(false);

    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-custom-iso', '--apply' => true, '--yes' => true])->assertFailed()
        ->expectsOutputToContain('not ready (I-R9): R15 clamd (G-4): fake: clamd not reachable')
        ->expectsOutputToContain('not ready (I-R9): R17 Proxmox ISO storage (G-7): no custom_iso_storage on proxmox-cz1')
        ->expectsOutputToContain('Nothing was published');

    expect(irgVersion('vps', 'compute-4'))->toBe(1)->and(irgVersion('vds', 'vds-16'))->toBe(1)
        ->and(app(CatalogRevisions::class)->pending('2026-10-custom-iso'))->toHaveKey('2026-10-custom-iso');
});

it('still previews the plans and says what is not ready', function () {
    irgScanner(false);
    irgProxmox(true);

    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-custom-iso'])->assertSuccessful()
        ->expectsOutputToContain('vps/compute-4 v1 → v2: custom_iso null → true; custom_iso_max_mb null → 4096')
        ->expectsOutputToContain('not ready (I-R9): R15 clamd (G-4)');
});

it('names each missing step on its own', function () {
    irgScanner(true);
    irgProxmox(false);
    expect(app(CustomIsoReadiness::class)->unmet())->toHaveCount(1)
        ->and(app(CustomIsoReadiness::class)->unmet()[0])->toContain('R17 Proxmox ISO storage (G-7)')->toContain('custom_iso_storage');

    irgProxmox(true);
    config(['onhost.custom_iso.org_quota_mb' => 100_000_000]); // more than any disk holds
    expect(app(CustomIsoReadiness::class)->unmet())->toHaveCount(1)
        ->and(app(CustomIsoReadiness::class)->unmet()[0])->toContain('R16 image volume (G-5)')->toContain('free');

    config(['onhost.custom_iso.org_quota_mb' => 1, 'filesystems.disks.custom_isos.root' => public_path('isos')]);
    expect(app(CustomIsoReadiness::class)->unmet()[0])->toContain('R16 image volume (G-5)')->toContain('web root');
});

it('counts every usable Proxmox instance: one without the ISO storage is not ready', function () {
    irgScanner(true);
    irgProxmox(true);
    $second = pveLab()->replicate();
    $second->forceFill(['key' => 'proxmox-cz2', 'options' => ['default_node' => 'prg2-n1', 'storage' => 'local-zfs']])->save();

    expect(app(CustomIsoReadiness::class)->unmet())->toHaveCount(1)
        ->and(app(CustomIsoReadiness::class)->unmet()[0])->toContain('proxmox-cz2');

    $second->forceFill(['state' => 'disabled'])->save(); // an instance nobody may call takes no order either
    expect(app(CustomIsoReadiness::class)->unmet())->toBe([]);
});

it('gates the domain apply itself, not only the console', function () {
    irgScanner(false);
    irgProxmox(true);

    $done = app(CatalogRevisions::class)->apply('2026-10-custom-iso', CommandContext::system('test:i-r9'));

    expect($done)->toHaveCount(1)->and($done[0]['kind'])->toBe('gate')->and($done[0]['error'])->toStartWith('revision_not_ready')
        ->and(irgVersion('vps', 'compute-4'))->toBe(1);
});

it('publishes the custom ISO plans once R15–R17 are green', function () {
    irgScanner(true);
    irgProxmox(true);

    expect(app(CatalogRevisions::class)->blockers('2026-10-custom-iso'))->toBe([]);
    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-custom-iso', '--apply' => true, '--yes' => true])->assertSuccessful();

    expect(irgVersion('vps', 'compute-4'))->toBe(2)->and(irgVersion('vds', 'vds-16'))->toBe(2)
        ->and(app(CatalogRevisions::class)->pending('2026-10-custom-iso'))->toBe([]);
});

it('leaves revisions without a requirement alone', function () {
    irgScanner(false);

    expect(app(CatalogRevisions::class)->blockers('2026-09-honest-promises'))->toBe([])
        ->and(app(CatalogRevisions::class)->blockers('2026-10-penpot-on-sale'))->toBe([]);
});
