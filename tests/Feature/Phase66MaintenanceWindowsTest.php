<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceMaintenanceWindow;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Model helpers ─────────────────────────────────────────────────────────────

it('ServiceMaintenanceWindow statusLabel returns Czech strings', function (): void {
    expect((new ServiceMaintenanceWindow(['status' => 'scheduled']))->statusLabel())->toBe('Plánováno')
        ->and((new ServiceMaintenanceWindow(['status' => 'in_progress']))->statusLabel())->toBe('Probíhá')
        ->and((new ServiceMaintenanceWindow(['status' => 'completed']))->statusLabel())->toBe('Dokončeno')
        ->and((new ServiceMaintenanceWindow(['status' => 'cancelled']))->statusLabel())->toBe('Zrušeno');
});

it('ServiceMaintenanceWindow statusBadgeClass returns correct classes', function (): void {
    expect((new ServiceMaintenanceWindow(['status' => 'scheduled']))->statusBadgeClass())->toBe('bg-warning')
        ->and((new ServiceMaintenanceWindow(['status' => 'in_progress']))->statusBadgeClass())->toBe('bg-danger')
        ->and((new ServiceMaintenanceWindow(['status' => 'completed']))->statusBadgeClass())->toBe('bg-success')
        ->and((new ServiceMaintenanceWindow(['status' => 'cancelled']))->statusBadgeClass())->toBe('bg-secondary');
});

it('ServiceMaintenanceWindow durationMinutes calculates correctly', function (): void {
    $w = new ServiceMaintenanceWindow([
        'scheduled_start' => now(),
        'scheduled_end'   => now()->addMinutes(90),
    ]);
    expect($w->durationMinutes())->toBe(90);
});

it('ServiceMaintenanceWindow isActive returns true only for in_progress', function (): void {
    expect((new ServiceMaintenanceWindow(['status' => 'in_progress']))->isActive())->toBeTrue()
        ->and((new ServiceMaintenanceWindow(['status' => 'scheduled']))->isActive())->toBeFalse();
});

// ── Service relation ──────────────────────────────────────────────────────────

it('Service has maintenanceWindows HasMany relation', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    ServiceMaintenanceWindow::create([
        'service_id'      => $service->id,
        'title'           => 'Test',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addHour(),
        'scheduled_end'   => now()->addHours(2),
    ]);

    expect($service->maintenanceWindows()->count())->toBe(1);
});

// ── upcoming scope ────────────────────────────────────────────────────────────

it('upcoming scope returns only future scheduled and in_progress records', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    ServiceMaintenanceWindow::create([
        'service_id'      => $service->id,
        'title'           => 'Past',
        'status'          => 'completed',
        'scheduled_start' => now()->subDays(2),
        'scheduled_end'   => now()->subDay(),
    ]);

    ServiceMaintenanceWindow::create([
        'service_id'      => $service->id,
        'title'           => 'Future',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDays(2),
    ]);

    $upcoming = ServiceMaintenanceWindow::where('service_id', $service->id)->upcoming()->get();

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming->first()->title)->toBe('Future');
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can list maintenance windows', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.maintenance.index'))
        ->assertOk()
        ->assertSee('Okna údržby');
});

it('non-admin cannot access maintenance admin', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.maintenance.index'))
        ->assertStatus(403);
});

it('admin can create a maintenance window', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.maintenance.store'), [
            'title'            => 'Server upgrade',
            'description'      => 'Plánovaná aktualizace hardware',
            'service_id'       => null,
            'status'           => 'scheduled',
            'scheduled_start'  => now()->addDay()->format('Y-m-d\TH:i'),
            'scheduled_end'    => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'notify_customers' => '1',
        ])
        ->assertRedirect(route('admin.maintenance.index'));

    expect(ServiceMaintenanceWindow::where('title', 'Server upgrade')->exists())->toBeTrue();
});

it('admin create validates scheduled_end after scheduled_start', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.maintenance.store'), [
            'title'           => 'Bad window',
            'status'          => 'scheduled',
            'scheduled_start' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'scheduled_end'   => now()->addHour()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('scheduled_end');
});

it('admin can create maintenance window linked to a service', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($admin)
        ->post(route('admin.maintenance.store'), [
            'title'           => 'Service maintenance',
            'status'          => 'scheduled',
            'service_id'      => $service->id,
            'scheduled_start' => now()->addDay()->format('Y-m-d\TH:i'),
            'scheduled_end'   => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    expect(ServiceMaintenanceWindow::where('service_id', $service->id)->exists())->toBeTrue();
});

it('admin can update a maintenance window', function (): void {
    $admin = adminUser();
    $w     = ServiceMaintenanceWindow::create([
        'title'           => 'Old title',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.maintenance.update', $w), [
            'title'           => 'Updated title',
            'status'          => 'scheduled',
            'scheduled_start' => now()->addDay()->format('Y-m-d\TH:i'),
            'scheduled_end'   => now()->addDay()->addHours(3)->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    expect($w->fresh()->title)->toBe('Updated title');
});

it('admin can start a maintenance window', function (): void {
    $admin = adminUser();
    $w     = ServiceMaintenanceWindow::create([
        'title'           => 'Test',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addHour(),
        'scheduled_end'   => now()->addHours(2),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.maintenance.start', $w))
        ->assertRedirect();

    expect($w->fresh()->status)->toBe('in_progress')
        ->and($w->fresh()->actual_start)->not->toBeNull();
});

it('admin can complete a maintenance window', function (): void {
    $admin = adminUser();
    $w     = ServiceMaintenanceWindow::create([
        'title'           => 'Test',
        'status'          => 'in_progress',
        'scheduled_start' => now()->subHour(),
        'scheduled_end'   => now()->addHour(),
        'actual_start'    => now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.maintenance.complete', $w))
        ->assertRedirect();

    expect($w->fresh()->status)->toBe('completed')
        ->and($w->fresh()->actual_end)->not->toBeNull();
});

it('admin can cancel a maintenance window', function (): void {
    $admin = adminUser();
    $w     = ServiceMaintenanceWindow::create([
        'title'           => 'Test',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.maintenance.cancel', $w))
        ->assertRedirect();

    expect($w->fresh()->status)->toBe('cancelled');
});

it('admin can delete a maintenance window', function (): void {
    $admin = adminUser();
    $w     = ServiceMaintenanceWindow::create([
        'title'           => 'Delete me',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHour(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.maintenance.destroy', $w))
        ->assertRedirect();

    expect(ServiceMaintenanceWindow::find($w->id))->toBeNull();
});

// ── Panel routes ──────────────────────────────────────────────────────────────

it('customer can view their maintenance page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.maintenance.index'))
        ->assertOk()
        ->assertSee('Plánovaná údržba');
});

it('customer sees upcoming maintenance for their services', function (): void {
    $user    = customerUser();
    $service = Service::factory()->for($user->customer)->create(['status' => ServiceStatus::Active]);

    ServiceMaintenanceWindow::create([
        'service_id'      => $service->id,
        'title'           => 'Moje údržba',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHours(2),
    ]);

    $this->actingAs($user)
        ->get(route('panel.maintenance.index'))
        ->assertOk()
        ->assertSee('Moje údržba');
});

it('customer does not see other customers maintenance', function (): void {
    $user1   = customerUser();
    $user2   = customerUser();
    $service = Service::factory()->for($user2->customer)->create(['status' => ServiceStatus::Active]);

    ServiceMaintenanceWindow::create([
        'service_id'      => $service->id,
        'title'           => 'Cizí údržba',
        'status'          => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHours(2),
    ]);

    $this->actingAs($user1)
        ->get(route('panel.maintenance.index'))
        ->assertOk()
        ->assertDontSee('Cizí údržba');
});

it('unauthenticated user cannot view maintenance page', function (): void {
    $this->get(route('panel.maintenance.index'))->assertRedirect();
});
