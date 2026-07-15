<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceBackupLog;
use App\Models\ServiceFirewallRule;
use App\Models\ServiceHealthIncident;

// ── Service 360° detail sections ──────────────────────────────────────────────

it('admin service detail shows unified 360 sections', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create(['label' => 'unified-web.cz']);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Živý stav')
        ->assertSee('Firewall pravidla')
        ->assertSee('Health incidenty');
});

it('service detail lists existing firewall rules inline', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create();

    ServiceFirewallRule::create([
        'service_id' => $service->id,
        'direction'  => 'in',
        'protocol'   => 'tcp',
        'port_from'  => 443,
        'port_to'    => 443,
        'ip_cidr'    => '10.20.30.0/24',
        'action'     => 'allow',
        'is_active'  => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('10.20.30.0/24');
});

it('service detail lists open health incidents inline', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create();

    ServiceHealthIncident::create([
        'service_id' => $service->id,
        'severity'   => 'critical',
        'title'      => 'Disk téměř plný na 360 detailu',
        'status'     => 'open',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Disk téměř plný na 360 detailu');
});

it('service detail shows recent backup logs when present', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create();

    ServiceBackupLog::create([
        'service_id'       => $service->id,
        'status'           => 'success',
        'size_bytes'       => 52428800,
        'duration_seconds' => 42,
        'started_at'       => now()->subHour(),
        'completed_at'     => now()->subHour()->addSeconds(42),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('Poslední zálohy');
});

it('domain services do not show the firewall card', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Wedos,
        'label'               => 'domena-bez-fw.cz',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertDontSee('Firewall pravidla');
});

// ── Live status endpoint ──────────────────────────────────────────────────────

it('live status returns dry-run payload for aapanel service in mock mode', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'label'               => 'mock-web.cz',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'aapanel', 'ok' => true, 'dry_run' => true]);
});

it('live status returns dry-run payload for proxmox VPS in mock mode', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'proxmox', 'ok' => true, 'dry_run' => true]);
});

it('live status returns dry-run payload for pterodactyl game server in mock mode', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '7',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'pterodactyl', 'ok' => true, 'dry_run' => true]);
});

it('live status returns dry-run payload for wedos domain in mock mode', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Wedos,
        'label'               => 'domena-live.cz',
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'wedos', 'ok' => true, 'dry_run' => true]);
});

it('live status reports an error for proxmox service without VMID', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => null,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'proxmox', 'ok' => false]);

    expect($response->json('error'))->not->toBeNull();
});

it('non-admin cannot access live status', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->getJson(route('admin.services.live-status', $service))
        ->assertForbidden();
});

it('guest cannot access live status', function (): void {
    $service = Service::factory()->create();

    $this->getJson(route('admin.services.live-status', $service))
        ->assertUnauthorized();
});
