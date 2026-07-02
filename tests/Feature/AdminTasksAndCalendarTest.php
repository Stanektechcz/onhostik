<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Models\AdminTask;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── AdminTask: access control ─────────────────────────────────────────────

it('blocks customers from the task board', function (): void {
    $this->actingAs(customerUser())->get(route('admin.tasks'))->assertForbidden();
});

it('allows admin to view the task board', function (): void {
    $this->actingAs(adminUser())->get(route('admin.tasks'))->assertOk();
});

// ── AdminTask: CRUD ───────────────────────────────────────────────────────

it('admin can create a task', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.tasks.store'), [
            'title'       => 'Opravit webhook',
            'description' => 'Comgate webhook vrací 500',
            'status'      => 'pending',
            'priority'    => 'high',
            'due_date'    => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    expect(AdminTask::where('title', 'Opravit webhook')->exists())->toBeTrue();
});

it('admin can update a task', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Původní název',
        'status'     => 'pending',
        'priority'   => 'low',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.tasks.update', $task), [
            'title'    => 'Nový název',
            'status'   => 'inprogress',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $task->refresh();
    expect($task->title)->toBe('Nový název');
    expect($task->status)->toBe('inprogress');
});

it('admin can delete a task', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Smazat mě',
        'status'     => 'pending',
        'priority'   => 'low',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.tasks.destroy', $task))
        ->assertRedirect();

    expect(AdminTask::find($task->id))->toBeNull();
});

// ── AdminTask: status toggle ──────────────────────────────────────────────

it('toggleStatus cycles pending → inprogress', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Cyklus',
        'status'     => 'pending',
        'priority'   => 'medium',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tasks.toggle', $task))
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('inprogress');
});

it('toggleStatus cycles inprogress → done and sets completed_at', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Cyklus done',
        'status'     => 'inprogress',
        'priority'   => 'medium',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tasks.toggle', $task))
        ->assertRedirect();

    $fresh = $task->fresh();
    expect($fresh->status)->toBe('done');
    expect($fresh->completed_at)->not->toBeNull();
});

it('toggleStatus cycles done → pending and clears completed_at', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'        => 'Znovu čekat',
        'status'       => 'done',
        'priority'     => 'medium',
        'created_by'   => $admin->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.tasks.toggle', $task))
        ->assertRedirect();

    $fresh = $task->fresh();
    expect($fresh->status)->toBe('pending');
    expect($fresh->completed_at)->toBeNull();
});

// ── AdminTask: model helpers ──────────────────────────────────────────────

it('AdminTask::isOverdue returns true for past due_date on non-done task', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Opožděný',
        'status'     => 'pending',
        'priority'   => 'high',
        'due_date'   => now()->subDay()->toDateString(),
        'created_by' => $admin->id,
    ]);

    expect($task->isOverdue())->toBeTrue();
});

it('AdminTask::isOverdue returns false for done tasks even if past due', function (): void {
    $admin = adminUser();
    $task  = AdminTask::create([
        'title'      => 'Hotovo',
        'status'     => 'done',
        'priority'   => 'low',
        'due_date'   => now()->subDays(3)->toDateString(),
        'created_by' => $admin->id,
    ]);

    expect($task->isOverdue())->toBeFalse();
});

it('scopeNotDone excludes done tasks', function (): void {
    $admin = adminUser();
    AdminTask::create(['title' => 'Hotovo', 'status' => 'done',       'priority' => 'low', 'created_by' => $admin->id]);
    AdminTask::create(['title' => 'Čeká',   'status' => 'pending',    'priority' => 'low', 'created_by' => $admin->id]);
    AdminTask::create(['title' => 'Probíhá','status' => 'inprogress', 'priority' => 'low', 'created_by' => $admin->id]);

    $active = AdminTask::notDone()->get();
    expect($active)->toHaveCount(2);
    expect($active->pluck('title')->toArray())->not->toContain('Hotovo');
});

// ── Calendar ──────────────────────────────────────────────────────────────

it('blocks customers from the calendar', function (): void {
    $this->actingAs(customerUser())->get(route('admin.calendar'))->assertForbidden();
});

it('allows admin to view the calendar page', function (): void {
    $this->actingAs(adminUser())->get(route('admin.calendar'))->assertOk();
});

it('calendar events endpoint returns JSON array', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.calendar.events'));

    $response->assertOk();
    expect($response->json())->toBeArray();
});

it('calendar events includes service renewals expiring soon', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'next_due_date'       => now()->addDays(10)->toDateString(),
        'label'               => 'webhosting-test',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    $response = $this->actingAs(adminUser())
        ->getJson(route('admin.calendar.events'));

    $response->assertOk();
    $events = collect($response->json());
    // service due in 10 days → color #7366FF (>7 days bucket)
    expect($events->where('color', '#7366FF')->count())->toBeGreaterThan(0);
});

// ── Service batch operations ──────────────────────────────────────────────

it('batch suspend queues jobs for active services', function (): void {
    \Illuminate\Support\Facades\Queue::fake();

    $user      = customerUser();
    $admin     = adminUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-batch',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.services.batch-suspend'), [
            'ids'    => [$service->id],
            'reason' => 'Neuhrazená faktura',
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Domains\Provisioning\Jobs\ChangeServiceStateJob::class
    );
});

it('batch suspend ignores non-active services', function (): void {
    \Illuminate\Support\Facades\Queue::fake();

    $user      = customerUser();
    $admin     = adminUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'already-suspended',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.services.batch-suspend'), [
            'ids'    => [$service->id],
            'reason' => 'Neuhrazená faktura',
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Queue::assertNotPushed(
        \App\Domains\Provisioning\Jobs\ChangeServiceStateJob::class
    );
});

it('batch unsuspend queues jobs for suspended services', function (): void {
    \Illuminate\Support\Facades\Queue::fake();

    $user      = customerUser();
    $admin     = adminUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Suspended,
        'label'               => 'test-unsuspend',
        'provisioning_driver' => \App\Domains\Provisioning\Enums\ProvisioningDriver::AAPanel,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.services.batch-unsuspend'), [
            'ids' => [$service->id],
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Domains\Provisioning\Jobs\ChangeServiceStateJob::class
    );
});

it('batch suspend validates reason is required', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.services.batch-suspend'), [
            'ids' => [1],
        ])
        ->assertSessionHasErrors('reason');
});

it('batch suspend validates max 50 ids', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.services.batch-suspend'), [
            'ids'    => range(1, 51),
            'reason' => 'Hromadné pozastavení',
        ])
        ->assertSessionHasErrors('ids');
});
