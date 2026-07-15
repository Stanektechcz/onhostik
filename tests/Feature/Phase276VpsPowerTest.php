<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;

// ── Customer VPS power actions ────────────────────────────────────────────────

it('customer can start own active VPS and a task is recorded', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.vps-action', $service), ['action' => 'start'])
        ->assertRedirect(route('panel.services.show', $service))
        ->assertSessionHas('status');

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'vps_start')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Success)
        ->and($task->result['dry_run'] ?? null)->toBeTrue();
});

it('restart action records vps_restart task in mock mode', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '205',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.vps-action', $service), ['action' => 'restart']);

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'vps_restart')->exists())
        ->toBeTrue();
});

it('customer cannot control a foreign VPS', function (): void {
    $user  = customerUser();
    $other = customerUser();

    $foreign = Service::factory()->create([
        'customer_id'         => $other->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.vps-action', $foreign), ['action' => 'stop'])
        ->assertForbidden();
});

it('power actions are rejected for non-proxmox services', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.vps-action', $service), ['action' => 'start'])
        ->assertNotFound();
});

it('power actions are rejected for suspended services', function (): void {
    $user    = customerUser();
    $service = Service::factory()->suspended()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.vps-action', $service), ['action' => 'stop'])
        ->assertRedirect(route('panel.services.show', $service))
        ->assertSessionHasErrors('vps');

    expect(ProvisioningTask::where('service_id', $service->id)->count())->toBe(0);
});

it('invalid action fails validation', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.vps-action', $service), ['action' => 'detonate'])
        ->assertSessionHasErrors('action');
});

it('job marks the task failed when VMID is missing', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => null,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.vps-action', $service), ['action' => 'start']);

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'vps_start')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Failed);
});

// ── Customer live status ──────────────────────────────────────────────────────

it('customer live status returns whitelisted data for own VPS', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('panel.services.live-status', $service))
        ->assertOk()
        ->assertJson(['provider' => 'proxmox', 'ok' => true, 'dry_run' => true]);

    expect($response->json('data.status'))->toBe('running')
        ->and($response->json('data'))->not->toHaveKey('vmid');
});

it('customer live status denies foreign service', function (): void {
    $user  = customerUser();
    $other = customerUser();

    $foreign = Service::factory()->create([
        'customer_id'         => $other->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->getJson(route('panel.services.live-status', $foreign))
        ->assertForbidden();
});

it('panel VPS detail shows the power management card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Správa VPS')
        ->assertSee('Restartovat');
});

it('non-VPS service does not show the power card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertDontSee('Správa VPS');
});

// ── Admin VPS power actions ───────────────────────────────────────────────────

it('admin can stop a VPS from the admin service detail', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '300',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.vps-action', $service), ['action' => 'stop'])
        ->assertRedirect(route('admin.services.show', $service));

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'vps_stop')->exists())
        ->toBeTrue();
});

it('admin can act on suspended VPS too', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->suspended()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '301',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.services.vps-action', $service), ['action' => 'stop']);

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'vps_stop')->exists())
        ->toBeTrue();
});

it('non-admin cannot use the admin vps action route', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->post(route('admin.services.vps-action', $service), ['action' => 'start'])
        ->assertForbidden();
});
