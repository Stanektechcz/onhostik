<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view customer merges list', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.customer-merges.index'))
        ->assertOk()
        ->assertViewHas('merges');
});

it('admin can create a customer merge request', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.customer-merges.store'), [
            'primary_customer_id' => 1,
            'merged_customer_id'  => 2,
            'note'                => 'Duplicate account',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('customer_merges', ['primary_customer_id' => 1]);
});

it('merge request is created with pending status', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.customer-merges.store'), [
            'primary_customer_id' => 1,
            'merged_customer_id'  => 2,
            'note'                => 'Duplicate account',
        ]);

    expect(\App\Models\CustomerMerge::first()->status)->toBe('pending');
});

it('guest cannot access customer merges', function (): void {
    $this->get(route('admin.customer-merges.index'))
        ->assertRedirect();
});

it('store validates primary and merged customer differ', function (): void {
    $this->actingAs(adminUser())
        ->post(route('admin.customer-merges.store'), [
            'primary_customer_id' => 1,
            'merged_customer_id'  => 1,
            'note'                => 'Same account',
        ])
        ->assertSessionHasErrors('merged_customer_id');
});
