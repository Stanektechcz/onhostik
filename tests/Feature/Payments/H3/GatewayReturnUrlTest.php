<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * H3 security review of PR #116: GoPay and Stripe send the payer back to `return_url` / `cancel_url` as well. They pass through the
 * same check as Comgate (Onhost\Providers\Payments\ReturnUrls): the portal's or the application's own origin, or an https host of
 * `onhost.payments.return_hosts` — anything else is refused before the gateway is called.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(); // nothing may be sent: a refused URL is refused first, and an accepted one is not what this file tests
    $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_stripe';
    $_ENV['GOPAY_CLIENT_ID'] = 'gp-client';
    $_ENV['GOPAY_CLIENT_SECRET'] = 'gp-secret-test';
    config()->set('onhost.payments.gopay.goid', 8123456789);
    config()->set('onhost.portal_url', 'https://portal.onhost.test');
    config()->set('app.url', 'https://api.onhost.test');
    config()->set('onhost.payments.return_hosts', []);
});

it('refuses a foreign return or cancel address on GoPay and Stripe before the gateway is called', function (string $provider, string $key) {
    $input = ['description' => 'Dobití', 'reference' => 'pi-h3', 'email' => 'c@example.cz', 'return_url' => 'https://portal.onhost.test/ok', 'cancel_url' => 'https://portal.onhost.test/no'];
    $input[$key] = 'https://evil.example/x';

    try {
        app(PaymentProviderRegistry::class)->get($provider)->createPaymentIntent(Money::minor(50000, 'CZK'), $input);
        $this->fail("{$provider} accepted a foreign {$key}");
    } catch (DomainError $e) {
        expect($e->error)->toBe('payment_return_url_invalid')->and($e->extra['field'] ?? null)->toBe($key)->and($e->status)->toBe(422);
    }
    Http::assertNothingSent();
})->with([
    'gopay return' => ['gopay', 'return_url'],
    'stripe return' => ['stripe', 'return_url'],
    'stripe cancel' => ['stripe', 'cancel_url'],
]);
