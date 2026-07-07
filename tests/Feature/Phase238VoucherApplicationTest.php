<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view voucher application page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.voucher-application.index'))
        ->assertOk();
});

it('panel user can apply a valid voucher', function (): void {
    $user = customerUser();

    \App\Models\Voucher::create([
        'code'       => 'SAVE10',
        'type'       => 'discount_percent',
        'value'      => 10,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    $this->actingAs($user)
        ->post(route('panel.voucher-application.store'), [
            'code' => 'SAVE10',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');
});

it('applying voucher increments used count', function (): void {
    $user = customerUser();

    \App\Models\Voucher::create([
        'code'       => 'SAVE10',
        'type'       => 'discount_percent',
        'value'      => 10,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    $this->actingAs($user)
        ->post(route('panel.voucher-application.store'), [
            'code' => 'SAVE10',
        ]);

    expect(\App\Models\Voucher::first()->used_count)->toBe(1);
});

it('applying invalid voucher code shows error', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.voucher-application.store'), [
            'code' => 'INVALID_CODE',
        ])
        ->assertSessionHasErrors('code');
});

it('guest cannot apply vouchers', function (): void {
    $this->post(route('panel.voucher-application.store'), [
        'code' => 'SAVE10',
    ])
        ->assertRedirect();
});
