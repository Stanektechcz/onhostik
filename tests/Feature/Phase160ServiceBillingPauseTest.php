<?php

declare(strict_types=1);

use App\Domains\Provisioning\Models\Service;

it('customer can request billing pause for own service', function (): void {
    $customer = customerUser();
    $service  = Service::factory()->create(['customer_id' => $customer->customer->id]);

    $this->actingAs($customer)
         ->post(route('panel.services.billing-pause.store', $service))
         ->assertRedirect();

    expect($service->fresh()->billing_pause_requested_at)->not->toBeNull();
});

it('customer cannot request billing pause for another customer service', function (): void {
    $customer1 = customerUser();
    $customer2 = customerUser();
    $service   = Service::factory()->create(['customer_id' => $customer1->customer->id]);

    $this->actingAs($customer2)
         ->post(route('panel.services.billing-pause.store', $service))
         ->assertForbidden();
});

it('admin can view pending billing pause requests', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    Service::factory()->create([
        'customer_id'                 => $customer->customer->id,
        'billing_pause_requested_at'  => now(),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.service-billing-pause.index'))
         ->assertOk()
         ->assertViewIs('admin.service-billing-pause');
});

it('admin can approve billing pause', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $service = Service::factory()->create([
        'customer_id'                => $customer->customer->id,
        'billing_pause_requested_at' => now(),
    ]);

    $this->actingAs($admin)
         ->post(route('admin.service-billing-pause.approve', $service), [
             'paused_until' => now()->addMonths(2)->toDateString(),
         ])
         ->assertRedirect();

    expect($service->fresh()->billing_paused_until)->not->toBeNull();
    expect($service->fresh()->billing_pause_requested_at)->toBeNull();
});

it('admin can reject billing pause request', function (): void {
    $admin    = adminUser();
    $customer = customerUser();

    $service = Service::factory()->create([
        'customer_id'                => $customer->customer->id,
        'billing_pause_requested_at' => now(),
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.service-billing-pause.reject', $service))
         ->assertRedirect();

    expect($service->fresh()->billing_pause_requested_at)->toBeNull();
});
