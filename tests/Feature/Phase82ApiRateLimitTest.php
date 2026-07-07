<?php

declare(strict_types=1);

use App\Domains\Api\Models\ApiTokenRateLimit;
use App\Domains\Api\Models\ApiUsageLog;
use App\Domains\Api\Services\ApiRateLimitService;

// ── Service tests ─────────────────────────────────────────────────────────────

it('usageForToken returns zero counts when no logs exist', function (): void {
    $service = new ApiRateLimitService();
    $usage   = $service->usageForToken(999);

    expect($usage['minute'])->toBe(0)
        ->and($usage['hour'])->toBe(0)
        ->and($usage['day'])->toBe(0);
});

it('usageForToken counts logs within time windows', function (): void {
    $tokenId = 42;
    $base    = ['token_id' => $tokenId, 'user_id' => null, 'endpoint' => 'v1/ping', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 10, 'ip_address' => '1.1.1.1'];

    // 3 within last minute
    foreach (range(1, 3) as $_) {
        \Illuminate\Support\Facades\DB::table('api_usage_logs')->insert(array_merge($base, ['created_at' => now()->subSeconds(30)->toDateTimeString()]));
    }
    // 2 more within last hour but not last minute
    foreach (range(1, 2) as $_) {
        \Illuminate\Support\Facades\DB::table('api_usage_logs')->insert(array_merge($base, ['created_at' => now()->subMinutes(30)->toDateTimeString()]));
    }
    // 1 more within last day but not last hour
    \Illuminate\Support\Facades\DB::table('api_usage_logs')->insert(array_merge($base, ['created_at' => now()->subHours(6)->toDateTimeString()]));

    $service = new ApiRateLimitService();
    $usage   = $service->usageForToken($tokenId);

    expect($usage['minute'])->toBe(3)
        ->and($usage['hour'])->toBe(5)
        ->and($usage['day'])->toBe(6);
});

it('limitsForToken returns defaults when no config exists', function (): void {
    $service = new ApiRateLimitService();
    $limits  = $service->limitsForToken(999);

    expect($limits['requests_per_minute'])->toBe(30)
        ->and($limits['requests_per_hour'])->toBe(500)
        ->and($limits['requests_per_day'])->toBe(5000);
});

it('limitsForToken returns configured limits when they exist', function (): void {
    ApiTokenRateLimit::create([
        'token_id'            => 77,
        'requests_per_minute' => 10,
        'requests_per_hour'   => 100,
        'requests_per_day'    => 1000,
    ]);

    $service = new ApiRateLimitService();
    $limits  = $service->limitsForToken(77);

    expect($limits['requests_per_minute'])->toBe(10)
        ->and($limits['requests_per_hour'])->toBe(100)
        ->and($limits['requests_per_day'])->toBe(1000);
});

it('statusForToken detects exceeded limits', function (): void {
    $tokenId = 55;

    ApiTokenRateLimit::create([
        'token_id'            => $tokenId,
        'requests_per_minute' => 2,
        'requests_per_hour'   => 100,
        'requests_per_day'    => 1000,
    ]);

    // 3 requests in last minute — exceeds limit of 2
    $base = ['token_id' => $tokenId, 'user_id' => null, 'endpoint' => 'v1/ping', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 10, 'ip_address' => '1.1.1.1'];
    foreach (range(1, 3) as $_) {
        \Illuminate\Support\Facades\DB::table('api_usage_logs')->insert(array_merge($base, ['created_at' => now()->subSeconds(20)->toDateTimeString()]));
    }

    $service = new ApiRateLimitService();
    $status  = $service->statusForToken($tokenId);

    expect($status['is_exceeded'])->toBeTrue()
        ->and($status['minute_pct'])->toBeGreaterThan(100);
});

it('statusForToken shows in_norm for low usage', function (): void {
    $tokenId = 66;

    $service = new ApiRateLimitService();
    $status  = $service->statusForToken($tokenId);

    expect($status['is_exceeded'])->toBeFalse()
        ->and($status['minute_pct'])->toBe(0.0);
});

it('setLimits creates new rate limit config', function (): void {
    $service = new ApiRateLimitService();
    $service->setLimits(33, [
        'requests_per_minute' => 15,
        'requests_per_hour'   => 200,
        'requests_per_day'    => 2000,
    ]);

    $cfg = ApiTokenRateLimit::where('token_id', 33)->first();
    expect($cfg)->not->toBeNull()
        ->and($cfg->requests_per_minute)->toBe(15);
});

it('setLimits updates existing config', function (): void {
    ApiTokenRateLimit::create([
        'token_id'            => 44,
        'requests_per_minute' => 5,
        'requests_per_hour'   => 50,
        'requests_per_day'    => 500,
    ]);

    $service = new ApiRateLimitService();
    $service->setLimits(44, [
        'requests_per_minute' => 20,
        'requests_per_hour'   => 300,
        'requests_per_day'    => 3000,
    ]);

    expect(ApiTokenRateLimit::where('token_id', 44)->count())->toBe(1);
    expect(ApiTokenRateLimit::where('token_id', 44)->value('requests_per_minute'))->toBe(20);
});

it('summary returns zero counts when no active tokens', function (): void {
    $service = new ApiRateLimitService();
    $summary = $service->summary();

    expect($summary['active_tokens'])->toBe(0)
        ->and($summary['exceeded_tokens'])->toBe(0)
        ->and($summary['near_limit_tokens'])->toBe(0);
});

// ── Admin routes ──────────────────────────────────────────────────────────────

it('admin can view api rate limits page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.api-rate-limits.index'))
        ->assertOk()
        ->assertSee('Rate Limit');
});

it('admin can set rate limits for a token', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.api-rate-limits.store'), [
            'token_id'            => 100,
            'requests_per_minute' => 20,
            'requests_per_hour'   => 300,
            'requests_per_day'    => 3000,
        ])
        ->assertRedirect(route('admin.api-rate-limits.index'));

    expect(ApiTokenRateLimit::where('token_id', 100)->exists())->toBeTrue();
});

it('validation rejects invalid token_id', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.api-rate-limits.store'), [
            'token_id'            => 0,
            'requests_per_minute' => 10,
            'requests_per_hour'   => 100,
            'requests_per_day'    => 1000,
        ])
        ->assertSessionHasErrors('token_id');
});

it('admin can delete rate limit config', function (): void {
    $admin = adminUser();
    $cfg   = ApiTokenRateLimit::create([
        'token_id'            => 200,
        'requests_per_minute' => 10,
        'requests_per_hour'   => 100,
        'requests_per_day'    => 1000,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.api-rate-limits.destroy', $cfg))
        ->assertRedirect(route('admin.api-rate-limits.index'));

    expect(ApiTokenRateLimit::find($cfg->id))->toBeNull();
});

it('non-admin cannot access rate limit routes', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.api-rate-limits.index'))
        ->assertStatus(403);
});
