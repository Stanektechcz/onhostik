<?php

declare(strict_types=1);

it('admin can view vat summary page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.vat-summary'))
         ->assertOk()
         ->assertViewIs('admin.vat-summary');
});

it('vat summary accepts year parameter', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.vat-summary', ['year' => 2024]))
         ->assertOk()
         ->assertViewHas('year', 2024);
});

it('vat summary defaults to current year', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.vat-summary'))
         ->assertOk()
         ->assertViewHas('year', now()->year);
});

it('vat summary view contains byMonth and scenarios data', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.vat-summary'));

    $response->assertViewHas('byMonth')
             ->assertViewHas('scenarios')
             ->assertViewHas('totals');
});

it('customer cannot view vat summary', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.metrics.vat-summary'))
         ->assertForbidden();
});
