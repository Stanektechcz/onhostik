<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;

// ── Unified per-platform view of services ─────────────────────────────────────

it('services index shows platform filter chips with counts', function (): void {
    $admin = adminUser();

    Service::factory()->count(2)->create(['provisioning_driver' => ProvisioningDriver::AAPanel]);
    Service::factory()->create(['provisioning_driver' => ProvisioningDriver::Proxmox]);

    $this->actingAs($admin)
        ->get(route('admin.services.index'))
        ->assertOk()
        ->assertSee('AAPanel (webhosting)')
        ->assertSee('Proxmox VE (VPS/cloud)')
        ->assertSee('Pterodactyl (gamehosting)')
        ->assertSee('WEDOS WAPI (domény)');
});

it('driver filter narrows the service list', function (): void {
    $admin = adminUser();

    Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'label'               => 'web-only-service.cz',
    ]);
    Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'label'               => 'vps-only-service',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.index', ['driver' => 'proxmox']))
        ->assertOk()
        ->assertSee('vps-only-service')
        ->assertDontSee('web-only-service.cz');
});

it('invalid driver value is ignored', function (): void {
    $admin = adminUser();

    Service::factory()->create(['label' => 'still-visible.cz']);

    $this->actingAs($admin)
        ->get(route('admin.services.index', ['driver' => 'nonsense']))
        ->assertOk()
        ->assertSee('still-visible.cz');
});

it('driver filter combines with status filter', function (): void {
    $admin = adminUser();

    Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'label'               => 'active-game-server',
    ]);
    Service::factory()->suspended()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'label'               => 'suspended-game-server',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.index', ['driver' => 'pterodactyl', 'status' => 'suspended']))
        ->assertOk()
        ->assertSee('suspended-game-server')
        ->assertDontSee('active-game-server');
});

it('csv export respects the driver filter', function (): void {
    $admin = adminUser();

    Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Wedos,
        'label'               => 'export-domain.cz',
    ]);
    Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'label'               => 'export-web.cz',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.services.export', ['driver' => 'wedos']))
        ->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toContain('export-domain.cz')
        ->not->toContain('export-web.cz');
});
