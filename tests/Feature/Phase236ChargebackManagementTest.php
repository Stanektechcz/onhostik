<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view chargebacks list', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.chargebacks.index'))
        ->assertOk()
        ->assertViewHas('chargebacks');
});

it('admin can update chargeback status', function (): void {
    $chargeback = \App\Models\Chargeback::create([
        'customer_id' => 1,
        'amount'      => 5000,
        'currency'    => 'CZK',
        'reason'      => 'Fraud',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.chargebacks.update', $chargeback), [
            'status'     => 'under_review',
            'admin_note' => 'Investigating',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('chargebacks', ['status' => 'under_review']);
});

it('admin can filter chargebacks by status', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.chargebacks.index', ['status' => 'received']))
        ->assertOk();
});

it('guest cannot access chargebacks', function (): void {
    $this->get(route('admin.chargebacks.index'))
        ->assertRedirect();
});

it('update validates status enum', function (): void {
    $chargeback = \App\Models\Chargeback::create([
        'customer_id' => 1,
        'amount'      => 5000,
        'currency'    => 'CZK',
        'reason'      => 'Fraud',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    $this->actingAs(adminUser())
        ->patch(route('admin.chargebacks.update', $chargeback), [
            'status' => 'invalid_status',
        ])
        ->assertSessionHasErrors('status');
});
