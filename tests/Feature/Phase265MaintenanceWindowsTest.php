<?php

declare(strict_types=1);

use App\Models\MaintenanceWindow;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list maintenance windows', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.maintenance-windows.index'))
        ->assertOk()
        ->assertViewIs('admin.maintenance-windows.index');
});

it('admin can schedule a maintenance window', function () {
    $admin = adminUser();
    $this->actingAs($admin)->post(route('admin.maintenance-windows.store'), [
        'title'     => 'DB upgrade',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at'   => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
    ])->assertRedirect();
    $this->assertDatabaseHas('maintenance_windows', ['title' => 'DB upgrade', 'status' => 'scheduled']);
});

it('admin can update maintenance window status', function () {
    $admin = adminUser();
    $window = MaintenanceWindow::create([
        'title'     => 'Upgrade',
        'starts_at' => now()->addHour(),
        'ends_at'   => now()->addHours(3),
        'message'   => '',
        'color'     => 'info',
        'is_active' => true,
        'status'    => 'scheduled',
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.maintenance-windows.update', $window), ['status' => 'in_progress'])
        ->assertRedirect();
    $this->assertDatabaseHas('maintenance_windows', ['id' => $window->id, 'status' => 'in_progress']);
});

it('admin can delete a maintenance window', function () {
    $admin = adminUser();
    $window = MaintenanceWindow::create([
        'title'     => 'Old window',
        'starts_at' => now()->subDay(),
        'ends_at'   => now()->subHour(),
        'message'   => '',
        'color'     => 'warning',
        'is_active' => false,
        'status'    => 'completed',
    ]);
    $this->actingAs($admin)
        ->delete(route('admin.maintenance-windows.destroy', $window))
        ->assertRedirect();
    $this->assertDatabaseMissing('maintenance_windows', ['id' => $window->id]);
});

it('panel customer can view upcoming maintenance windows', function () {
    $user = customerUser();
    $this->actingAs($user)
        ->get(route('panel.maintenance-windows.index'))
        ->assertOk()
        ->assertViewIs('panel.maintenance-windows.index');
});
