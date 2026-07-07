<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ApiRateLimitConfigController;
use App\Models\ApiRateLimitConfig;

test('api rate limit config controller exists', function (): void {
    expect(class_exists(ApiRateLimitConfigController::class))->toBeTrue();
});

test('api rate limit configs table exists', function (): void {
    expect(\Schema::hasTable('api_rate_limit_configs'))->toBeTrue();
});

test('api rate limit index route exists', function (): void {
    expect(Route::has('admin.api-rate-limit.index'))->toBeTrue();
});

test('api rate limit config can be created', function (): void {
    $config = ApiRateLimitConfig::create([
        'customer_id'         => null,
        'scope'               => 'global',
        'requests_per_minute' => 60,
        'requests_per_day'    => 10000,
        'is_active'           => true,
    ]);
    expect($config->requests_per_minute)->toBe(60);
});

test('api rate limit index loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.api-rate-limit.index'));
    $response->assertOk();
});
