<?php

declare(strict_types=1);

it('admin can view product category revenue page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.metrics.product-categories'))
         ->assertOk()
         ->assertViewIs('admin.product-category-revenue');
});

it('product category revenue page provides rows data', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.product-categories'))
         ->assertOk();

    $response->assertViewHas('rows');
});

it('product category revenue works with no invoices', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.product-categories'))
         ->assertOk();

    $rows = $response->viewData('rows');
    expect($rows)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

it('product category revenue returns total revenue', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.metrics.product-categories'))
         ->assertOk();

    $response->assertViewHas('totalRevenue');
});

it('customer cannot access product category revenue', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.metrics.product-categories'))
         ->assertForbidden();
});
