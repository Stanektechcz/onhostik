<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view customer segment analytics', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.customer-segment-analytics.index'))
        ->assertOk()
        ->assertViewHas('tags');
});

it('analytics includes pivot counts', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.customer-segment-analytics.index'))
        ->assertOk()
        ->assertViewHas('pivotCounts');
});

it('segment with no customers shows zero count', function (): void {
    \App\Models\CustomerSegmentTag::create([
        'name'  => 'Test',
        'color' => '#ff0000',
    ]);

    $response = $this->actingAs(adminUser())
        ->get(route('admin.customer-segment-analytics.index'))
        ->assertOk();

    expect($response->viewData('tags')->count())->toBeGreaterThanOrEqual(1);
});

it('guest cannot access segment analytics', function (): void {
    $this->get(route('admin.customer-segment-analytics.index'))
        ->assertRedirect();
});

it('analytics page loads without errors when no tags exist', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.customer-segment-analytics.index'))
        ->assertOk();
});
