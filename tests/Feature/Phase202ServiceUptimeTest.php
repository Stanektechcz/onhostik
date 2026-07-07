<?php

use App\Domains\Provisioning\Models\Service;
use App\Models\ServiceUptimeCheck;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view service uptime index', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.service-uptime.index'))
        ->assertOk()
        ->assertViewHas('services');
});

it('admin can view uptime detail for a service', function (): void {
    $service = Service::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('admin.service-uptime.show', $service))
        ->assertOk()
        ->assertViewHas('service')
        ->assertViewHas('checks');
});

it('admin can record a new uptime check', function (): void {
    $service = Service::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.service-uptime.store', $service), [
            'check_type'  => 'http',
            'target'      => 'https://example.com',
            'is_up'       => true,
            'response_ms' => 150,
        ])
        ->assertRedirect();

    expect(ServiceUptimeCheck::where('service_id', $service->id)->count())->toBe(1);
});

it('customer can view uptime for their own service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->get(route('panel.services.uptime.show', $service))
        ->assertOk()
        ->assertViewHas('checks');
});

it('customer cannot view uptime of another customers service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->get(route('panel.services.uptime.show', $service))
        ->assertForbidden();
});
