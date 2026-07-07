<?php

use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view webhook retry policy page', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.webhook-retry-policy.index'))
        ->assertOk()
        ->assertViewHas('endpoints');
});

it('admin can update retry policy for an endpoint', function (): void {
    DB::table('webhook_endpoints')->insert([
        'name'                => 'Test Webhook',
        'source'              => 'test-src-1',
        'secret'              => null,
        'is_active'           => true,
        'max_retries'         => 3,
        'retry_delay_seconds' => 60,
        'timeout_seconds'     => 10,
        'created_at'          => now(),
        'updated_at'          => now(),
    ]);
    $endpointId = DB::table('webhook_endpoints')->orderByDesc('id')->value('id');

    $this->actingAs(adminUser())
        ->patch(route('admin.webhook-retry-policy.update', $endpointId), [
            'max_retries'         => 5,
            'retry_delay_seconds' => 120,
            'timeout_seconds'     => 30,
        ])
        ->assertRedirect();

    $ep = DB::table('webhook_endpoints')->find($endpointId);
    expect((int) $ep->max_retries)->toBe(5);
    expect((int) $ep->retry_delay_seconds)->toBe(120);
});

it('max_retries must be between 0 and 10', function (): void {
    DB::table('webhook_endpoints')->insert([
        'name'       => 'Validation Test',
        'source'     => 'test-src-2',
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $endpointId = DB::table('webhook_endpoints')->orderByDesc('id')->value('id');

    $this->actingAs(adminUser())
        ->patch(route('admin.webhook-retry-policy.update', $endpointId), [
            'max_retries'         => 15,
            'retry_delay_seconds' => 60,
            'timeout_seconds'     => 10,
        ])
        ->assertSessionHasErrors('max_retries');
});

it('timeout must be at least 3 seconds', function (): void {
    DB::table('webhook_endpoints')->insert([
        'name'       => 'Timeout Test',
        'source'     => 'test-src-3',
        'is_active'  => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $endpointId = DB::table('webhook_endpoints')->orderByDesc('id')->value('id');

    $this->actingAs(adminUser())
        ->patch(route('admin.webhook-retry-policy.update', $endpointId), [
            'max_retries'         => 3,
            'retry_delay_seconds' => 60,
            'timeout_seconds'     => 1,
        ])
        ->assertSessionHasErrors('timeout_seconds');
});

it('unauthenticated user cannot view webhook retry policy', function (): void {
    $this->get(route('admin.webhook-retry-policy.index'))
        ->assertRedirect();
});
