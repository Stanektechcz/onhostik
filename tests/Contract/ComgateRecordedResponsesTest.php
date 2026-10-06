<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;

/*
 * The Comgate adapter against stored, sanitized gateway answers (audit P1-14, G9): every call goes through the real
 * ProviderHttpClient, Http::fake serves the file in tests/Contract/fixtures/comgate, and nothing reaches the network
 * (preventStrayRequests). The fixtures are shaped from the API v2.0 documentation until a capture from the Comgate test
 * account replaces them (tests/Contract/fixtures/comgate/README.md) — the assertions are on the fields the adapter reads.
 */

function comgateFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/fixtures/comgate/{$name}.json"), true, 512, JSON_THROW_ON_ERROR);
}

/** Serves one fixture per call, in order, and records every request the adapter sent. */
function comgateReplay(array $responses, array &$sent): void
{
    $queue = $responses;
    Http::fake(function (HttpRequest $request) use (&$queue, &$sent) {
        $sent[] = ['method' => $request->method(), 'url' => $request->url(), 'data' => $request->data(), 'authorization' => $request->header('Authorization')[0] ?? null];
        $next = array_shift($queue);

        return $next === null ? Http::response(['code' => 1400, 'message' => 'no fixture queued'], 500) : Http::response($next);
    });
}

function comgate(): ComgatePaymentProvider
{
    $provider = app(PaymentProviderRegistry::class)->get('comgate');
    expect($provider)->toBeInstanceOf(ComgatePaymentProvider::class);

    return $provider;
}

beforeEach(function () {
    Http::preventStrayRequests();
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'recorded-callback-secret';
    config()->set('onhost.payments.comgate.merchant', '123456');
    config()->set('onhost.payments.comgate.callback_allowlist', []);
    config()->set('onhost.payments.comgate.return_hosts', ['portal.test']); // H3: the recorded calls send the payer back to this host
});

it('creates a payment from the recorded answer and sends the merchant credentials and money in minor units', function () {
    $sent = [];
    comgateReplay([comgateFixture('create_prepared')], $sent);

    $created = comgate()->createPaymentIntent(Money::minor(200000, 'CZK'), ['description' => 'ONhost credit top-up', 'reference' => 'pi_local_ref', 'email' => 'customer@example.cz', 'method' => 'card', 'locale' => 'cs', 'return_url' => 'https://portal.test/ok', 'cancel_url' => 'https://portal.test/no', 'pending_url' => 'https://portal.test/wait', 'idempotency_key' => 'rec-1']);

    expect($created)->toMatchArray(['provider_id' => 'AB12-CD34-EF56', 'state' => 'PENDING', 'redirect_url' => 'https://payments.comgate.cz/client/instructions/index?id=AB12-CD34-EF56']);
    expect($sent)->toHaveCount(1)
        ->and($sent[0]['method'])->toBe('POST')
        ->and($sent[0]['url'])->toEndWith('/payment')
        ->and($sent[0]['authorization'])->toBe('Basic '.base64_encode('123456:recorded-callback-secret'))
        ->and($sent[0]['data'])->toMatchArray(['price' => 200000, 'curr' => 'CZK', 'refId' => 'pi_local_ref', 'method' => 'CARD_CZ_CSOB_2', 'prepareOnly' => true, 'label' => 'ONhost credit to']);
});

it('asks for a recurring payment when the card is kept and charges the stored card later without a redirect', function () {
    $sent = [];
    comgateReplay([comgateFixture('create_recurring_prepared'), comgateFixture('status_paid_card'), comgateFixture('recurring_charge_paid')], $sent);
    $provider = comgate();

    $created = $provider->createPaymentIntent(Money::minor(200000, 'CZK'), ['description' => 'Top-up', 'reference' => 'pi_local_ref', 'email' => 'customer@example.cz', 'method' => 'card', 'save_method' => true, 'locale' => 'cs', 'return_url' => 'https://portal.test/ok', 'cancel_url' => 'https://portal.test/no', 'pending_url' => 'https://portal.test/wait']);
    expect($created['provider_id'])->toBe('RC78-GH90-IJ12')->and($created['raw']['initRecurring'])->toBeTrue()->and($sent[0]['data']['initRecurring'])->toBeTrue();

    $status = $provider->getPaymentStatus('RC78-GH90-IJ12');
    $method = $provider->storedMethodFrom('RC78-GH90-IJ12', $status['raw']);
    expect($method)->toBe(['token' => $created['provider_id'], 'brand' => 'VISA', 'last4' => '1234', 'expires' => '2031-12']);

    $charge = $provider->chargeStoredMethod($method['token'], Money::minor(100000, 'CZK'), ['description' => 'Auto top-up', 'reference' => 'pi_local_ref4', 'idempotency_key' => 'rec-2']);
    expect($charge)->toMatchArray(['provider_id' => 'WX56-YZ78-AB90', 'redirect_url' => null, 'state' => 'PAID'])
        ->and($sent[2]['data'])->toMatchArray(['initRecurringId' => 'RC78-GH90-IJ12', 'price' => 100000, 'method' => 'CARD_CZ_CSOB_2']);
});

it('reads the status of a paid card payment, a pending bank payment and a cancelled one', function () {
    $sent = [];
    comgateReplay([comgateFixture('status_paid_card'), comgateFixture('status_pending'), comgateFixture('status_cancelled')], $sent);
    $provider = comgate();

    $paid = $provider->getPaymentStatus('AB12-CD34-EF56');
    expect($paid['state'])->toBe('PAID')->and($paid['method'])->toBe('card')->and($paid['paid_at'])->not->toBeNull()
        ->and($paid['amount']->minor)->toBe(200000)->and($paid['amount']->currency->value)->toBe('CZK');
    expect($sent[0]['method'])->toBe('GET')->and($sent[0]['url'])->toEndWith('/payment/transId/AB12-CD34-EF56');

    $pending = $provider->getPaymentStatus('KL34-MN56-OP78');
    expect($pending['state'])->toBe('PENDING')->and($pending['method'])->toBe('bank_transfer')->and($pending['paid_at'])->toBeNull()->and($pending['amount']->currency->value)->toBe('EUR');

    $cancelled = $provider->getPaymentStatus('QR90-ST12-UV34');
    expect($cancelled['state'])->toBe('CANCELLED')->and($cancelled['method'])->toBe('apple_pay');
});

it('turns a recorded gateway error into a payment_provider_error that carries the vendor code', function () {
    $sent = [];
    comgateReplay([comgateFixture('error_wrong_parameter')], $sent);

    try {
        comgate()->createPaymentIntent(Money::minor(1, 'CZK'), ['description' => 'x', 'reference' => 'r', 'email' => 'a@b.cz', 'locale' => 'cs', 'return_url' => 'https://portal.test/u', 'cancel_url' => 'https://portal.test/u', 'pending_url' => 'https://portal.test/u']);
        $this->fail('The adapter accepted an error answer.');
    } catch (DomainError $e) {
        expect($e->getMessage())->toContain('[1309]')->and($e->getMessage())->toContain('Wrong parameter: price');
    }
});

it('refunds against the recorded answer in minor units under the caller\'s idempotency key', function () {
    $sent = [];
    comgateReplay([comgateFixture('refund_accepted')], $sent);

    $refund = comgate()->refund('AB12-CD34-EF56', Money::minor(50000, 'CZK'), 'refund-key-1', 'withdrawal');

    expect($refund['state'])->toBe('succeeded_pending')->and($refund['provider_refund_id'])->toBe('AB12-CD34-EF56:refund-key-1');
    expect($sent[0]['url'])->toEndWith('/payment/transId/AB12-CD34-EF56/refund')
        ->and($sent[0]['data'])->toMatchArray(['amount' => 50000, 'curr' => 'CZK', 'refId' => 'refund-key-1']);
});

it('verifies the recorded callback and refuses one with a wrong secret or without a transaction id', function () {
    $payload = comgateFixture('webhook_paid');
    $event = comgate()->verifyWebhook(Request::create('/webhooks/comgate', 'POST', $payload));
    expect($event)->toMatchArray(['event_id' => 'AB12-CD34-EF56:PAID', 'provider_id' => 'AB12-CD34-EF56', 'state' => 'PAID'])
        ->and($event['amount']->minor)->toBe(200000);

    $wrong = fn () => comgate()->verifyWebhook(Request::create('/webhooks/comgate', 'POST', ['secret' => 'not-the-secret'] + $payload));
    expect($wrong)->toThrow(DomainError::class, 'secret mismatch');

    $missing = fn () => comgate()->verifyWebhook(Request::create('/webhooks/comgate', 'POST', ['status' => 'PAID']));
    expect($missing)->toThrow(DomainError::class, 'missing transId');
});

it('reconciles a day from the recorded transfer list and detail', function () {
    $sent = [];
    comgateReplay([comgateFixture('transfer_list'), comgateFixture('transfer_detail')], $sent);

    $items = comgate()->reconcile('2026-10-04', '2026-10-04');

    expect($items)->toHaveCount(2)
        ->and($items[0])->toMatchArray(['provider_id' => 'AB12-CD34-EF56', 'settled_at' => '2026-10-04', 'state' => 'settled', 'reference' => 'pi_local_ref'])
        ->and($items[0]['amount']->minor)->toBe(200000)->and($items[0]['fee']->minor)->toBe(3000)
        ->and($items[1]['provider_id'])->toBe('WX56-YZ78-AB90');
    expect($sent[0]['url'])->toContain('/transfer')->and($sent[1]['url'])->toEndWith('/transfer/TR-2026-10-04-1');
});
