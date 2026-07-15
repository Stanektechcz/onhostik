<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;

// ── Customer game server actions ──────────────────────────────────────────────

it('customer can request reinstall of own active game server', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '42',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.game-action', $service), ['action' => 'reinstall'])
        ->assertRedirect(route('panel.services.show', $service))
        ->assertSessionHas('status');

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'game_reinstall')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Success)
        ->and($task->result['dry_run'] ?? null)->toBeTrue();
});

it('game action denies foreign service', function (): void {
    $user  = customerUser();
    $other = customerUser();

    $foreign = Service::factory()->create([
        'customer_id'         => $other->customer->id,
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '42',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.game-action', $foreign), ['action' => 'reinstall'])
        ->assertForbidden();
});

it('game action rejected for non-pterodactyl service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.game-action', $service), ['action' => 'reinstall'])
        ->assertNotFound();
});

it('game action rejected for suspended service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->suspended()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '42',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.game-action', $service), ['action' => 'reinstall'])
        ->assertSessionHasErrors('game');

    expect(ProvisioningTask::where('service_id', $service->id)->count())->toBe(0);
});

it('job fails the task when server id is missing', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => null,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.game-action', $service), ['action' => 'reinstall']);

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'game_reinstall')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Failed);
});

it('panel game server detail shows the management card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '42',
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Správa game serveru')
        ->assertSee('Reinstalovat server');
});

it('admin can trigger game reinstall from admin detail', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '77',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall'])
        ->assertRedirect(route('admin.services.show', $service));

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'game_reinstall')->exists())
        ->toBeTrue();
});

it('non-admin cannot use admin game route', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::Pterodactyl,
        'external_id'         => '42',
    ]);

    $this->actingAs($user)
        ->post(route('admin.services.game-action', $service), ['action' => 'reinstall'])
        ->assertForbidden();
});
