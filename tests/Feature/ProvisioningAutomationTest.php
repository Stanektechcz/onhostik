<?php

declare(strict_types=1);

use App\Domains\Provisioning\Actions\DrainServerAction;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\ServiceSyncState;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Auto-healing (Y2) and server draining (Y3).
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function aapanelServer(string $status = 'active'): Server
{
    return Server::factory()->create([
        'driver' => ProvisioningDriver::AAPanel->value,
        'status' => $status,
    ]);
}

// ── Y2: auto-healing ──────────────────────────────────────────────────────────────

it('does nothing when auto-heal is disabled (default, opt-in)', function (): void {
    config(['provisioning.auto_heal' => false]);

    Service::factory()->create([
        'customer_id' => customerUser()->customer->id,
        'status'      => ServiceStatus::Active,
        'sync_state'  => ServiceSyncState::MissingRemote,
    ]);

    $this->artisan('services:heal')->expectsOutputToContain('disabled')->assertSuccessful();
});

it('re-provisions a MissingRemote paid service when enabled', function (): void {
    config(['provisioning.auto_heal' => true]);

    $service = Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'sync_state'          => ServiceSyncState::MissingRemote,
    ]);

    $this->artisan('services:heal')->assertSuccessful();

    // The reprovision ran and was audited.
    $this->assertDatabaseHas('activity_log', ['description' => 'service.auto_healed']);
});

it('leaves in-sync services alone', function (): void {
    config(['provisioning.auto_heal' => true]);

    Service::factory()->create([
        'customer_id' => customerUser()->customer->id,
        'status'      => ServiceStatus::Active,
        'sync_state'  => ServiceSyncState::InSync,
    ]);

    $this->artisan('services:heal')->expectsOutputToContain('Nothing to heal')->assertSuccessful();
});

// ── Y3: draining ──────────────────────────────────────────────────────────────────

it('moves live services off a drained server to another node', function (): void {
    $from = aapanelServer();
    $to   = aapanelServer();

    $service = Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $from->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $result = app(DrainServerAction::class)->execute($from);

    // Moved off the drained box to SOME other active node (the selector picks
    // the least-loaded, which may be the seeded server or $to).
    expect($result['moved'])->toBe(1)
        ->and($service->fresh()->server_id)->not->toBe($from->id)
        ->and($service->fresh()->server_id)->not->toBeNull()
        // The drained box is flipped to maintenance so nothing new lands on it.
        ->and($from->fresh()->status)->toBe('maintenance');
});

it('reports no_target when there is nowhere to move services', function (): void {
    // Only one aapanel server (plus whatever the seeder made) — force the issue
    // by pointing at a driver/server with no alternative active node.
    $from = aapanelServer();
    // Take every OTHER active aapanel server out of rotation.
    Server::where('driver', ProvisioningDriver::AAPanel->value)
        ->where('id', '!=', $from->id)
        ->update(['status' => 'offline']);

    Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $from->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $result = app(DrainServerAction::class)->execute($from);

    expect($result['moved'])->toBe(0)
        ->and($result['no_target'])->toBe(1);
});

it('does not migrate terminated services', function (): void {
    $from = aapanelServer();
    aapanelServer(); // a target exists

    Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $from->id,
        'status'              => ServiceStatus::Terminated,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $result = app(DrainServerAction::class)->execute($from);

    // Terminated service is excluded entirely — nothing to move.
    expect($result['moved'])->toBe(0)
        ->and($result['skipped'])->toBe(0)
        ->and($result['no_target'])->toBe(0);
});

it('lets an admin drain a server over HTTP', function (): void {
    $from = aapanelServer();
    aapanelServer();

    Service::factory()->create([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $from->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.servers.drain', $from))
        ->assertRedirect();

    expect($from->fresh()->status)->toBe('maintenance');
});
