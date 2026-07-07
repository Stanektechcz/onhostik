<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceConfigSnapshot;

it('admin can view service config snapshots', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($admin)
         ->get(route('admin.services.config-snapshots.index', $service))
         ->assertOk()
         ->assertViewIs('admin.service-config-snapshots');
});

it('admin can create a config snapshot', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($admin)
         ->post(route('admin.services.config-snapshots.store', $service), ['reason' => 'Before upgrade'])
         ->assertRedirect();

    expect(ServiceConfigSnapshot::where('service_id', $service->id)->exists())->toBeTrue();
});

it('snapshot stores the service config as JSON', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create([
        'customer_id' => $customer->customer->id,
        'resources'   => ['ram' => 2048, 'disk' => 10240],
    ]);

    $this->actingAs($admin)
         ->post(route('admin.services.config-snapshots.store', $service));

    $snapshot = ServiceConfigSnapshot::where('service_id', $service->id)->first();
    expect($snapshot->config)->toEqual(['ram' => 2048, 'disk' => 10240]);
});

it('snapshot records creator user id', function (): void {
    $admin    = adminUser();
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($admin)
         ->post(route('admin.services.config-snapshots.store', $service));

    $snapshot = ServiceConfigSnapshot::where('service_id', $service->id)->first();
    expect($snapshot->created_by)->toBe($admin->id);
});

it('customer cannot access config snapshots', function (): void {
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($customer)
         ->get(route('admin.services.config-snapshots.index', $service))
         ->assertForbidden();
});
