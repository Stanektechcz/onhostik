<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view service pin page', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $user->customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-pins.show', $service))
        ->assertOk()
        ->assertViewHas('service')
        ->assertViewHas('pin');
});

it('panel user can set a service pin', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $user->customer->id,
    ]);

    $this->actingAs($user)
        ->post(route('panel.service-pins.store', $service), [
            'pin'              => '1234',
            'pin_confirmation' => '1234',
        ])
        ->assertRedirect();

    expect(\App\Models\ServicePin::count())->toBe(1);
});

it('pin is stored as hash not plaintext', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $user->customer->id,
    ]);

    $this->actingAs($user)
        ->post(route('panel.service-pins.store', $service), [
            'pin'              => '1234',
            'pin_confirmation' => '1234',
        ]);

    expect(\App\Models\ServicePin::first()->pin_hash)->not->toBe('1234');
});

it('guest cannot access service pin', function (): void {
    $this->get(route('panel.service-pins.show', 1))
        ->assertRedirect();
});

it('customer cannot access another customer service pin', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'customer_id' => $otherUser->customer->id,
    ]);

    $this->actingAs($user)
        ->get(route('panel.service-pins.show', $service))
        ->assertForbidden();
});
