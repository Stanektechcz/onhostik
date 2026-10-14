<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Payments\Models\PaymentEvent;
use Onhost\Domain\Payments\Models\PaymentIntent;

/*
 * W8 (TASK-0234): the launch configuration of W1 + W3 + W6 together, as the first day after go-live will run it. The sell
 * matrix is fail-closed with one family switched on (W3), COMGATE_CALLBACK_IPS is still empty so every Comgate notification
 * is refused whatever address it comes from (W1), and the only way a card payment settles is the sweep that re-asks Comgate
 * once the grace period is over (W6). A guest orders a web hosting by card: the notification is refused (403
 * `webhook_source_rejected`, Comgate is not asked), the order waits, and `onhost:payments:sweep` settles it. A product of a
 * switched-off family is refused before anything is created. Comgate is faked; nothing reaches the network.
 */

const W8_TRANS = 'W8LN-CHPA-Y001';
const W8_CG_SECRET = 'example-secret-w8'; // a test value, never a credential

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
    Http::preventStrayRequests();
    foreach (['W8LAUNCHCG_MERCHANT' => '123456', 'W8LAUNCHCG_SECRET' => W8_CG_SECRET] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
    config([
        'onhost-sell.default' => false, 'onhost-sell.families' => ['web' => true], // W3: launch sells one family
        'onhost.payments.default' => 'comgate',
        'onhost.payments.comgate.merchant' => '123456', 'onhost.payments.comgate.secret_ref' => 'env://W8LAUNCHCG', 'onhost.payments.comgate.test' => true,
        'onhost.payments.sweep.providers' => ['comgate'], 'onhost.payments.sweep.grace_minutes' => 10,
        'onhost.payments.sweep.max_age_hours' => 72, 'onhost.payments.sweep.batch' => 50,
    ]);
    // W1: COMGATE_CALLBACK_IPS empty, read through the real config file rather than a copy of its parsing
    $GLOBALS['w8LaunchCallbackIps'] = getenv('COMGATE_CALLBACK_IPS');
    w8LaunchCallbackIps('');
    expect(config('onhost.payments.comgate.callback_allowlist'))->toBe([]);
});

afterEach(function () {
    $previous = $GLOBALS['w8LaunchCallbackIps'] ?? false;
    w8LaunchCallbackIps($previous === false ? null : (string) $previous);
    foreach (['W8LAUNCHCG_MERCHANT', 'W8LAUNCHCG_SECRET'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

/** Sets COMGATE_CALLBACK_IPS (null removes it) and loads the Comgate config from config/onhost.php again, as a fresh boot would. */
function w8LaunchCallbackIps(?string $value): void
{
    if ($value === null) {
        putenv('COMGATE_CALLBACK_IPS');
        unset($_ENV['COMGATE_CALLBACK_IPS'], $_SERVER['COMGATE_CALLBACK_IPS']);
    } else {
        putenv('COMGATE_CALLBACK_IPS='.$value);
        $_ENV['COMGATE_CALLBACK_IPS'] = $_SERVER['COMGATE_CALLBACK_IPS'] = $value;
    }
    $fresh = require config_path('onhost.php');
    config(['onhost.payments.comgate.callback_allowlist' => $fresh['payments']['comgate']['callback_allowlist']]);
}

/** Comgate: creating the payment answers the redirect, the status read answers `$gate['status']` for the order's total and counts. */
function w8LaunchComgateFake(array &$gate): void
{
    $gate += ['status' => 'PENDING', 'total' => 0, 'created' => 0, 'status_calls' => 0];
    Http::fake(function (HttpRequest $request) use (&$gate) {
        if (str_contains($request->url(), 'payments.comgate.cz/v2.0/payment/transId/')) {
            $gate['status_calls']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => W8_TRANS, 'status' => $gate['status'], 'price' => $gate['total'], 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'w8']);
        }
        if (str_ends_with($request->url(), 'payments.comgate.cz/v2.0/payment')) {
            $gate['created']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => W8_TRANS, 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id='.W8_TRANS]);
        }

        return Http::response(['code' => 1400, 'message' => 'unexpected call'], 500);
    });
}

/** @param  array<string,mixed>  $item */
function w8LaunchGuestOrder(object $test, array $item, string $email, string $key)
{
    return $test->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs', 'Idempotency-Key' => $key])->postJson('/v1/checkout/guest', [
        'customer' => ['email' => $email, 'name' => 'Lenka Launch', 'country' => 'CZ', 'street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => [['line_id' => 'l1'] + $item],
        'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Lenka Launch'], 'registry_terms_cz' => ['person' => 'Lenka Launch']],
        'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'terms' => true,
    ]);
}

it('settles a guest card order through the sweep while every Comgate notification is refused', function () {
    $gate = [];
    w8LaunchComgateFake($gate);

    $placed = w8LaunchGuestOrder($this, ['product_key' => 'web-hosting', 'plan_key' => 'standard'], 'lenka@launch.cz', 'w8-launch-1')->assertCreated();
    expect($placed->json('redirect_url'))->toContain('payments.comgate.cz')
        ->and($placed->json('state'))->toBe(OrderStateMachine::PENDING_PAYMENT)
        ->and($gate['created'])->toBe(1);
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $intent = PaymentIntent::query()->findOrFail($order->payment_intent_id);
    expect($intent->provider)->toBe('comgate')->and($intent->provider_id)->toBe(W8_TRANS);

    // the customer pays; Comgate's notification arrives, from its own address or any other: refused, Comgate is not asked
    $gate['status'] = 'PAID';
    $gate['total'] = $order->total_minor;
    foreach (['127.0.0.1', '89.185.236.55', '2001:db8::7'] as $ip) {
        $refused = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/v1/webhooks/payments/comgate', ['transId' => W8_TRANS, 'status' => 'PAID', 'price' => $order->total_minor, 'curr' => 'CZK', 'secret' => W8_CG_SECRET])
            ->assertStatus(403);
        expect((string) $refused->getContent())->toContain('webhook_source_rejected');
    }
    expect($gate['status_calls'])->toBe(0)
        ->and(PaymentEvent::query()->count())->toBe(0)
        ->and($intent->refresh()->isSucceeded())->toBeFalse()
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT);

    // inside the grace period the sweep leaves the payment to the notification and the payer's return
    $this->travel(5)->minutes();
    $this->artisan('onhost:payments:sweep')->expectsOutputToContain('checked 0')->assertExitCode(0);
    expect($gate['status_calls'])->toBe(0);

    // after it, the sweep re-asks Comgate and settles the order
    $this->travel(10)->minutes();
    $this->artisan('onhost:payments:sweep')
        ->expectsOutputToContain('checked 1, settled 1, failed 0, open 0, errors 0')
        ->assertExitCode(0);

    expect($gate['status_calls'])->toBe(1)
        ->and($intent->refresh()->state)->toBe('SUCCEEDED')
        ->and($intent->paid_at)->not->toBeNull()
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PAID)
        ->and($order->invoice_id)->not->toBeNull()
        ->and(Invoice::query()->findOrFail($order->invoice_id)->state)->toBe(Invoice::PAID);

    // idempotent: the next run has nothing left to ask
    $this->artisan('onhost:payments:sweep')->expectsOutputToContain('checked 0')->assertExitCode(0);
    expect($gate['status_calls'])->toBe(1);
});

it('refuses a guest order for a product of a switched-off family before anything is created', function () {
    $gate = [];
    w8LaunchComgateFake($gate);

    $refused = w8LaunchGuestOrder($this, ['product_key' => 'vps', 'plan_key' => 'compute-4'], 'vojta@launch.cz', 'w8-launch-2')->assertStatus(409);

    // the account used to be created first and stay behind without an order or a set-password mail: the next try then said
    // `account_exists` to a person who never got a password
    expect($refused->json('error'))->toBe('product_not_sellable')
        ->and(User::query()->where('email', 'vojta@launch.cz')->exists())->toBeFalse()
        ->and(Order::query()->count())->toBe(0)
        ->and(PaymentIntent::query()->count())->toBe(0)
        ->and($gate['created'])->toBe(0);
    Http::assertNothingSent();

    // the same person orders what is on sale and gets the account then
    w8LaunchGuestOrder($this, ['product_key' => 'web-hosting', 'plan_key' => 'start'], 'vojta@launch.cz', 'w8-launch-3')->assertCreated();
    expect(User::query()->where('email', 'vojta@launch.cz')->exists())->toBeTrue();
});

it('refuses what the quote would refuse before the account exists: unknown product, unknown plan, no current price', function (array $item, int $status, string $error) {
    $gate = [];
    w8LaunchComgateFake($gate);

    if ($item['w8_retire_price'] ?? false) { // the monthly CZK price is no longer in force
        unset($item['w8_retire_price']);
        app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'month')['price']->forceFill(['effective_to' => now()->subMinute()])->save();
    }
    $refused = w8LaunchGuestOrder($this, $item, 'nikdo@launch.cz', 'w8-launch-4')->assertStatus($status);

    expect($refused->json('error'))->toBe($error)
        ->and(User::query()->where('email', 'nikdo@launch.cz')->exists())->toBeFalse()
        ->and(Order::query()->count())->toBe(0)
        ->and($gate['created'])->toBe(0);
})->with([
    'unknown product' => [['product_key' => 'no-such-product', 'plan_key' => 'standard'], 404, 'not_found'],
    'unknown plan' => [['product_key' => 'web-hosting', 'plan_key' => 'no-such-plan'], 404, 'not_found'],
    'no current price' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'w8_retire_price' => true], 409, 'price_unavailable'],
]);

it('refuses a plan change line from somebody without an account with 422, before the account exists', function () {
    $gate = [];
    w8LaunchComgateFake($gate);

    $refused = w8LaunchGuestOrder($this, ['product_key' => 'web-hosting', 'plan_key' => 'standard', 'config' => ['upgrade_of' => 'svc_01m4w8launchupgradeline00']], 'zmena@launch.cz', 'w8-launch-5')
        ->assertStatus(422);

    expect($refused->json('error'))->toBe('upgrade_needs_account')
        ->and(User::query()->where('email', 'zmena@launch.cz')->exists())->toBeFalse()
        ->and(Order::query()->count())->toBe(0)
        ->and($gate['created'])->toBe(0);
});

it('lets a guest order a domain alone while every product family is switched off', function () {
    config(['onhost-sell.default' => false, 'onhost-sell.families' => []]);
    $gate = [];
    w8LaunchComgateFake($gate);

    $placed = w8LaunchGuestOrder($this, ['product_key' => 'domain', 'config' => ['fqdn' => 'w8-launch-domena.cz', 'period_years' => 1]], 'domena@launch.cz', 'w8-launch-6')
        ->assertCreated();

    expect($placed->json('state'))->toBe(OrderStateMachine::PENDING_PAYMENT)
        ->and(User::query()->where('email', 'domena@launch.cz')->exists())->toBeTrue()
        ->and($gate['created'])->toBe(1);
});
