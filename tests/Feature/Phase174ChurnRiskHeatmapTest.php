<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ChurnRiskHeatmapController;

test('churn risk heatmap controller exists', function (): void {
    expect(class_exists(ChurnRiskHeatmapController::class))->toBeTrue();
});

test('churn risk heatmap route exists', function (): void {
    expect(Route::has('admin.metrics.churn-heatmap'))->toBeTrue();
});

test('churn risk heatmap requires authentication', function (): void {
    $response = $this->get(route('admin.metrics.churn-heatmap'));
    $response->assertRedirect();
});

test('churn risk heatmap loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.metrics.churn-heatmap'));
    $response->assertOk();
});

test('churn risk heatmap view contains segment info', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.metrics.churn-heatmap'));
    $response->assertSee('Heatmapa');
});
