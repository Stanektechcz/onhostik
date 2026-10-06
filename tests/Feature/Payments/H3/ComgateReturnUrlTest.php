<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PaymentProviderRegistry;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * H3 (phase H, TASK-0121): the addresses Comgate sends the customer back to after paying (`url_paid`, `url_cancelled`,
 * `url_pending`) are the platform's own. An order or an invoice payment may carry `return_urls` from the request; the adapter
 * passed them to the gateway as they came, so a link could make the payment page of ONhost send a paying customer on to any
 * site (an open redirect behind a trusted payment page). Now each URL must be absolute, without credentials, on an allowed
 * origin: the portal's and the application's own, or a host listed in `onhost.payments.comgate.return_hosts` (https only). A
 * URL that fails is refused before anything reaches the gateway.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'h3-callback-secret';
    config()->set('onhost.payments.comgate.merchant', '123456');
    config()->set('onhost.portal_url', 'https://portal.onhost.test');
    config()->set('app.url', 'https://api.onhost.test');
    config()->set('onhost.payments.comgate.return_hosts', ['pay.partner.test']);
    $GLOBALS['h3ComgateSent'] = [];
    Http::fake(function (HttpRequest $request) {
        $GLOBALS['h3ComgateSent'][] = $request->data();

        return Http::response(['code' => 0, 'message' => 'OK', 'transId' => 'H3AB-CD12-EF34', 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id=H3AB-CD12-EF34']);
    });
});

/** @param array<string,mixed> $urls */
function h3ComgateCreate(array $urls): array
{
    return app(PaymentProviderRegistry::class)->get('comgate')->createPaymentIntent(Money::minor(10000, 'CZK'), ['method' => 'card'] + $urls);
}

it('passes the portal\'s own return addresses and those of an allowed host to the gateway', function () {
    h3ComgateCreate(['return_url' => 'https://portal.onhost.test/panel/fakturace?payment=pi_1&result=success', 'cancel_url' => 'https://api.onhost.test/x', 'pending_url' => 'https://pay.partner.test/wait']);

    expect($GLOBALS['h3ComgateSent'])->toHaveCount(1)
        ->and($GLOBALS['h3ComgateSent'][0]['url_paid'])->toBe('https://portal.onhost.test/panel/fakturace?payment=pi_1&result=success')
        ->and($GLOBALS['h3ComgateSent'][0]['url_cancelled'])->toBe('https://api.onhost.test/x')
        ->and($GLOBALS['h3ComgateSent'][0]['url_pending'])->toBe('https://pay.partner.test/wait');
});

it('refuses a return address on another host, a relative or protocol-relative one, credentials, or plain http on an extra host', function (string $key, string $url) {
    try {
        h3ComgateCreate([$key => $url]);
        $this->fail("{$key} {$url} reached the gateway");
    } catch (DomainError $e) {
        expect($e->error)->toBe('payment_return_url_invalid')->and($e->extra['field'] ?? null)->toBe($key);
    }
    expect($GLOBALS['h3ComgateSent'])->toBe([]); // refused before the gateway was called
})->with([
    'another host' => ['return_url', 'https://evil.example/phish'],
    'look-alike host' => ['return_url', 'https://portal.onhost.test.evil.example/x'],
    'credentials' => ['cancel_url', 'https://portal.onhost.test@evil.example/x'],
    'relative' => ['pending_url', '/panel/fakturace'],
    'protocol-relative' => ['return_url', '//evil.example/x'],
    'javascript' => ['return_url', 'javascript:alert(1)'],
    'http on an extra host' => ['return_url', 'http://pay.partner.test/ok'],
    'portal host on another port' => ['return_url', 'https://portal.onhost.test:8443/x'],
]);

it('leaves the merchant\'s own addresses in place when the caller sends none', function () {
    h3ComgateCreate([]);

    expect($GLOBALS['h3ComgateSent'][0])->not->toHaveKey('url_paid')->not->toHaveKey('url_cancelled')->not->toHaveKey('url_pending');
});

it('answers a payment with a foreign return address 422 through the payment service, never a gateway failure', function () {
    [, $org] = $this->customerWithOrganization();

    try {
        app(PaymentService::class)->createIntent($org, Money::minor(10000, 'CZK'), 'order', 'order', 'ord_h3', CommandContext::system('test')->withScope($org->id), [
            'provider' => 'comgate', 'method' => 'card', 'return_urls' => ['success' => 'https://evil.example/ok'],
        ]);
        $this->fail('a foreign return address was accepted');
    } catch (DomainError $e) {
        expect($e->error)->toBe('payment_return_url_invalid')->and($e->status)->toBe(422);
    }
    expect($GLOBALS['h3ComgateSent'])->toBe([])
        ->and(PaymentIntent::query()->where('organization_id', $org->id)->value('state'))->toBe('FAILED');
});
