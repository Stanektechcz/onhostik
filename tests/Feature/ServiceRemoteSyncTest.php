<?php

declare(strict_types=1);

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\ServiceSyncState;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceRemoteSyncService;
use App\Notifications\ServiceSyncDriftNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Reconciling a Service against aaPanel — the check that catches "the
 * customer paid, we marked it Active, but the site was never created".
 *
 * The sync is strictly read-only: it must never need (or use) the
 * AAPANEL_ALLOW_REAL_WRITES gate.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Put the aaPanel integration into a real (non-mock) read-capable state. */
function liveAapanelSetting(): IntegrationSetting
{
    $setting = IntegrationSetting::firstOrCreate(
        ['provider' => 'aapanel'],
        ['label' => 'AAPanel (webhosting)'],
    );

    $setting->forceFill([
        'is_active'   => true,
        'mock_mode'   => false,
        'dry_run'     => false,
        'credentials' => ['base_url' => 'https://panel.test', 'api_key' => 'k'],
    ])->save();

    return $setting;
}

function aapanelService(ServiceStatus $status, string $label = 'zakaznik.cz', ?string $externalId = null): Service
{
    return Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => $status,
        'label'               => $label,
        'external_id'         => $externalId,
    ]);
}

it('refuses to claim a service is in sync while the integration is in mock mode', function (): void {
    // Mock mode returns a simulated site for ANY name — reporting "in sync"
    // from that would be false assurance, which is worse than no answer.
    $service = aapanelService(ServiceStatus::Active);

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::Unsupported)
        ->and($result['message'])->toContain('mock');
});

it('flags an active service that does not exist in the panel', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]); // panel knows no such site

    $service = aapanelService(ServiceStatus::Active, 'chybi.cz');

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::MissingRemote)
        ->and($service->fresh()->sync_state)->toBe(ServiceSyncState::MissingRemote)
        ->and($service->fresh()->last_synced_at)->not->toBeNull();
});

it('reports in sync when the site exists in the panel', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([['id' => 42, 'name' => 'existuje.cz', 'status' => '1']], 200)]);

    $service = aapanelService(ServiceStatus::Active, 'existuje.cz', externalId: '42');

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::InSync);
});

it('adopts the panel id when the service never recorded one', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([['id' => 77, 'name' => 'sirotek.cz', 'status' => '1']], 200)]);

    $service = aapanelService(ServiceStatus::Active, 'sirotek.cz', externalId: null);

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::Adopted)
        ->and($service->fresh()->external_id)->toBe('77');
});

it('does not flag a pending service that is not provisioned yet', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]);

    $service = aapanelService(ServiceStatus::Pending, 'ceka.cz');

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::InSync)
        ->and($result['state']->needsAttention())->toBeFalse();
});

it('flags a status mismatch when the panel has the site stopped', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([['id' => 9, 'name' => 'stoji.cz', 'status' => 'stop']], 200)]);

    $service = aapanelService(ServiceStatus::Active, 'stoji.cz', externalId: '9');

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::StatusMismatch);
});

it('records an error instead of throwing when the panel is unreachable', function (): void {
    liveAapanelSetting();
    Http::fake(fn () => throw new \RuntimeException('connection refused'));

    $service = aapanelService(ServiceStatus::Active, 'nedostupny.cz');

    $result = app(ServiceRemoteSyncService::class)->sync($service);

    expect($result['state'])->toBe(ServiceSyncState::Error)
        ->and($service->fresh()->sync_state)->toBe(ServiceSyncState::Error);
});

// ── The sweep command ─────────────────────────────────────────────────────────

it('notifies admins the first time a service drifts', function (): void {
    Notification::fake();
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]);

    $admin = adminUser();
    aapanelService(ServiceStatus::Active, 'drift.cz');

    $this->artisan('services:sync-remote')->assertSuccessful();

    Notification::assertSentTo($admin, ServiceSyncDriftNotification::class);
});

it('does not re-notify while the same drift persists', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]);

    adminUser();
    aapanelService(ServiceStatus::Active, 'drift.cz');

    $this->artisan('services:sync-remote')->assertSuccessful();

    Notification::fake(); // only watch the SECOND run
    $this->artisan('services:sync-remote')->assertSuccessful();

    Notification::assertNothingSent();
});

it('skips services that are not live unless --all is given', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]);

    $pending = aapanelService(ServiceStatus::Pending, 'ceka.cz');

    $this->artisan('services:sync-remote')->assertSuccessful();
    expect($pending->fresh()->last_synced_at)->toBeNull();

    $this->artisan('services:sync-remote', ['--all' => true])->assertSuccessful();
    expect($pending->fresh()->last_synced_at)->not->toBeNull();
});

// ── Admin UI ──────────────────────────────────────────────────────────────────

it('lets an admin verify a service against the panel from the detail page', function (): void {
    liveAapanelSetting();
    Http::fake(['*' => Http::response([], 200)]);

    $service = aapanelService(ServiceStatus::Active, 'overit.cz');

    $this->actingAs(adminUser())
        ->post(route('admin.services.sync-remote', $service))
        ->assertRedirect();

    expect($service->fresh()->sync_state)->toBe(ServiceSyncState::MissingRemote);
});

it('forbids a customer from triggering a panel sync', function (): void {
    $service = aapanelService(ServiceStatus::Active);

    $this->actingAs(customerUser())
        ->post(route('admin.services.sync-remote', $service))
        ->assertForbidden();
});

it('lets an admin re-provision a service that is missing in the panel', function (): void {
    $service = aapanelService(ServiceStatus::Active, 'znovu.cz');

    $this->actingAs(adminUser())
        ->post(route('admin.services.reprovision', $service))
        ->assertRedirect()
        ->assertSessionHas('status');
});

it('refuses to re-provision a terminated service', function (): void {
    $service = aapanelService(ServiceStatus::Terminated, 'ukonceno.cz');

    $this->actingAs(adminUser())
        ->post(route('admin.services.reprovision', $service))
        ->assertSessionHasErrors('service');
});

it('forbids a customer from re-provisioning a service', function (): void {
    $service = aapanelService(ServiceStatus::Active);

    $this->actingAs(customerUser())
        ->post(route('admin.services.reprovision', $service))
        ->assertForbidden();
});

it('shows the sync card on the admin service detail', function (): void {
    $service = aapanelService(ServiceStatus::Active, 'karta.cz');

    $this->actingAs(adminUser())
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Synchronizace s panelem')
        ->assertSee('Ověřit v panelu');
});
