<?php

declare(strict_types=1);

use App\Models\ServiceBackupLog;
use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list backup logs', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.service-backup-logs.index'))
        ->assertOk()
        ->assertViewIs('admin.service-backup-logs.index');
});

it('admin can filter backup logs by service', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    ServiceBackupLog::create([
        'service_id' => $service->id,
        'status'     => 'success',
        'started_at' => now(),
    ]);
    $response = $this->actingAs($admin)
        ->get(route('admin.service-backup-logs.index', ['service_id' => $service->id]));
    $response->assertOk()->assertViewHas('serviceId', (string) $service->id);
});

it('admin can filter backup logs by status', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.service-backup-logs.index', ['status' => 'failed']))
        ->assertOk()
        ->assertViewHas('status', 'failed');
});

it('panel customer can view backup logs for own services', function () {
    $user = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    ServiceBackupLog::create([
        'service_id' => $service->id,
        'status'     => 'success',
        'started_at' => now(),
    ]);
    $this->actingAs($user)
        ->get(route('panel.service-backup-logs.index'))
        ->assertOk()
        ->assertViewIs('panel.service-backup-logs.index');
});

it('guest is redirected from backup logs', function () {
    $this->get(route('admin.service-backup-logs.index'))->assertRedirect();
});
