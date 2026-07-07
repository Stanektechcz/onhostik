<?php

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceResourceSnapshot;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view resource snapshots index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-resource-snapshots.index'))
        ->assertOk()
        ->assertViewHas('snapshots');
});

it('admin can record a snapshot for a service', function (): void {
    $service = Service::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.service-resource-snapshots.store', $service), [
            'disk_gb'      => 10,
            'bandwidth_gb' => 50,
            'cpu_percent'  => 25,
            'ram_mb'       => 512,
        ])
        ->assertRedirect();

    expect(ServiceResourceSnapshot::where('service_id', $service->id)->count())->toBe(1);
});

it('customer can view resource snapshot history for their service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->get(route('panel.service-snapshots.show', $service))
        ->assertOk()
        ->assertViewHas('snapshots');
});

it('customer cannot view snapshots for another customers service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->get(route('panel.service-snapshots.show', $service))
        ->assertForbidden();
});

it('cpu_percent must not exceed 100', function (): void {
    $service = Service::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.service-resource-snapshots.store', $service), [
            'cpu_percent' => 150,
        ])
        ->assertSessionHasErrors('cpu_percent');
});
