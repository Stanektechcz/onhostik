<?php

declare(strict_types=1);

use App\Domains\Shared\Support\LogContext;
use Illuminate\Support\Facades\Log;

/**
 * Audit M166: structured logging context.
 */

it('stamps the fields onto lines logged inside the block', function (): void {
    $captured = [];

    Log::listen(function (\Illuminate\Log\Events\MessageLogged $e) use (&$captured): void {
        $captured[] = $e->context;
    });

    LogContext::with(['service_id' => 42, 'customer_id' => 7], function (): void {
        Log::info('provisioning started');
    });

    expect($captured)->toHaveCount(1)
        ->and($captured[0]['service_id'])->toBe(42)
        ->and($captured[0]['customer_id'])->toBe(7);
});

it('removes the fields again after the block', function (): void {
    $captured = [];

    LogContext::with(['service_id' => 42], fn () => null);

    Log::listen(function (\Illuminate\Log\Events\MessageLogged $e) use (&$captured): void {
        $captured[] = $e->context;
    });

    Log::info('later, unrelated line');

    // The service_id must not bleed into a line logged after the operation.
    expect($captured[0]['service_id'] ?? null)->toBeNull();
});

it('restores context even when the block throws', function (): void {
    try {
        LogContext::with(['service_id' => 99], function (): void {
            throw new \RuntimeException('boom');
        });
    } catch (\RuntimeException) {
        // expected
    }

    $captured = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $e) use (&$captured): void {
        $captured[] = $e->context;
    });

    Log::info('after the failure');

    expect($captured[0]['service_id'] ?? null)->toBeNull();
});

it('drops null fields instead of logging empty keys', function (): void {
    $captured = [];

    Log::listen(function (\Illuminate\Log\Events\MessageLogged $e) use (&$captured): void {
        $captured[] = $e->context;
    });

    LogContext::with(['service_id' => 5, 'server_id' => null], function (): void {
        Log::info('line');
    });

    expect($captured[0]['service_id'])->toBe(5)
        ->and($captured[0])->not->toHaveKey('server_id');
});

it('redacts a secret-bearing field before it reaches the log', function (): void {
    $captured = [];

    Log::listen(function (\Illuminate\Log\Events\MessageLogged $e) use (&$captured): void {
        $captured[] = $e->context;
    });

    // Defence in depth: context is not where secrets should go, but if one
    // slips in it must not be logged verbatim.
    LogContext::with(['service_id' => 1, 'api_key' => 'live_should_not_appear'], function (): void {
        Log::info('line');
    });

    expect(json_encode($captured[0]))->not->toContain('live_should_not_appear');
});
