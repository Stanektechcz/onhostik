<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view chargeback analytics', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.chargeback-analytics.index'))
        ->assertOk()
        ->assertViewHas('stats');
});

it('analytics shows correct total count', function (): void {
    $admin = adminUser();

    \App\Models\Chargeback::create([
        'customer_id' => 1,
        'amount'      => 1000,
        'currency'    => 'CZK',
        'reason'      => 'R',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    \App\Models\Chargeback::create([
        'customer_id' => 1,
        'amount'      => 2000,
        'currency'    => 'CZK',
        'reason'      => 'R2',
        'status'      => 'received',
        'received_at' => now()->format('Y-m-d'),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.chargeback-analytics.index'))
        ->assertOk();

    expect($response->viewData('totalCount'))->toBe(2);
});

it('analytics shows recent chargebacks', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.chargeback-analytics.index'))
        ->assertOk()
        ->assertViewHas('recentChargebacks');
});

it('analytics shows total amount', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.chargeback-analytics.index'))
        ->assertOk()
        ->assertViewHas('totalAmount');
});

it('guest cannot access chargeback analytics', function (): void {
    $this->get(route('admin.chargeback-analytics.index'))
        ->assertRedirect();
});
