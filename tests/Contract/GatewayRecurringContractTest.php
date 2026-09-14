<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentMethod;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Payments\GoPay\GoPayPaymentProvider;
use Onhost\Providers\Payments\Stripe\StripePaymentProvider;

/*
 * Recurring paths of the contingency gateways against recorded sandbox-shaped payloads (audit §5h-7): the fixtures in
 * tests/Contract/fixtures mirror what the sandboxes answer (ids and secrets redacted), so the adapters are exercised
 * on the real field names — an expanded Checkout Session with the kept card, an off-session PaymentIntent and its
 * webhook event; a GoPay ON_DEMAND payment with the masked card and a recurrence of it — and the platform settles
 * both through the ordinary status path, keeping the token and the card facts without ever seeing a card number.
 */

function gatewayFixture(string $gateway, string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/fixtures/{$gateway}/{$name}.json"), true, 512, JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

afterEach(function () {
    unset($_ENV['STRIPE_SECRET_KEY'], $_ENV['STRIPE_WEBHOOK_SECRET'], $_ENV['GOPAY_CLIENT_ID'], $_ENV['GOPAY_CLIENT_SECRET']);
});

it('Stripe: the recorded session, off-session intent and event settle a kept card and an off-session charge', function () {
    $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_recorded';
    $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_recorded';
    $session = gatewayFixture('stripe', 'checkout_session_paid');
    $offSession = gatewayFixture('stripe', 'payment_intent_offsession');
    $event = gatewayFixture('stripe', 'event_payment_intent_succeeded');
    $posted = [];
    Http::fake(function (Request $request) use ($session, $offSession, &$posted) {
        if (! str_contains($request->url(), 'api.stripe.com')) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $path === '/v1/checkout/sessions' && $request->method() === 'POST' => Http::response(['id' => $session['id'], 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/'.$session['id']]),
            $path === '/v1/checkout/sessions/'.$session['id'] => Http::response($session),
            $path === '/v1/payment_intents' && $request->method() === 'POST' => (function () use (&$posted, $request, $offSession) {
                $posted[] = $request->data();

                return Http::response($offSession);
            })(),
            $path === '/v1/payment_intents/'.$offSession['id'] => Http::response($offSession),
            default => Http::response(['error' => ['message' => "no fixture for {$path}"]], 404),
        };
    });
    [$owner, $org] = $this->customerWithOrganization([], ['currency' => 'EUR', 'country' => 'DE', 'billing_email' => 'customer@example.com']);
    $payments = app(PaymentService::class);
    $ctx = CommandContext::system('recorded');

    $intent = $payments->createIntent($org, Money::minor(4000, 'EUR'), 'topup', 'wallet', $org->id, $ctx, ['provider' => 'stripe', 'method' => 'card', 'save_method' => true, 'idempotency_key' => 'rec-stripe-1']);
    expect($intent->provider_id)->toBe($session['id']);
    expect($payments->syncFromProvider($intent, $ctx))->toBe('settled');
    $method = PaymentMethod::query()->where('organization_id', $org->id)->firstOrFail();
    expect($method)->toMatchArray(['provider' => 'stripe', 'brand' => 'visa', 'last4' => '4242', 'expires' => '2031-12'])->and($method->provider_token)->toBe('cus_QwErTy123456:pm_1QAbCdEfGhIjKlMn');
    expect(json_encode($intent->fresh()->raw))->not->toContain('secret_REDACTED'); // the client secret in the recorded payload is redacted before it is stored

    $charge = $payments->createIntent($org, Money::minor(2500, 'EUR'), 'topup', 'wallet', $org->id, $ctx, ['provider' => 'stripe', 'method' => 'stored', 'stored_method_id' => $method->id, 'idempotency_key' => 'rec-stripe-2', 'description' => 'Automatické dobití kreditu']);
    expect($charge->provider_id)->toBe($offSession['id'])->and($posted[0])->toMatchArray(['customer' => 'cus_QwErTy123456', 'payment_method' => 'pm_1QAbCdEfGhIjKlMn', 'off_session' => 'true', 'confirm' => 'true']);
    // the gateway answered "succeeded" on the spot: the charge is booked at creation (wallet credit, receipt) and the status path only confirms it
    expect($charge->state)->toBe('SUCCEEDED')->and($charge->paid_at)->not->toBeNull()->and($payments->syncFromProvider($charge, $ctx))->toBe('already_settled');
    expect(app(WalletService::class)->balances($org, 'EUR')['posted']->minor)->toBe(6500);

    $payload = json_encode($event);
    $t = time();
    $verified = app(StripePaymentProvider::class)->verifyWebhook(Illuminate\Http\Request::create('/v1/webhooks/payments/stripe', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_recorded')], $payload));
    expect($verified)->toMatchArray(['event_id' => $event['id'], 'provider_id' => $offSession['id'], 'state' => 'PAID'])->and($verified['amount']->minor)->toBe(2500);
});

it('GoPay: the recorded ON_DEMAND payment keeps the card, its recurrence settles, the notification resolves through the status', function () {
    $_ENV['GOPAY_CLIENT_ID'] = 'gp-client-recorded';
    $_ENV['GOPAY_CLIENT_SECRET'] = 'gp-secret-recorded';
    config()->set('onhost.payments.gopay.goid', 8123456789);
    $parent = gatewayFixture('gopay', 'payment_recurrent');
    $child = gatewayFixture('gopay', 'recurrence_created');
    $posted = [];
    Http::fake(function (Request $request) use ($parent, $child, &$posted) {
        if (! str_contains($request->url(), 'gopay.com')) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/oauth2/token') => Http::response(['token_type' => 'Bearer', 'access_token' => 'recorded-token', 'expires_in' => 1800]),
            str_ends_with($path, '/payments/payment') && $request->method() === 'POST' => (function () use (&$posted, $request, $parent) {
                $posted['create'] = $request->data();

                return Http::response(['id' => $parent['id'], 'order_number' => $parent['order_number'], 'state' => 'CREATED', 'amount' => $parent['amount'], 'currency' => 'CZK', 'gw_url' => $parent['gw_url'], 'recurrence' => ['recurrence_cycle' => 'ON_DEMAND', 'recurrence_date_to' => '2029-09-13', 'recurrence_state' => 'REQUESTED']]);
            })(),
            str_ends_with($path, '/payments/payment/'.$parent['id']) => Http::response($parent),
            str_ends_with($path, '/payments/payment/'.$parent['id'].'/create-recurrence') => (function () use (&$posted, $request, $child) {
                $posted['recurrence'] = $request->data();

                return Http::response($child);
            })(),
            str_ends_with($path, '/payments/payment/'.$child['id']) => Http::response($child),
            default => Http::response(['errors' => [['description' => "no fixture for {$path}"]]], 404),
        };
    });
    [$owner, $org] = $this->customerWithOrganization([], ['billing_email' => 'customer@example.cz']);
    $payments = app(PaymentService::class);
    $ctx = CommandContext::system('recorded');

    $intent = $payments->createIntent($org, Money::minor(50000, 'CZK'), 'topup', 'wallet', $org->id, $ctx, ['provider' => 'gopay', 'method' => 'card', 'save_method' => true, 'idempotency_key' => 'rec-gopay-1']);
    expect($intent->provider_id)->toBe((string) $parent['id'])->and($posted['create']['recurrence']['recurrence_cycle'])->toBe('ON_DEMAND')->and($posted['create']['payer']['allowed_payment_instruments'])->toBe(['PAYMENT_CARD']);
    expect($payments->syncFromProvider($intent, $ctx))->toBe('settled');
    $method = PaymentMethod::query()->where('organization_id', $org->id)->firstOrFail();
    expect($method)->toMatchArray(['provider' => 'gopay', 'brand' => 'VISA', 'last4' => '4444', 'expires' => '2031-12'])->and($method->provider_token)->toBe((string) $parent['id']);
    expect(json_encode($intent->fresh()->raw))->not->toContain('card_token"'.':"REDACTED'); // the card token GoPay echoes is masked by the redactor before storage

    $charge = $payments->createIntent($org, Money::minor(20000, 'CZK'), 'topup', 'wallet', $org->id, $ctx, ['provider' => 'gopay', 'method' => 'stored', 'stored_method_id' => $method->id, 'idempotency_key' => 'rec-gopay-2', 'description' => 'Automatické dobití kreditu']);
    expect($charge->provider_id)->toBe((string) $child['id'])->and($posted['recurrence'])->toMatchArray(['amount' => 20000, 'currency' => 'CZK']);
    expect($charge->state)->toBe('SUCCEEDED')->and($payments->syncFromProvider($charge, $ctx))->toBe('already_settled'); // PAID at once: booked at creation
    expect(app(WalletService::class)->balances($org, 'CZK')['posted']->minor)->toBe(70000);

    $notification = app(GoPayPaymentProvider::class)->verifyWebhook(Illuminate\Http\Request::create('/v1/webhooks/payments/gopay', 'GET', ['id' => (string) $child['id']]));
    expect($notification)->toMatchArray(['provider_id' => (string) $child['id'], 'state' => 'PAID'])->and($notification['amount']->minor)->toBe(20000);
});
