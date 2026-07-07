<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view vouchers list', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.vouchers.index'))
        ->assertOk()
        ->assertViewHas('vouchers');
});

it('admin can create a voucher', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.vouchers.store'), [
            'code'      => 'SAVE20',
            'type'      => 'discount_percent',
            'value'     => 20,
            'is_active' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('vouchers', ['code' => 'SAVE20']);
});

it('admin can toggle voucher active status', function (): void {
    $voucher = \App\Models\Voucher::create([
        'code'       => 'TOGV',
        'type'       => 'credit',
        'value'      => 500,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.vouchers.update', $voucher), [
            'code'      => 'TOGV',
            'type'      => 'credit',
            'value'     => 500,
            'is_active' => 0,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('vouchers', ['is_active' => false]);
});

it('guest cannot access vouchers', function (): void {
    $this->get(route('admin.vouchers.index'))
        ->assertRedirect();
});

it('store rejects duplicate voucher code', function (): void {
    \App\Models\Voucher::create([
        'code'       => 'DUP',
        'type'       => 'credit',
        'value'      => 100,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    $this->actingAs(adminUser())
        ->post(route('admin.vouchers.store'), [
            'code'      => 'DUP',
            'type'      => 'credit',
            'value'     => 100,
            'is_active' => 1,
        ])
        ->assertSessionHasErrors('code');
});
