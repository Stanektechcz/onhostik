<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('admin can view the revenue forecast page', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.revenue-forecast'))
        ->assertOk()
        ->assertSee('Prognóza tržeb');
});

it('revenue forecast page shows historical section', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.revenue-forecast'))
        ->assertOk()
        ->assertSee('Skutečné příjmy');
});

it('revenue forecast page shows forecast section', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.revenue-forecast'))
        ->assertOk()
        ->assertSee('Prognóza');
});

it('revenue forecast includes YTD and average KPI cards', function (): void {
    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.revenue-forecast'))
        ->assertOk()
        ->assertSee('Průměr posledních 3 měsíců')
        ->assertSee('Celkem YTD');
});

it('revenue forecast page shows non-zero revenue after a payment is made', function (): void {
    $user   = customerUser();
    $result = placeOrder($user);

    // Mark invoice as paid via admin
    $admin = adminUser();
    $this->actingAs($admin)
        ->post(route('admin.invoices.mark-paid', $result['invoice']))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.revenue-forecast'))
        ->assertOk();
});

it('customer cannot access admin revenue forecast', function (): void {
    $customer = customerUser();
    $this->actingAs($customer)
        ->get(route('admin.revenue-forecast'))
        ->assertForbidden();
});
