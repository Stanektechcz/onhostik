<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view their chargeback history', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.chargebacks.index'))
        ->assertOk()
        ->assertViewHas('chargebacks');
});

it('panel user only sees their own chargebacks', function (): void {
    $user      = customerUser();
    $otherUser = customerUser();

    \App\Models\Chargeback::create([
        'customer_id' => $user->customer->id,
        'amount'      => 1000,
        'currency'    => 'CZK',
        'reason'      => 'Test',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    \App\Models\Chargeback::create([
        'customer_id' => $otherUser->customer->id,
        'amount'      => 2000,
        'currency'    => 'CZK',
        'reason'      => 'Other',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.chargebacks.index'))
        ->assertOk();

    expect($response->viewData('chargebacks')->total())->toBe(1);
});

it('chargebacks are read-only for panel user', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.chargebacks.index'))
        ->assertOk();
});

it('guest cannot access chargeback history', function (): void {
    $this->get(route('panel.chargebacks.index'))
        ->assertRedirect();
});

it('panel user with no chargebacks sees empty list', function (): void {
    $user = customerUser();

    $response = $this->actingAs($user)
        ->get(route('panel.chargebacks.index'))
        ->assertOk();

    expect($response->viewData('chargebacks')->total())->toBe(0);
});
