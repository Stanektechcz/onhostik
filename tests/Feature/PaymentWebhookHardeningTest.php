<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessComgateWebhookAction;
use App\Domains\Billing\Actions\ProcessGopayWebhookAction;
use App\Domains\Billing\Actions\ProcessStripeWebhookAction;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Services\Gateways\ComgateGateway;
use App\Domains\Billing\Services\Gateways\GopayGateway;
use App\Domains\Billing\Services\Gateways\StripeGateway;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

/**
 * Cross-gateway webhook hardening (audit D54).
 *
 * The gateways take plain-string constructor arguments, so they cannot be
 * autowired — they need explicit container bindings. Stripe and GoPay were
 * missing theirs, which made those webhook endpoints throw
 * BindingResolutionException on every notification: no card payment could
 * ever settle. These tests keep that from regressing.
 */
beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

it('can resolve every payment gateway from the container', function (string $gateway): void {
    expect(app($gateway))->toBeInstanceOf($gateway);
})->with([
    ComgateGateway::class,
    StripeGateway::class,
    GopayGateway::class,
]);

it('can resolve every webhook processor from the container', function (string $action): void {
    expect(app($action))->toBeInstanceOf($action);
})->with([
    ProcessComgateWebhookAction::class,
    ProcessStripeWebhookAction::class,
    ProcessGopayWebhookAction::class,
]);

it('answers every payment webhook endpoint without a server error', function (string $route): void {
    // Endpoints must stay reachable: a 500 makes the gateway retry forever.
    $response = $this->post($route, []);

    expect($response->status())->toBeLessThan(500);
})->with([
    '/api/webhooks/comgate',
    '/api/webhooks/gopay',
]);

// ── Stripe signature verification ─────────────────────────────────────────────

it('rejects a Stripe webhook with a forged signature', function (): void {
    config()->set('stripe.webhook_secret', 'whsec_test');

    $payload   = json_encode(['id' => 'evt_1', 'type' => 'payment_intent.succeeded']) ?: '';
    $timestamp = time();

    $this->call(
        'POST',
        '/api/webhooks/stripe',
        server: ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=" . str_repeat('0', 64)],
        content: $payload,
    );

    // Forged signature must never produce a payment.
    expect(Payment::count())->toBe(0);
});

it('rejects a Stripe webhook whose timestamp is outside the replay window', function (): void {
    config()->set('stripe.webhook_secret', 'whsec_test');

    $payload = json_encode(['id' => 'evt_2', 'type' => 'payment_intent.succeeded']) ?: '';
    $old     = time() - 3600; // an hour old — replayed
    $sig     = hash_hmac('sha256', "{$old}.{$payload}", 'whsec_test');

    $this->call(
        'POST',
        '/api/webhooks/stripe',
        server: ['HTTP_STRIPE_SIGNATURE' => "t={$old},v1={$sig}"],
        content: $payload,
    );

    expect(Payment::count())->toBe(0);
});

it('rejects a Stripe webhook with no signature header at all', function (): void {
    config()->set('stripe.webhook_secret', 'whsec_test');

    $this->call(
        'POST',
        '/api/webhooks/stripe',
        content: json_encode(['id' => 'evt_3']) ?: '',
    );

    expect(Payment::count())->toBe(0);
});
