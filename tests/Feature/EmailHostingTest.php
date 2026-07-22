<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Email hosting — customer self-service mailbox management on a webhosting
 * service. The backend + admin UI already existed; this is the customer side.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

function ownedHostingService(?\App\Models\User $user = null): Service
{
    $user ??= customerUser();

    return Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'mojedomena.cz',
        'external_id'         => '12',
    ]);
}

it('lets the customer create a mailbox on their webhosting service', function (): void {
    $user    = customerUser();
    $service = ownedHostingService($user);

    $this->actingAs($user)
        ->post(route('panel.services.mailboxes', $service), [
            'action' => 'create_mailbox', 'username' => 'info', 'password' => 'MailboxPass1!', 'quota_mb' => 2048,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    // The action ran through the provisioning pipeline (mock → dry-run task).
    $this->assertDatabaseHas('provisioning_tasks', ['service_id' => $service->id, 'operation' => 'create_mailbox']);
});

it('requires a password to create a mailbox', function (): void {
    $user    = customerUser();
    $service = ownedHostingService($user);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.mailboxes', $service), ['action' => 'create_mailbox', 'username' => 'info'])
        ->assertSessionHasErrors('password');
});

it('never stores the mailbox password on our side', function (): void {
    $user    = customerUser();
    $service = ownedHostingService($user);

    $this->actingAs($user)->post(route('panel.services.mailboxes', $service), [
        'action' => 'create_mailbox', 'username' => 'info', 'password' => 'SuperSecret99!',
    ])->assertRedirect();

    // The provisioning task payload must not carry the plaintext password.
    $task = \App\Domains\Provisioning\Models\ProvisioningTask::where('operation', 'create_mailbox')->firstOrFail();
    expect(json_encode($task->getAttributes()))->not->toContain('SuperSecret99!');
});

it('rejects mailbox management on a non-webhosting service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'         => $user->customer->id,
        'provisioning_driver' => ProvisioningDriver::Proxmox,
        'status'              => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->from(route('panel.services.show', $service))
        ->post(route('panel.services.mailboxes', $service), ['action' => 'create_mailbox', 'username' => 'x', 'password' => 'MailboxPass1!'])
        ->assertSessionHasErrors('mailbox');
});

it('forbids managing mailboxes on someone else’s service', function (): void {
    $theirs = ownedHostingService(customerUser());

    $this->actingAs(customerUser())
        ->post(route('panel.services.mailboxes', $theirs), ['action' => 'delete_mailbox', 'username' => 'info'])
        ->assertForbidden();
});

it('shows the mailbox card on a webhosting service detail', function (): void {
    $user    = customerUser();
    $service = ownedHostingService($user);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee('E-mailové schránky');
});
