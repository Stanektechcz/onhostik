<?php

use App\Domains\Customer\Models\Customer;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view credit transfer page', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.credit-transfer.index'))
        ->assertOk()
        ->assertViewHas('customers');
});

it('credit transfer requires valid customer ids', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.credit-transfer.transfer'), [
            'from_customer_id' => 99999,
            'to_customer_id'   => 99998,
            'amount_haler'     => 1000,
        ])
        ->assertSessionHasErrors('from_customer_id');
});

it('from and to customer must differ', function (): void {
    $customer = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.credit-transfer.transfer'), [
            'from_customer_id' => $customer->id,
            'to_customer_id'   => $customer->id,
            'amount_haler'     => 1000,
        ])
        ->assertSessionHasErrors();
});

it('amount must be at least 100 haler', function (): void {
    $from = Customer::factory()->create();
    $to   = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.credit-transfer.transfer'), [
            'from_customer_id' => $from->id,
            'to_customer_id'   => $to->id,
            'amount_haler'     => 50,
        ])
        ->assertSessionHasErrors('amount_haler');
});

it('transfer fails when source has insufficient credit', function (): void {
    $from = Customer::factory()->create();
    $to   = Customer::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.credit-transfer.transfer'), [
            'from_customer_id' => $from->id,
            'to_customer_id'   => $to->id,
            'amount_haler'     => 100000,
        ])
        ->assertSessionHasErrors('amount_haler');
});
