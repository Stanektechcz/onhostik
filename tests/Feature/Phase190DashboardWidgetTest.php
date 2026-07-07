<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\DashboardWidgetController;
use App\Models\DashboardWidget;

test('dashboard widget controller exists', function (): void {
    expect(class_exists(DashboardWidgetController::class))->toBeTrue();
});

test('dashboard widget model exists', function (): void {
    expect(class_exists(DashboardWidget::class))->toBeTrue();
});

test('dashboard widgets table exists', function (): void {
    expect(\Schema::hasTable('dashboard_widgets'))->toBeTrue();
});

test('dashboard widgets index route exists', function (): void {
    expect(Route::has('panel.dashboard-widgets.index'))->toBeTrue();
});

test('dashboard widgets index loads for customer', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->get(route('panel.dashboard-widgets.index'));
    $response->assertOk();
});
