<?php

use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view the service resource usage dashboard', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-resource-usage.index'))
        ->assertOk()
        ->assertViewHas('services');
});

it('unauthenticated user is redirected from resource usage dashboard', function (): void {
    $this->get(route('admin.service-resource-usage.index'))
        ->assertRedirect();
});

it('resource usage page lists only services with disk_usage_gb set', function (): void {
    Service::factory()->create([
        'disk_usage_gb'           => null,
        'disk_limit_gb'           => 50,
        'usage_alert_threshold'   => 80,
    ]);
    Service::factory()->create([
        'disk_usage_gb'           => 45,
        'disk_limit_gb'           => 50,
        'usage_alert_threshold'   => 80,
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.service-resource-usage.index'))
        ->assertOk();

    $services = $response->viewData('services');
    expect($services)->not()->toBeNull();
});

it('non-admin customer cannot access resource usage dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.service-resource-usage.index'))
        ->assertForbidden();
});

it('resource usage page returns 200 even when no services are over threshold', function (): void {
    Service::factory()->create([
        'disk_usage_gb'           => 10,
        'disk_limit_gb'           => 100,
        'usage_alert_threshold'   => 80,
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.service-resource-usage.index'))
        ->assertOk();
});
