<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Payments\CardPayments;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Errors\DomainError;

/*
 * C-R10e (J4, TASK-0155): until the owner stores the Comgate merchant account (I-R10: it exists only after Comgate reviewed the
 * site), a card order must not reach the gateway with empty credentials and come back as a provider error. The public data says
 * card payments are off (the cart hides the card tile), an order that asks for the card anyway is refused up front — before a
 * guest account is created — with a plain sentence that names the bank transfer, and no request leaves for the gateway.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
    Http::preventStrayRequests();
    config(['onhost.payments.default' => 'comgate', 'onhost.payments.comgate.secret_ref' => 'env://ONHOST_TEST_NO_COMGATE', 'onhost.payments.comgate.merchant' => null]);
});

function cardGuestPayload(string $mode): array
{
    return [
        'customer' => ['email' => 'karta@firma.cz', 'name' => 'Karel Karta', 'country' => 'CZ', 'street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start']],
        'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []],
        'payment' => ['mode' => $mode], 'terms' => true,
    ];
}

it('says in the public data that card payments are off while the gateway has no credentials', function () {
    expect(app(CardPayments::class)->available())->toBeFalse();

    $js = $this->get('/surfaces/onhost-data.js')->assertOk()->getContent();
    preg_match('/var D = (\{.*\});\n  function L/s', $js, $m);
    expect(json_decode($m[1], true)['cs']['cardPayments'])->toBeFalse()
        ->and($js)->toContain('cardPayments: function () { return D.cs.cardPayments; }');
});

it('refuses a guest card order up front, in Czech, before any account exists and without calling the gateway', function () {
    $response = $this->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs', 'Idempotency-Key' => 'card-guest-1'])->postJson('/v1/checkout/guest', cardGuestPayload('gateway'));

    $response->assertStatus(409)->assertJsonPath('error', 'payment_card_unavailable');
    expect($response->json('message'))->toContain('bankovním převodem')->not->toContain('Comgate')
        ->and(User::query()->where('email', 'karta@firma.cz')->exists())->toBeFalse()
        ->and(Order::query()->count())->toBe(0)
        ->and(PaymentIntent::query()->count())->toBe(0);
    Http::assertNothingSent();

    // the bank transfer works
    $this->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'card-guest-2'])->postJson('/v1/checkout/guest', cardGuestPayload('bank'))->assertCreated();
});

it('refuses a signed-in card order before the gateway is called, and lets the bank transfer through', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $quote = fn () => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []];
    app()->setLocale('en');

    expect(fn () => app(CheckoutService::class)->placeOrder($quote(), $org, $owner, $consents, ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'], 'card-signed-in-1', $this->contextFor($owner, $org)))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('payment_card_unavailable')->and($e->getMessage())->toContain('bank transfer'));
    expect(PaymentIntent::query()->count())->toBe(0)->and(Order::query()->count())->toBe(0);
    Http::assertNothingSent();

    $placed = app(CheckoutService::class)->placeOrder($quote(), $org, $owner, $consents, ['mode' => 'bank'], 'card-signed-in-2', $this->contextFor($owner, $org));
    expect($placed['bank_instructions'])->not->toBeNull();
});
