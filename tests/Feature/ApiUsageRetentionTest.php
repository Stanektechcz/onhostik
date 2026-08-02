<?php

declare(strict_types=1);

use App\Domains\Api\Models\ApiUsageLog;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * API-usage telemetry is high-growth; raw rows expire via retention:apply while
 * analytics keep their aggregates (audit 500 #24).
 */

it('prunes api usage logs older than the retention window', function (): void {
    config(['retention.policies.api_usage_logs.days' => 90]);

    $old = ApiUsageLog::create(['endpoint' => 'v1/old', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 5, 'ip_address' => '127.0.0.1']);
    ApiUsageLog::where('id', $old->id)->update(['created_at' => now()->subDays(200)]);

    ApiUsageLog::create(['endpoint' => 'v1/recent', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 5, 'ip_address' => '127.0.0.1']);

    $this->artisan('retention:apply')->assertExitCode(0);

    expect(ApiUsageLog::count())->toBe(1)
        ->and(ApiUsageLog::where('endpoint', 'v1/recent')->exists())->toBeTrue();
});

it('keeps recent api usage logs and reports without deleting in dry-run', function (): void {
    config(['retention.policies.api_usage_logs.days' => 90]);

    $old = ApiUsageLog::create(['endpoint' => 'v1/old', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 5, 'ip_address' => '127.0.0.1']);
    ApiUsageLog::where('id', $old->id)->update(['created_at' => now()->subDays(200)]);

    $this->artisan('retention:apply', ['--dry-run' => true])->assertExitCode(0);

    expect(ApiUsageLog::count())->toBe(1); // dry-run deleted nothing
});
