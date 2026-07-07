<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can access revenue comparison dashboard', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.metrics.revenue-comparison'))
        ->assertOk()
        ->assertViewIs('admin.revenue-comparison.index');
});

it('revenue comparison view has current month data', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.metrics.revenue-comparison'))
        ->assertViewHas('currentMonth');
});

it('revenue comparison view has previous month data', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.metrics.revenue-comparison'))
        ->assertViewHas('previousMonth');
});

it('revenue comparison view has year over year data', function () {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.metrics.revenue-comparison'))
        ->assertViewHas('currentYear')
        ->assertViewHas('previousYear');
});

it('guest is redirected from revenue comparison', function () {
    $this->get(route('admin.metrics.revenue-comparison'))->assertRedirect();
});
