<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Platform\Money\Money;

/*
 * G6 (found by G9): ComgatePaymentProvider::createPaymentIntent read `$input['locale']` without a default — the check said "cs"
 * for a missing locale and then sent the missing key, so a caller that passed no locale failed with an undefined index before
 * anything reached the gateway. The same held for description, reference and e-mail. A missing value now falls back (Czech,
 * empty), and an unsupported language is sent as Czech, never as itself.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'g6-callback-secret';
    config()->set('onhost.payments.comgate.merchant', '123456');
    $GLOBALS['g6ComgateSent'] = [];
    Http::fake(function (HttpRequest $request) {
        $GLOBALS['g6ComgateSent'][] = $request->data();

        return Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'G6AB-CD12-EF34', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=G6AB-CD12-EF34']);
    });
});

/** @param list<array<string,mixed>> $sent what the adapter sent so far */
function g6ComgateCreate(array $input, ?array &$sent): array
{
    $created = app(PaymentProviderRegistry::class)->get('comgate')->createPaymentIntent(Money::minor(10000, 'CZK'), $input);
    $sent = $GLOBALS['g6ComgateSent'];

    return $created;
}

it('creates a payment when the caller sends no locale, description, reference or e-mail', function () {
    $sent = [];
    $created = g6ComgateCreate(['method' => 'card'], $sent);

    expect($created['provider_id'])->toBe('G6AB-CD12-EF34')
        ->and($sent)->toHaveCount(1)
        ->and($sent[0]['lang'])->toBe('cs')
        ->and($sent[0]['price'])->toBe(10000);
});

it('sends a supported language as it is and anything else as Czech', function () {
    $sent = [];
    g6ComgateCreate(['method' => 'card', 'locale' => 'en', 'description' => 'Top-up', 'reference' => 'pi_g6', 'email' => 'a@example.cz'], $sent);
    g6ComgateCreate(['method' => 'card', 'locale' => 'de'], $sent);
    g6ComgateCreate(['method' => 'card', 'locale' => null], $sent);

    expect(array_column($sent, 'lang'))->toBe(['en', 'cs', 'cs']);
});
