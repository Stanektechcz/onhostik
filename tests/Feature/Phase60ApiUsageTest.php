<?php

declare(strict_types=1);

use App\Domains\Api\Models\ApiUsageLog;
use App\Models\User;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ApiUsageLog model ──────────────────────────────────────────────────────────

it('ApiUsageLog has no updated_at column', function (): void {
    $cols = Schema::getColumnListing('api_usage_logs');
    expect($cols)->toContain('created_at')
        ->and($cols)->not->toContain('updated_at');
});

it('ApiUsageLog isError() returns true for status >= 400', function (): void {
    $log = new ApiUsageLog(['status_code' => 404]);
    expect($log->isError())->toBeTrue();

    $ok = new ApiUsageLog(['status_code' => 200]);
    expect($ok->isError())->toBeFalse();
});

it('ApiUsageLog stores all fields correctly', function (): void {
    $user = customerUser();

    ApiUsageLog::create([
        'user_id'          => $user->id,
        'token_id'         => null,
        'endpoint'         => 'v1/profile',
        'method'           => 'GET',
        'status_code'      => 200,
        'response_time_ms' => 45,
        'ip_address'       => '127.0.0.1',
    ]);

    $log = ApiUsageLog::first();
    expect($log->endpoint)->toBe('v1/profile')
        ->and($log->status_code)->toBe(200)
        ->and($log->user_id)->toBe($user->id);
});

// ── Middleware integration ─────────────────────────────────────────────────────

it('API v1 requests are logged by middleware', function (): void {
    $user  = customerUser();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->getJson('/api/v1/profile')
        ->assertOk();

    expect(ApiUsageLog::count())->toBeGreaterThan(0);

    $log = ApiUsageLog::latest('id')->first();
    expect($log->endpoint)->toContain('v1/profile')
        ->and($log->method)->toBe('GET')
        ->and($log->status_code)->toBe(200)
        ->and($log->user_id)->toBe($user->id);
});

it('API v1 response time is recorded in log', function (): void {
    $user  = customerUser();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->getJson('/api/v1/profile')
        ->assertOk();

    $log = ApiUsageLog::latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->response_time_ms)->toBeGreaterThanOrEqual(0);
});

it('unauthenticated API requests are not logged', function (): void {
    $this->getJson('/api/v1/profile')->assertStatus(401);

    expect(ApiUsageLog::count())->toBe(0);
});

it('webhook endpoints do not create log entries', function (): void {
    // Webhooks are outside the v1/v2 auth:sanctum groups — middleware does not run
    $initialCount = ApiUsageLog::count();
    $this->postJson('/api/webhooks/comgate', []);

    expect(ApiUsageLog::count())->toBe($initialCount);
});

// ── Admin dashboard ────────────────────────────────────────────────────────────

it('admin api-usage dashboard is accessible', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.api-usage.index'))
        ->assertOk()
        ->assertSee('API Usage');
});

it('admin api-usage dashboard is forbidden for regular users', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.api-usage.index'))
        ->assertStatus(403);
});

it('admin api-usage dashboard shows correct totals', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/profile', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 50]);
    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/invoices', 'method' => 'GET', 'status_code' => 200, 'response_time_ms' => 100]);
    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/services', 'method' => 'GET', 'status_code' => 403, 'response_time_ms' => 20]);

    $this->actingAs($admin)
        ->get(route('admin.api-usage.index'))
        ->assertOk()
        ->assertSee('3'); // total
});

it('admin api-usage dashboard shows error rate', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/profile', 'method' => 'GET', 'status_code' => 200]);
    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/profile', 'method' => 'GET', 'status_code' => 500]);

    $response = $this->actingAs($admin)
        ->get(route('admin.api-usage.index'))
        ->assertOk();

    // 1 error out of 2 = 50%
    $response->assertSee('50');
});

it('admin api-usage dashboard works with empty data', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.api-usage.index'))
        ->assertOk()
        ->assertSee('0');
});

it('admin api-usage shows top endpoints', function (): void {
    $admin = adminUser();
    $user  = customerUser();

    for ($i = 0; $i < 5; $i++) {
        ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/profile', 'method' => 'GET', 'status_code' => 200]);
    }
    ApiUsageLog::create(['user_id' => $user->id, 'endpoint' => 'v1/invoices', 'method' => 'GET', 'status_code' => 200]);

    $this->actingAs($admin)
        ->get(route('admin.api-usage.index'))
        ->assertOk()
        ->assertSee('v1/profile');
});
