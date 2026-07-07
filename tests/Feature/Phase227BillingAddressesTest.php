<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view billing addresses', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.billing-addresses.index'))
        ->assertOk()
        ->assertViewHas('addresses');
});

it('panel user can create a billing address', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.billing-addresses.store'), [
            'label'        => 'Firma',
            'street'       => 'Hlavní 1',
            'city'         => 'Praha',
            'postal_code'  => '11000',
            'country_code' => 'CZ',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('billing_addresses', ['city' => 'Praha']);
});

it('creating first address sets it as default', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.billing-addresses.store'), [
            'label'        => 'Firma',
            'street'       => 'Hlavní 1',
            'city'         => 'Praha',
            'postal_code'  => '11000',
            'country_code' => 'CZ',
            'is_default'   => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('billing_addresses', ['is_default' => true]);
});

it('panel user cannot view billing addresses without customer', function (): void {
    $this->actingAs(\App\Models\User::factory()->create())
        ->get(route('panel.billing-addresses.index'))
        ->assertForbidden();
});

it('guest cannot access billing addresses', function (): void {
    $this->get(route('panel.billing-addresses.index'))
        ->assertRedirect();
});
