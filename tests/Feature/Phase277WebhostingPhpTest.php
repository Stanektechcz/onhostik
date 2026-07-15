<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;

// ── Customer PHP version change ───────────────────────────────────────────────

it('customer can request PHP version change for own webhosting', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-123',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.php-version', $service), ['php_version' => '83'])
        ->assertRedirect(route('panel.services.show', $service))
        ->assertSessionHas('status');

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'set_php_version')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Success)
        ->and($task->result['dry_run'] ?? null)->toBeTrue()
        ->and($service->fresh()->resources['php_version'] ?? null)->toBe('83');
});

it('php change denies foreign service', function (): void {
    $user  = customerUser();
    $other = customerUser();

    $foreign = Service::factory()->create([
        'customer_id'         => $other->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-1',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.php-version', $foreign), ['php_version' => '82'])
        ->assertForbidden();
});

it('php change rejected for non-aapanel service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.php-version', $service), ['php_version' => '82'])
        ->assertNotFound();
});

it('php change rejected for suspended service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->suspended()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-9',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.php-version', $service), ['php_version' => '82'])
        ->assertSessionHasErrors('php');

    expect(ProvisioningTask::where('service_id', $service->id)->count())->toBe(0);
});

it('unsupported php version fails validation', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-5',
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.php-version', $service), ['php_version' => '56'])
        ->assertSessionHasErrors('php_version');
});

it('job fails the task when site id is missing', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => null,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.php-version', $service), ['php_version' => '82']);

    $task = ProvisioningTask::where('service_id', $service->id)->where('operation', 'set_php_version')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Failed);
});

it('panel webhosting detail shows the management card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-7',
        'resources'           => ['php_version' => '82'],
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('Správa webhostingu')
        ->assertSee('PHP 8.2');
});

it('vps service does not show webhosting card', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'external_id'         => '100',
    ]);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertDontSee('Správa webhostingu');
});

// ── Admin PHP version change ──────────────────────────────────────────────────

it('admin can change php version from admin detail', function (): void {
    $admin   = adminUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-42',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.services.show', $service))
        ->post(route('admin.services.php-version', $service), ['php_version' => '81'])
        ->assertRedirect(route('admin.services.show', $service));

    expect(ProvisioningTask::where('service_id', $service->id)->where('operation', 'set_php_version')->exists())
        ->toBeTrue()
        ->and($service->fresh()->resources['php_version'] ?? null)->toBe('81');
});

it('non-admin cannot use admin php route', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'external_id'         => 'AAP-1',
    ]);

    $this->actingAs($user)
        ->post(route('admin.services.php-version', $service), ['php_version' => '82'])
        ->assertForbidden();
});
