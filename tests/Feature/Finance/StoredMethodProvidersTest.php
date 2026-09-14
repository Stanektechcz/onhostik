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
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Payments\GoPay\GoPayPaymentProvider;
use Onhost\Providers\Payments\Stripe\StripePaymentProvider;

/*
 * Stored cards beyond the primary gateway (audit §5g-5): Stripe keeps the card through a Checkout Session with
 * `setup_future_usage` and charges it with off-session PaymentIntents; GoPay keeps it as an ON_DEMAND recurrent
 * payment and charges recurrences of it. Both sit behind the same StoredMethodCharging contract, so the automatic
 * top-up does not know which gateway holds the card — only that the card's own gateway must charge it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_stripe';
    $_ENV['STRIPE_WEBHOOK_SECRET'] = 'whsec_test';
    $_ENV['GOPAY_CLIENT_ID'] = 'gp-client';
    $_ENV['GOPAY_CLIENT_SECRET'] = 'gp-secret-test';
    config()->set('onhost.payments.gopay.goid', 8123456789);
});

afterEach(function () {
    unset($_ENV['STRIPE_SECRET_KEY'], $_ENV['STRIPE_WEBHOOK_SECRET'], $_ENV['GOPAY_CLIENT_ID'], $_ENV['GOPAY_CLIENT_SECRET']);
});

it('keeps a card through a Stripe Checkout Session and charges it off-session; status, refund and webhooks know the charge by its intent id', function () {
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        if (! str_contains($request->url(), 'api.stripe.com')) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $calls[] = [$request->method(), $path, $request->data(), urldecode((string) parse_url($request->url(), PHP_URL_QUERY))];

        return match (true) {
            $path === '/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_1', 'customer' => null]),
            $path === '/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'status' => 'complete', 'amount_total' => 4000, 'currency' => 'eur', 'customer' => 'cus_9',
                'payment_intent' => ['id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'succeeded', 'client_secret' => 'pi_1_secret_x', 'payment_method' => ['id' => 'pm_7', 'object' => 'payment_method', 'type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2031]]]]),
            $path === '/v1/payment_intents' => Http::response(['id' => 'pi_2', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 2500, 'amount_received' => 2500, 'currency' => 'eur', 'metadata' => ['stored' => '1']]),
            $path === '/v1/payment_intents/pi_2' => Http::response(['id' => 'pi_2', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 2500, 'amount_received' => 2500, 'currency' => 'eur', 'payment_method' => ['id' => 'pm_7', 'card' => ['last4' => '4242']]]),
            $path === '/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded']),
            default => Http::response(['error' => ['message' => "no fake for {$path}"]], 404),
        };
    });
    $stripe = app(StripePaymentProvider::class);
    expect($stripe->storedMethodsAvailable())->toBeTrue();

    // the initial payment asks Stripe to keep the card on a Customer
    $created = $stripe->createPaymentIntent(Money::minor(4000, 'EUR'), ['description' => 'Top-up', 'reference' => 'pi-local-1', 'email' => 'c@example.com', 'return_url' => 'https://p/ok', 'cancel_url' => 'https://p/no', 'pending_url' => 'https://p/pending', 'save_method' => true, 'idempotency_key' => 'k1']);
    expect($created['provider_id'])->toBe('cs_1')->and($calls[0][2])->toMatchArray(['payment_intent_data[setup_future_usage]' => 'off_session', 'customer_creation' => 'always', 'payment_method_types[0]' => 'card', 'mode' => 'payment']);

    // its settled status carries the Customer and the card; the token is customer:payment_method, the card facts are what the customer sees
    $status = $stripe->getPaymentStatus('cs_1');
    expect($status['state'])->toBe('PAID')->and($calls[1][1])->toBe('/v1/checkout/sessions/cs_1')->and($calls[1][3])->toContain('expand[]=payment_intent.payment_method');
    expect($stripe->storedMethodFrom('cs_1', $status['raw']))->toBe(['token' => 'cus_9:pm_7', 'brand' => 'visa', 'last4' => '4242', 'expires' => '2031-12']);
    expect($stripe->storedMethodFrom('cs_1', ['id' => 'cs_1', 'customer' => null]))->toBeNull(); // a session without a kept card stores nothing

    // an off-session charge with the token; the resulting intent is known by its pi_ id everywhere
    $charge = $stripe->chargeStoredMethod('cus_9:pm_7', Money::minor(2500, 'EUR'), ['description' => 'Auto top-up', 'reference' => 'pi-local-2', 'idempotency_key' => 'k2', 'email' => 'c@example.com']);
    expect($charge)->toMatchArray(['provider_id' => 'pi_2', 'state' => 'PAID', 'redirect_url' => null])->and($calls[2][2])->toMatchArray(['customer' => 'cus_9', 'payment_method' => 'pm_7', 'off_session' => 'true', 'confirm' => 'true', 'amount' => 2500, 'currency' => 'eur', 'metadata[stored]' => '1']);
    expect($stripe->getPaymentStatus('pi_2'))->toMatchArray(['state' => 'PAID', 'method' => 'card'])->and($calls[3][1])->toBe('/v1/payment_intents/pi_2');
    expect($stripe->refund('pi_2', Money::minor(500, 'EUR'), 'rf-1')['provider_refund_id'])->toBe('re_1')->and($calls[4][2]['payment_intent'])->toBe('pi_2');
    expect(fn () => $stripe->chargeStoredMethod('broken', Money::minor(100, 'EUR')))->toThrow(DomainError::class);

    // webhooks: an off-session intent's success is its own payment; a checkout intent's success is still attributed to nothing (the session event carries it)
    $sign = function (array $event) {
        $payload = json_encode($event);
        $t = time();

        return [$payload, 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test')];
    };
    [$payload, $signature] = $sign(['id' => 'evt_1', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_2', 'object' => 'payment_intent', 'amount_received' => 2500, 'currency' => 'eur', 'metadata' => ['stored' => '1']]]]);
    $verified = $stripe->verifyWebhook(Illuminate\Http\Request::create('/v1/webhooks/payments/stripe', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload));
    expect($verified)->toMatchArray(['provider_id' => 'pi_2', 'state' => 'PAID'])->and($verified['amount']->minor)->toBe(2500);
    [$payload, $signature] = $sign(['id' => 'evt_2', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_1', 'object' => 'payment_intent', 'metadata' => []]]]);
    expect($stripe->verifyWebhook(Illuminate\Http\Request::create('/v1/webhooks/payments/stripe', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => $signature], $payload)))->toMatchArray(['provider_id' => '', 'state' => 'PENDING']);
});

it('keeps a card as a GoPay on-demand recurrent payment and charges recurrences of it', function () {
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        if (! str_contains($request->url(), 'gopay.com')) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $calls[] = [$request->method(), $path, $request->data()];

        return match (true) {
            str_ends_with($path, '/oauth2/token') => Http::response(['access_token' => 'at-1', 'expires_in' => 1800]),
            str_ends_with($path, '/payments/payment') => Http::response(['id' => 3001, 'state' => 'CREATED', 'gw_url' => 'https://gw.sandbox.gopay.com/gw/v3/3001', 'recurrence' => ['recurrence_cycle' => 'ON_DEMAND', 'recurrence_date_to' => '2029-09-13', 'recurrence_state' => 'REQUESTED']]),
            str_ends_with($path, '/payments/payment/3001') => Http::response(['id' => 3001, 'state' => 'PAID', 'amount' => 50000, 'currency' => 'CZK', 'payment_instrument' => 'PAYMENT_CARD', 'recurrence' => ['recurrence_cycle' => 'ON_DEMAND', 'recurrence_state' => 'STARTED'], 'payer' => ['payment_card' => ['card_number' => '444444******4444', 'card_expiration' => '3112', 'card_brand' => 'VISA']]]),
            str_ends_with($path, '/payments/payment/3001/create-recurrence') => Http::response(['id' => 3002, 'state' => 'PAID', 'parent_id' => 3001]),
            str_ends_with($path, '/payments/payment/3002') => Http::response(['id' => 3002, 'state' => 'PAID', 'amount' => 20000, 'currency' => 'CZK', 'payment_instrument' => 'PAYMENT_CARD']),
            default => Http::response(['errors' => [['description' => "no fake for {$path}"]]], 404),
        };
    });
    $gopay = app(GoPayPaymentProvider::class);
    expect($gopay->storedMethodsAvailable())->toBeTrue();

    $created = $gopay->createPaymentIntent(Money::minor(50000, 'CZK'), ['description' => 'Dobití', 'reference' => 'pi-local-1', 'email' => 'c@example.cz', 'return_url' => 'https://p/ok', 'cancel_url' => 'https://p/no', 'pending_url' => 'https://p/pending', 'save_method' => true, 'method' => 'bank_transfer']);
    $create = collect($calls)->first(fn ($c) => str_ends_with($c[1], '/payments/payment'))[2];
    expect($created['provider_id'])->toBe('3001')->and($create['recurrence']['recurrence_cycle'])->toBe('ON_DEMAND')->and($create['payer']['allowed_payment_instruments'])->toBe(['PAYMENT_CARD']); // a kept method must be a card, whatever the customer clicked

    $status = $gopay->getPaymentStatus('3001');
    expect($status['state'])->toBe('PAID')->and($gopay->storedMethodFrom('3001', $status['raw']))->toBe(['token' => '3001', 'brand' => 'VISA', 'last4' => '4444', 'expires' => '2031-12']);
    expect($gopay->storedMethodFrom('3003', ['id' => 3003, 'state' => 'PAID']))->toBeNull();

    $charge = $gopay->chargeStoredMethod('3001', Money::minor(20000, 'CZK'), ['description' => 'Automatické dobití', 'reference' => 'pi-local-2']);
    $recurrence = collect($calls)->first(fn ($c) => str_ends_with($c[1], '/create-recurrence'))[2];
    expect($charge)->toMatchArray(['provider_id' => '3002', 'state' => 'PAID'])->and($recurrence)->toMatchArray(['amount' => 20000, 'currency' => 'CZK', 'order_number' => 'pi-local-2']);
    expect($gopay->getPaymentStatus('3002')['state'])->toBe('PAID');
});

it('lets the automatic top-up charge a Stripe card only through Stripe, and settling a kept card reads the token from the status payload', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz', 'currency' => 'EUR', 'country' => 'DE']);
    $calls = [];
    Http::fake(function (Request $request) use (&$calls) {
        if (! str_contains($request->url(), 'api.stripe.com')) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $calls[] = [$request->method(), $path, $request->data()];

        return match (true) {
            $path === '/v1/checkout/sessions' => Http::response(['id' => 'cs_5', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_5']),
            $path === '/v1/checkout/sessions/cs_5' => Http::response(['id' => 'cs_5', 'object' => 'checkout.session', 'payment_status' => 'paid', 'status' => 'complete', 'amount_total' => 4000, 'currency' => 'eur', 'customer' => 'cus_5',
                'payment_intent' => ['id' => 'pi_5', 'status' => 'succeeded', 'payment_method' => ['id' => 'pm_5', 'card' => ['brand' => 'mastercard', 'last4' => '5100', 'exp_month' => 3, 'exp_year' => 2030]]]]),
            $path === '/v1/payment_intents' => Http::response(['id' => 'pi_6', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => (int) $request->data()['amount'], 'amount_received' => (int) $request->data()['amount'], 'currency' => 'eur']),
            default => Http::response(['error' => ['message' => "no fake for {$path}"]], 404),
        };
    });
    $payments = app(PaymentService::class);
    $ctx = CommandContext::system('test');
    $intent = $payments->createIntent($org, Money::minor(4000, 'EUR'), 'topup', 'wallet', $org->id, $ctx, ['provider' => 'stripe', 'method' => 'card', 'save_method' => true, 'idempotency_key' => 'stripe-save-1']);
    expect($intent->provider)->toBe('stripe')->and($intent->return_urls['save_method'])->toBeTrue()->and($intent->provider_id)->toBe('cs_5');

    // the return page / webhook re-reads the status: the settle keeps the card from the expanded status payload, the token never leaves the encrypted column
    expect($payments->syncFromProvider($intent, $ctx))->toBe('settled');
    $method = PaymentMethod::query()->where('organization_id', $org->id)->firstOrFail();
    expect($method)->toMatchArray(['provider' => 'stripe', 'brand' => 'mastercard', 'last4' => '5100', 'expires' => '2030-03', 'is_default' => true])->and($method->provider_token)->toBe('cus_5:pm_5');
    expect(json_encode($payments->methods($org)))->not->toContain('pm_5');

    // the automatic top-up charges through the card's gateway even though another gateway is the default
    config()->set('onhost.payments.default', 'comgate');
    app(AutoTopup::class)->configure($org, ['enabled' => true, 'amount' => '25', 'threshold' => '10', 'monthly_limit' => '100'], $ctx);
    expect(AutoTopupSetting::query()->where('organization_id', $org->id)->value('payment_method_id'))->toBe($method->id);
    $result = app(AutoTopup::class)->attempt($org, Money::minor(1000, 'EUR'), $ctx);
    expect($result['status'])->toBe('charged');
    $charge = PaymentIntent::query()->findOrFail($result['payment_intent_id']);
    expect($charge->provider)->toBe('stripe')->and($charge->provider_id)->toBe('pi_6')->and($charge->method)->toBe('card')->and($charge->state)->toBe('SUCCEEDED'); // succeeded on the spot: booked at creation as a card payment
    $offSession = collect($calls)->first(fn ($c) => $c[1] === '/v1/payment_intents')[2];
    expect($offSession)->toMatchArray(['customer' => 'cus_5', 'payment_method' => 'pm_5', 'off_session' => 'true', 'amount' => 2500]);

    // a removed card leaves the top-up with nothing to charge rather than a charge through the wrong gateway
    $payments->removeMethod($org, $method->id, $ctx);
    expect(app(AutoTopup::class)->attempt($org, Money::minor(1000, 'EUR'), $ctx)['status'])->toBe('unsupported');
});
