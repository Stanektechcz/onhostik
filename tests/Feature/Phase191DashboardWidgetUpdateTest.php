<?php

declare(strict_types=1);

use App\Models\DashboardWidget;

test('dashboard widgets update route exists', function (): void {
    expect(Route::has('panel.dashboard-widgets.update'))->toBeTrue();
});

test('dashboard widget update stores preferences', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->postJson(route('panel.dashboard-widgets.update'), [
        'widgets' => [
            ['key' => 'services', 'position' => 0, 'is_visible' => true],
            ['key' => 'invoices', 'position' => 1, 'is_visible' => false],
        ],
    ]);
    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    expect(DashboardWidget::where('user_id', $user->id)->where('widget_key', 'invoices')->where('is_visible', false)->exists())->toBeTrue();
});

test('dashboard widget update validates widget keys', function (): void {
    $user = customerUser();
    $response = $this->actingAs($user)->postJson(route('panel.dashboard-widgets.update'), [
        'widgets' => [
            ['key' => 'invalid_key', 'position' => 0, 'is_visible' => true],
        ],
    ]);
    $response->assertStatus(422);
});

test('dashboard widget update requires authentication', function (): void {
    $response = $this->postJson(route('panel.dashboard-widgets.update'), ['widgets' => []]);
    $response->assertStatus(401);
});

test('dashboard widget model casts settings to array', function (): void {
    $widget = new DashboardWidget(['settings' => ['color' => 'blue']]);
    expect($widget->settings)->toBeArray();
});
