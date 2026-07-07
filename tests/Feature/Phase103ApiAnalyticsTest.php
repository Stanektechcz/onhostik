<?php

declare(strict_types=1);

use App\Domains\Api\Models\ApiUsageLog;

// ── Admin API analytics page ──────────────────────────────────────────────────

it('admin can view the API analytics page with no data', function (): void {
    ApiUsageLog::query()->delete();

    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.api-analytics'))
        ->assertOk()
        ->assertSee('API Token Usage Analytics');
});

it('admin analytics page shows total request counts', function (): void {
    ApiUsageLog::query()->delete();

    $admin = adminUser();
    ApiUsageLog::create([
        'user_id'          => $admin->id,
        'token_id'         => 1,
        'endpoint'         => 'api/v1/profile',
        'method'           => 'GET',
        'status_code'      => 200,
        'response_time_ms' => 45,
        'ip_address'       => '127.0.0.1',
    ]);

    ApiUsageLog::create([
        'user_id'          => $admin->id,
        'token_id'         => 1,
        'endpoint'         => 'api/v1/invoices',
        'method'           => 'GET',
        'status_code'      => 404,
        'response_time_ms' => 10,
        'ip_address'       => '127.0.0.1',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.api-analytics'))
        ->assertOk()
        ->assertSee('api/v1/profile')
        ->assertSee('api/v1/invoices');
});

it('admin analytics shows top endpoints from last 7 days', function (): void {
    ApiUsageLog::query()->delete();

    $admin = adminUser();

    foreach (range(1, 5) as $i) {
        ApiUsageLog::create([
            'user_id'          => $admin->id,
            'token_id'         => 1,
            'endpoint'         => 'api/v1/services',
            'method'           => 'GET',
            'status_code'      => 200,
            'response_time_ms' => 30,
            'ip_address'       => '10.0.0.1',
        ]);
    }

    $this->actingAs($admin)
        ->get(route('admin.api-analytics'))
        ->assertOk()
        ->assertSee('api/v1/services');
});

it('customer cannot access admin API analytics page', function (): void {
    $customer = customerUser();
    $this->actingAs($customer)
        ->get(route('admin.api-analytics'))
        ->assertForbidden();
});

// ── Panel: token usage stats ──────────────────────────────────────────────────

it('panel API tokens page shows per-token usage stats', function (): void {
    $user = customerUser();

    $tokenObj = $user->createToken('test-token', ['read']);
    $tokenId  = $tokenObj->accessToken->id;

    ApiUsageLog::create([
        'user_id'          => $user->id,
        'token_id'         => $tokenId,
        'endpoint'         => 'api/v1/profile',
        'method'           => 'GET',
        'status_code'      => 200,
        'response_time_ms' => 20,
        'ip_address'       => '127.0.0.1',
    ]);

    $this->actingAs($user)
        ->get(route('panel.account.api-tokens'))
        ->assertOk()
        ->assertSee('test-token')
        ->assertSee('Požadavky (7d)');
});

it('panel API tokens page loads without error when tokens have no usage logs', function (): void {
    $user = customerUser();
    $user->createToken('empty-token', ['read']);

    $this->actingAs($user)
        ->get(route('panel.account.api-tokens'))
        ->assertOk();
});
