<?php

declare(strict_types=1);

use App\Models\ServiceHealthIncident;
use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can list service health incidents', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.service-health-incidents.index'))
        ->assertOk()
        ->assertViewIs('admin.service-health-incidents.index');
});

it('admin can create a health incident', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $this->actingAs($admin)->post(route('admin.service-health-incidents.store'), [
        'service_id' => $service->id,
        'severity'   => 'critical',
        'title'      => 'Disk full',
        'status'     => 'open',
    ])->assertRedirect();
    $this->assertDatabaseHas('service_health_incidents', ['service_id' => $service->id, 'severity' => 'critical']);
});

it('admin resolving incident sets resolved_at', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $incident = ServiceHealthIncident::create([
        'service_id'  => $service->id,
        'severity'    => 'warning',
        'title'       => 'Latency spike',
        'status'      => 'investigating',
        'created_by'  => $admin->id,
    ]);
    $this->actingAs($admin)
        ->patch(route('admin.service-health-incidents.update', $incident), ['status' => 'resolved'])
        ->assertRedirect();
    $this->assertDatabaseHas('service_health_incidents', ['id' => $incident->id, 'status' => 'resolved']);
    $this->assertNotNull($incident->fresh()->resolved_at);
});

it('admin incident store fails with invalid severity', function () {
    $admin = adminUser();
    $service = Service::factory()->create();
    $this->actingAs($admin)->post(route('admin.service-health-incidents.store'), [
        'service_id' => $service->id,
        'severity'   => 'catastrophic',
        'title'      => 'Test',
        'status'     => 'open',
    ])->assertSessionHasErrors('severity');
});

it('panel customer can view health incidents for own services', function () {
    $user = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);
    ServiceHealthIncident::create([
        'service_id' => $service->id,
        'severity'   => 'info',
        'title'      => 'Minor issue',
        'status'     => 'open',
    ]);
    $this->actingAs($user)
        ->get(route('panel.service-health-incidents.index'))
        ->assertOk()
        ->assertViewIs('panel.service-health-incidents.index');
});
