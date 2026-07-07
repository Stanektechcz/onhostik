<?php

declare(strict_types=1);

use App\Models\MaintenanceWindow;
use Illuminate\Support\Carbon;

// ── Model ─────────────────────────────────────────────────────────────────────

it('COLORS constant includes all supported colors', function (): void {
    expect(MaintenanceWindow::COLORS)->toContain('warning', 'danger', 'info', 'primary');
});

it('active scope returns only current windows', function (): void {
    MaintenanceWindow::create([
        'title'     => 'Past',
        'message'   => 'm',
        'starts_at' => now()->subHours(3),
        'ends_at'   => now()->subHour(),
        'is_active' => true,
    ]);

    MaintenanceWindow::create([
        'title'     => 'Current',
        'message'   => 'm',
        'starts_at' => now()->subHour(),
        'ends_at'   => now()->addHour(),
        'is_active' => true,
    ]);

    MaintenanceWindow::create([
        'title'     => 'Upcoming',
        'message'   => 'm',
        'starts_at' => now()->addHour(),
        'ends_at'   => now()->addHours(3),
        'is_active' => true,
    ]);

    expect(MaintenanceWindow::active()->count())->toBe(1)
        ->and(MaintenanceWindow::active()->first()->title)->toBe('Current');
});

it('upcoming scope returns windows in the future', function (): void {
    MaintenanceWindow::create([
        'title' => 'Future', 'message' => 'm',
        'starts_at' => now()->addHour(),
        'ends_at'   => now()->addHours(2),
        'is_active' => true,
    ]);

    expect(MaintenanceWindow::upcoming()->count())->toBe(1);
});

it('isCurrentlyActive returns true during the window', function (): void {
    $maint = MaintenanceWindow::create([
        'title' => 'Live', 'message' => 'm',
        'starts_at' => now()->subMinutes(10),
        'ends_at'   => now()->addMinutes(10),
        'is_active' => true,
    ]);

    expect($maint->isCurrentlyActive())->toBeTrue();
});

it('isCurrentlyActive returns false for inactive window', function (): void {
    $maint = MaintenanceWindow::create([
        'title' => 'Off', 'message' => 'm',
        'starts_at' => now()->subMinutes(10),
        'ends_at'   => now()->addMinutes(10),
        'is_active' => false,
    ]);

    expect($maint->isCurrentlyActive())->toBeFalse();
});

it('isUpcoming returns true for future window', function (): void {
    $maint = MaintenanceWindow::create([
        'title' => 'Later', 'message' => 'm',
        'starts_at' => now()->addHour(),
        'ends_at'   => now()->addHours(2),
        'is_active' => true,
    ]);

    expect($maint->isUpcoming())->toBeTrue();
});

it('currentBanners returns active and upcoming windows', function (): void {
    MaintenanceWindow::create([
        'title' => 'Now', 'message' => 'm',
        'starts_at'        => now()->subMinute(),
        'ends_at'          => now()->addHour(),
        'show_on_frontend' => true,
        'is_active'        => true,
    ]);
    MaintenanceWindow::create([
        'title' => 'Soon', 'message' => 'm',
        'starts_at'        => now()->addHours(2),
        'ends_at'          => now()->addHours(4),
        'show_on_frontend' => true,
        'is_active'        => true,
    ]);

    $banners = MaintenanceWindow::currentBanners(false);
    expect($banners['active']?->title)->toBe('Now')
        ->and($banners['upcoming']?->title)->toBe('Soon');
});

it('forAdmin scope filters by show_on_admin', function (): void {
    MaintenanceWindow::create([
        'title' => 'Admin only', 'message' => 'm',
        'starts_at'     => now()->subMinute(),
        'ends_at'       => now()->addHour(),
        'show_on_admin' => true,
        'is_active'     => true,
    ]);
    MaintenanceWindow::create([
        'title' => 'Front only', 'message' => 'm',
        'starts_at'     => now()->subMinute(),
        'ends_at'       => now()->addHour(),
        'show_on_admin' => false,
        'is_active'     => true,
    ]);

    expect(MaintenanceWindow::active()->forAdmin()->count())->toBe(1)
        ->and(MaintenanceWindow::active()->forAdmin()->first()->title)->toBe('Admin only');
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view maintenance windows page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.maintenance-banners.index'))
        ->assertOk()
        ->assertSee('Okna údržby');
});

it('admin can create a maintenance window', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.maintenance-banners.store'), [
            'title'            => 'Plánovaná údržba',
            'message'          => 'Systém bude dočasně nedostupný.',
            'starts_at'        => now()->addDay()->format('Y-m-d H:i'),
            'ends_at'          => now()->addDay()->addHours(2)->format('Y-m-d H:i'),
            'show_on_frontend' => 1,
            'show_on_admin'    => 1,
            'color'            => 'warning',
            'is_active'        => 1,
        ])
        ->assertRedirect(route('admin.maintenance-banners.index'));

    expect(MaintenanceWindow::where('title', 'Plánovaná údržba')->exists())->toBeTrue();
});

it('validation rejects ends_at before starts_at', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.maintenance-banners.store'), [
            'title'     => 'Bad window',
            'message'   => 'x',
            'starts_at' => now()->addHours(3)->format('Y-m-d H:i'),
            'ends_at'   => now()->addHour()->format('Y-m-d H:i'),
            'color'     => 'warning',
        ])
        ->assertSessionHasErrors('ends_at');
});

it('admin can update a maintenance window', function (): void {
    $admin = adminUser();
    $maint = MaintenanceWindow::create([
        'title'     => 'Old',
        'message'   => 'm',
        'starts_at' => now()->addDay(),
        'ends_at'   => now()->addDay()->addHour(),
        'color'     => 'warning',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.maintenance-banners.update', $maint), [
            'title'            => 'Updated',
            'message'          => 'New message',
            'starts_at'        => now()->addDay()->format('Y-m-d H:i'),
            'ends_at'          => now()->addDay()->addHours(3)->format('Y-m-d H:i'),
            'show_on_frontend' => 1,
            'show_on_admin'    => 0,
            'color'            => 'danger',
            'is_active'        => 1,
        ])
        ->assertRedirect(route('admin.maintenance-banners.index'));

    expect($maint->fresh()->title)->toBe('Updated')
        ->and($maint->fresh()->color)->toBe('danger');
});

it('admin can delete a maintenance window', function (): void {
    $admin = adminUser();
    $maint = MaintenanceWindow::create([
        'title' => 'Delete me', 'message' => 'm',
        'starts_at' => now()->addDay(),
        'ends_at'   => now()->addDay()->addHour(),
        'color'     => 'info',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.maintenance-banners.destroy', $maint))
        ->assertRedirect(route('admin.maintenance-banners.index'));

    expect(MaintenanceWindow::find($maint->id))->toBeNull();
});

it('non-admin cannot access maintenance routes', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.maintenance-banners.index'))
        ->assertStatus(403);
});
