<?php

use App\Domains\Reseller\Models\ResellerProfile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view revenue by reseller report', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.revenue-by-reseller.index'))
        ->assertOk()
        ->assertViewHas('revenueByReseller');
});

it('report returns from and to date bounds', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.revenue-by-reseller.index') . '?from=2026-01-01&to=2026-01-31')
        ->assertOk();

    expect($response->viewData('from'))->toBe('2026-01-01');
    expect($response->viewData('to'))->toBe('2026-01-31');
});

it('report includes reseller with no revenue as zero', function (): void {
    ResellerProfile::factory()->create();

    $response = $this->actingAs(adminUser())
        ->get(route('admin.revenue-by-reseller.index'))
        ->assertOk();

    $rows = $response->viewData('revenueByReseller');
    expect($rows)->toHaveCount(1);
    expect($rows[0]['revenue'])->toBe(0.0);
});

it('report is sorted by revenue descending', function (): void {
    $response = $this->actingAs(adminUser())
        ->get(route('admin.revenue-by-reseller.index'))
        ->assertOk();

    $rows = $response->viewData('revenueByReseller');
    for ($i = 1; $i < count($rows); $i++) {
        expect($rows[$i - 1]['revenue'])->toBeGreaterThanOrEqual($rows[$i]['revenue']);
    }
});

it('guest cannot access revenue by reseller report', function (): void {
    $this->get(route('admin.revenue-by-reseller.index'))
        ->assertRedirect();
});
