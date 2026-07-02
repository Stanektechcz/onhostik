<?php

declare(strict_types=1);

use App\Domains\Billing\Services\Gateways\StripeGateway;

function gatewayWithSecret(string $webhookSecret): StripeGateway
{
    return new StripeGateway(
        apiKey:        'sk_test_dummy',
        webhookSecret: $webhookSecret,
        baseUrl:       'https://api.stripe.com/v1',
    );
}

function buildSig(string $payload, string $secret, int $timestamp = 0): string
{
    $ts  = $timestamp > 0 ? $timestamp : time();
    $sig = hash_hmac('sha256', "{$ts}.{$payload}", $secret);

    return "t={$ts},v1={$sig}";
}

// ───────── constructEvent ─────────

it('accepts a valid webhook signature and returns decoded event', function (): void {
    $gateway = gatewayWithSecret('test_secret');
    $payload = json_encode(['type' => 'checkout.session.completed', 'id' => 'evt_001']);
    $sig     = buildSig($payload, 'test_secret');

    $event = $gateway->constructEvent($payload, $sig);

    expect($event['type'])->toBe('checkout.session.completed')
        ->and($event['id'])->toBe('evt_001');
});

it('rejects a signature built with the wrong secret', function (): void {
    $gateway = gatewayWithSecret('correct_secret');
    $payload = json_encode(['type' => 'checkout.session.completed']);
    $sig     = buildSig($payload, 'wrong_secret');

    expect(fn () => $gateway->constructEvent($payload, $sig))
        ->toThrow(RuntimeException::class, 'signature verification failed');
});

it('rejects a stale timestamp as a replay-attack defence', function (): void {
    $gateway   = gatewayWithSecret('test_secret');
    $payload   = json_encode(['type' => 'checkout.session.completed']);
    $staleTime = time() - 400; // 400 seconds ago — beyond the 300 s window
    $sig       = buildSig($payload, 'test_secret', $staleTime);

    expect(fn () => $gateway->constructEvent($payload, $sig))
        ->toThrow(RuntimeException::class, 'timestamp too old');
});

it('rejects a payload that was tampered after signing', function (): void {
    $gateway  = gatewayWithSecret('test_secret');
    $original = json_encode(['type' => 'checkout.session.completed']);
    $sig      = buildSig($original, 'test_secret');
    $tampered = json_encode(['type' => 'account.updated']); // different from what was signed

    expect(fn () => $gateway->constructEvent($tampered, $sig))
        ->toThrow(RuntimeException::class, 'signature verification failed');
});

it('rejects a payload that is not valid JSON', function (): void {
    $gateway = gatewayWithSecret('test_secret');
    $payload = 'not-valid-json';
    $sig     = buildSig($payload, 'test_secret');

    expect(fn () => $gateway->constructEvent($payload, $sig))
        ->toThrow(RuntimeException::class, 'not valid JSON');
});

it('throws when webhook_secret is empty string', function (): void {
    $gateway = gatewayWithSecret('');

    expect(fn () => $gateway->constructEvent('{}', 't=123,v1=abc'))
        ->toThrow(RuntimeException::class, 'webhook_secret is not configured');
});

// ───────── isConfigured ─────────

it('reports configured when api_key is non-empty', function (): void {
    $gateway = new StripeGateway(
        apiKey:        'sk_test_some_key',
        webhookSecret: '',
        baseUrl:       'https://api.stripe.com/v1',
    );

    expect($gateway->isConfigured())->toBeTrue();
});

it('reports not configured when api_key is empty string', function (): void {
    $gateway = new StripeGateway(
        apiKey:        '',
        webhookSecret: '',
        baseUrl:       'https://api.stripe.com/v1',
    );

    expect($gateway->isConfigured())->toBeFalse();
});
