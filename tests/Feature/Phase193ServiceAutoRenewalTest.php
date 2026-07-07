<?php

use App\Domains\Provisioning\Models\Service;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can enable auto renewal on their service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->patch(route('panel.services.auto-renewal.update', $service), [
            'auto_renew'          => true,
            'renewal_notice_days' => 7,
        ])
        ->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeTrue();
});

it('customer can disable auto renewal', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'auto_renew'  => true,
    ]);

    $this->actingAs($user)
        ->patch(route('panel.services.auto-renewal.update', $service), [
            'auto_renew'          => false,
            'renewal_notice_days' => 7,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($service->fresh()->auto_renew)->toBeFalse();
});

it('customer cannot update auto renewal on another customer service', function (): void {
    $user    = customerUser();
    $other   = \App\Domains\Customer\Models\Customer::factory()->create();
    $service = Service::factory()->create(['customer_id' => $other->id]);

    $this->actingAs($user)
        ->patch(route('panel.services.auto-renewal.update', $service), [
            'auto_renew'          => true,
            'renewal_notice_days' => 5,
        ])
        ->assertForbidden();
});

it('renewal notice days must be between 1 and 30', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->patch(route('panel.services.auto-renewal.update', $service), [
            'auto_renew'          => true,
            'renewal_notice_days' => 0,
        ])
        ->assertSessionHasErrors('renewal_notice_days');
});

it('renewal_notice_days is persisted', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->patch(route('panel.services.auto-renewal.update', $service), [
            'auto_renew'          => true,
            'renewal_notice_days' => 14,
        ]);

    expect($service->fresh()->renewal_notice_days)->toBe(14);
});
