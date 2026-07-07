<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view voucher analytics', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.voucher-analytics.index'))
        ->assertOk()
        ->assertViewHas('totalVouchers');
});

it('analytics shows correct total voucher count', function (): void {
    \App\Models\Voucher::create([
        'code'       => 'VOUCHA',
        'type'       => 'credit',
        'value'      => 100,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    \App\Models\Voucher::create([
        'code'       => 'VOUCHB',
        'type'       => 'discount_percent',
        'value'      => 10,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.voucher-analytics.index'))
        ->assertOk();

    expect($response->viewData('totalVouchers'))->toBe(2);
});

it('analytics shows active count', function (): void {
    \App\Models\Voucher::create([
        'code'       => 'ACTIVE1',
        'type'       => 'credit',
        'value'      => 200,
        'used_count' => 0,
        'is_active'  => true,
    ]);

    \App\Models\Voucher::create([
        'code'       => 'INACTIVE1',
        'type'       => 'credit',
        'value'      => 200,
        'used_count' => 0,
        'is_active'  => false,
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.voucher-analytics.index'))
        ->assertOk();

    expect($response->viewData('activeVouchers'))->toBe(1);
});

it('analytics shows total usage', function (): void {
    \App\Models\Voucher::create([
        'code'       => 'USED5',
        'type'       => 'credit',
        'value'      => 50,
        'used_count' => 5,
        'is_active'  => true,
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.voucher-analytics.index'))
        ->assertOk();

    expect($response->viewData('totalUsage'))->toBeGreaterThanOrEqual(5);
});

it('guest cannot access voucher analytics', function (): void {
    $this->get(route('admin.voucher-analytics.index'))
        ->assertRedirect();
});
