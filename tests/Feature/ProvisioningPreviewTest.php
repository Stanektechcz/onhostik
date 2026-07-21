<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ProvisioningPreviewService;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Audit 56 — a read-only preview of what a provision would do, shown before the
 * admin actually runs it. It must resolve the same driver/server the job would,
 * and it must never write.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function previewService(array $attributes = []): Service
{
    $server = Server::factory()->create([
        'driver'       => ProvisioningDriver::AAPanel->value,
        'status'       => 'active',
        'max_services' => 10,
        'mock_mode'    => true,
    ]);

    return Service::factory()->create(array_merge([
        'customer_id'         => customerUser()->customer->id,
        'server_id'           => $server->id,
        'status'              => ServiceStatus::Active,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ], $attributes));
}

it('reports the driver, target server and mode for a service', function (): void {
    $preview = app(ProvisioningPreviewService::class)->forService(previewService());

    expect($preview['driver'])->toBe('aapanel')
        ->and($preview['target_server'])->not->toBeNull()
        // Real writes are OFF by default → a run would be simulated, not live.
        ->and($preview['writes_enabled'])->toBeFalse()
        ->and($preview['mode'])->toBe('dry-run');
});

it('probes the backend connection without provisioning anything', function (): void {
    $service = previewService();

    $preview = app(ProvisioningPreviewService::class)->forService($service);

    // The mock driver reports a healthy connection; the service state is
    // untouched by merely previewing.
    expect($preview['connection_ok'])->toBeTrue()
        ->and($service->fresh()->status)->toBe(ServiceStatus::Active);
});

it('flags a service with no driver as unplannable', function (): void {
    // The column is NOT NULL, so a driverless service only exists transiently
    // (mid-setup / corrupt row) — set it in memory rather than persist null.
    $service = previewService();
    $service->provisioning_driver = null;

    $preview = app(ProvisioningPreviewService::class)->forService($service);

    expect($preview['driver'])->toBeNull()
        ->and($preview['mode'])->toBe('unavailable')
        ->and($preview['notes'])->not->toBeEmpty();
});

it('notes when an unassigned service would pick the least-loaded server', function (): void {
    // No server_id → the preview runs the same selection the job would.
    $service = previewService();
    $service->update(['server_id' => null]);

    $preview = app(ProvisioningPreviewService::class)->forService($service->fresh());

    expect($preview['target_server'])->not->toBeNull()
        ->and(collect($preview['notes'])->implode(' '))->toContain('nejméně vytížený');
});

it('exposes the preview to an admin over HTTP as JSON', function (): void {
    $service = previewService();

    $this->actingAs(adminUser())
        ->getJson(route('admin.services.provision-preview', $service))
        ->assertOk()
        ->assertJsonStructure(['driver', 'driver_class', 'target_server', 'writes_enabled', 'mode', 'connection_ok', 'notes']);
});

it('forbids a customer from seeing the provisioning preview', function (): void {
    $service = previewService();

    $this->actingAs(customerUser())
        ->get(route('admin.services.provision-preview', $service))
        ->assertForbidden();
});

it('treats a domain (WEDOS) service as registrar-provisioned, not server', function (): void {
    $service = previewService(['provisioning_driver' => ProvisioningDriver::Wedos, 'server_id' => null]);

    $preview = app(ProvisioningPreviewService::class)->forService($service->fresh());

    expect($preview['driver'])->toBe('wedos')
        ->and($preview['target_server'])->toBeNull()
        ->and(collect($preview['notes'])->implode(' '))->toContain('registrátora');
});
