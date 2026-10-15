<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\ComgateCheck;
use Onhost\Domain\Payments\Models\PaymentEvent;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\Payments\Comgate\ComgatePaymentProvider;

/*
 * W1: who may tell the platform that a Comgate payment changed. The public route POST /v1/webhooks/payments/comgate accepted a
 * notification from ANY address while COMGATE_CALLBACK_IPS was empty (the default), so every forged notification made the
 * platform call Comgate. Now an empty list refuses every notification (fail closed), entries are trimmed, blank ones dropped,
 * and an entry may be an address or a CIDR range (IPv4 and IPv6). A refused notification never reaches Comgate: the status
 * is not re-read, no payment event is stored. Comgate is faked; nothing reaches the network.
 */

const CG_TRANS = 'W1AL-LOWL-IST1';

/** The Comgate double: a created payment and its status, counting the status reads. @param array<string,mixed> $gate */
function cgAllowlistFake(array &$gate): void
{
    $gate += ['status_calls' => 0, 'total' => 0];
    Http::fake(function (HttpRequest $request) use (&$gate) {
        if (str_contains($request->url(), 'payments.comgate.cz/v2.0/payment/transId/')) {
            $gate['status_calls']++;

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => CG_TRANS, 'status' => 'PAID', 'price' => $gate['total'], 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2', 'refId' => 'x']);
        }
        if (str_ends_with($request->url(), 'payments.comgate.cz/v2.0/payment')) {
            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => CG_TRANS, 'redirect' => 'https://payments.comgate.cz/client/instructions/index?id='.CG_TRANS]);
        }

        return Http::response(['code' => 1400, 'message' => 'unexpected call'], 500);
    });
}

/** A pending Comgate top-up of 1 500 Kč the notification is about. */
function cgAllowlistIntent(Organization $org, array &$gate): PaymentIntent
{
    $gate['total'] = 150000;

    return app(PaymentService::class)->createIntent($org, Money::minor(150000, 'CZK'), 'topup', 'wallet', $org->id, CommandContext::system('w1'), ['provider' => 'comgate', 'method' => 'card', 'idempotency_key' => 'w1-allowlist-1']);
}

/** The notification as Comgate posts it, from `$ip`, to the real route. */
function cgNotify(object $test, string $ip, int $total)
{
    return $test->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/v1/webhooks/payments/comgate', ['transId' => CG_TRANS, 'status' => 'PAID', 'price' => $total, 'curr' => 'CZK', 'secret' => 'w1-callback-secret']);
}

function cgVerifyFrom(string $ip): array
{
    return app(ComgatePaymentProvider::class)->verifyWebhook(Request::create('/v1/webhooks/payments/comgate', 'POST', ['transId' => CG_TRANS, 'status' => 'PAID'], [], [], ['REMOTE_ADDR' => $ip]));
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    putenv('COMGATE_MERCHANT=123456');
    putenv('COMGATE_SECRET=w1-callback-secret');
    $_ENV['COMGATE_MERCHANT'] = '123456';
    $_ENV['COMGATE_SECRET'] = 'w1-callback-secret';
    config()->set('onhost.payments.comgate.merchant', '123456');
});

it('settles a notification from an allow-listed address: entries are trimmed and blank ones dropped', function () {
    config()->set('onhost.payments.comgate.callback_allowlist', [' 198.51.100.7 ', '', '  ', '10.0.0.1']);
    $gate = [];
    cgAllowlistFake($gate);
    [, $org] = $this->customerWithOrganization();
    $intent = cgAllowlistIntent($org, $gate);

    cgNotify($this, '198.51.100.7', $gate['total'])->assertOk()->assertJson(['result' => 'settled']);

    expect($gate['status_calls'])->toBe(1) // the status was re-read from Comgate, the notification itself decided nothing
        ->and($intent->fresh()->isSucceeded())->toBeTrue();
});

it('settles a notification from an address inside an allow-listed CIDR range, IPv4 and IPv6', function () {
    config()->set('onhost.payments.comgate.callback_allowlist', ['198.51.100.0/24', '2001:db8::/32']);
    $gate = [];
    cgAllowlistFake($gate);
    [, $org] = $this->customerWithOrganization();
    $intent = cgAllowlistIntent($org, $gate);

    cgNotify($this, '198.51.100.200', $gate['total'])->assertOk()->assertJson(['result' => 'settled']);
    expect($intent->fresh()->isSucceeded())->toBeTrue();

    expect(cgVerifyFrom('2001:db8:0:1::5'))->toMatchArray(['provider_id' => CG_TRANS, 'state' => 'PAID']);
    expect(fn () => cgVerifyFrom('2001:db9::5'))->toThrow(DomainError::class, 'not allow-listed');
    expect(fn () => cgVerifyFrom('198.51.101.1'))->toThrow(DomainError::class, 'not allow-listed');
});

it('refuses a notification from an address that is not listed and never asks Comgate about it', function () {
    config()->set('onhost.payments.comgate.callback_allowlist', ['198.51.100.7', '10.0.0.0/8']);
    $gate = [];
    cgAllowlistFake($gate);
    [, $org] = $this->customerWithOrganization();
    $intent = cgAllowlistIntent($org, $gate);

    $response = cgNotify($this, '203.0.113.9', $gate['total'])->assertStatus(403);

    expect((string) $response->getContent())->toContain('webhook_source_rejected');
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/payment/transId/'));
    expect($gate['status_calls'])->toBe(0)
        ->and(PaymentEvent::query()->count())->toBe(0)
        ->and($intent->fresh()->isSucceeded())->toBeFalse();
});

it('refuses every notification while the allow-list is empty, blank, or only allows everyone', function (array $allowlist) {
    config()->set('onhost.payments.comgate.callback_allowlist', $allowlist);
    $gate = [];
    cgAllowlistFake($gate);
    [, $org] = $this->customerWithOrganization();
    $intent = cgAllowlistIntent($org, $gate);

    $response = cgNotify($this, '127.0.0.1', $gate['total'])->assertStatus(403);

    expect((string) $response->getContent())->toContain('webhook_source_rejected');
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/payment/transId/'));
    expect($gate['status_calls'])->toBe(0)
        ->and(PaymentEvent::query()->count())->toBe(0)
        ->and($intent->fresh()->isSucceeded())->toBeFalse();
})->with([
    'empty' => [[]],
    'blank entries only' => [[' ', '']],
    'allow-all IPv4 range' => [['0.0.0.0/0']],
    'allow-all IPv6 range' => [['::/0']],
]);

it('tells the administration that an empty allow-list refuses every notification', function () {
    config()->set('onhost.payments.comgate.callback_allowlist', [' ', '']);
    $empty = app(ComgateCheck::class)->status();
    expect($empty['callback_allowlist'])->toBe(0)
        ->and($empty['callback_notice'])->toContain('odmítne každou notifikaci');

    config()->set('onhost.payments.comgate.callback_allowlist', [' 198.51.100.7 ', '', '2001:db8::/32']);
    $listed = app(ComgateCheck::class)->status();
    expect($listed['callback_allowlist'])->toBe(2)->and($listed['callback_notice'])->toBeNull();
});
