<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\RevenueByCountryController;

test('revenue by country controller exists', function (): void {
    expect(class_exists(RevenueByCountryController::class))->toBeTrue();
});

test('revenue by country route exists', function (): void {
    expect(Route::has('admin.metrics.revenue-by-country'))->toBeTrue();
});

test('revenue by country requires admin', function (): void {
    $response = $this->get(route('admin.metrics.revenue-by-country'));
    $response->assertRedirect();
});

test('revenue by country loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.metrics.revenue-by-country'));
    $response->assertOk();
});

test('revenue by country view shows table', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.metrics.revenue-by-country'));
    $response->assertSee('zem', false);
});
