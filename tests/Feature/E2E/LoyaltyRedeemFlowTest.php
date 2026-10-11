<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\LoyaltyRedemptions;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Loyalty\Models\LoyaltyRedemption;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * G3 end to end — owner decision G-R2: a customer redeems loyalty points for a discount, over the real routes only.
 *
 *  1. The customer signs up and has points (earning them is its own flow, covered by the loyalty tests: here they are credited
 *     as an arrangement). The panel shows what may be redeemed.
 *  2. The cart: a web hosting for a year, the customer asks for 99 points (refused, the minimum is 100), then for 300; the quote
 *     carries a line of its own (−300 Kč before VAT, VAT on it), the renewal stays at the list price.
 *  3. The order is paid by card (the gateway double answers the callback), the hosting is provisioned (a stateful ISPConfig
 *     double), the points are spent once, the document has the points line, the customer is told.
 *  4. Finance credits half of the hosting: the money returned is half of what was PAID (the points line goes back by the same
 *     half) and half of the points come back.
 *  5. A second order by bank transfer reserves points; the customer cancels it before paying and the points are free again.
 *
 * Asserts never depend on row order, and nothing the customer reads names a vendor.
 */

beforeEach(function () {
    e2eSeedPlatform();
    e2eWebInfrastructure();
    e2eComgateEnvironment();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
    $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'UTC'));
});

const E2E_LOYALTY_VENDORS = ['ispconfig', 'comgate', 'wedos', 'proxmox', 'pterodactyl'];

function e2eLoyaltyNoVendors(mixed $payload): void
{
    $text = strtolower(json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '');
    foreach (E2E_LOYALTY_VENDORS as $vendor) {
        expect($text)->not->toContain($vendor);
    }
}

/** One request as finance with a fresh step-up; the customer's browser session is dropped first. */
function e2eLoyaltyAsFinance(object $test, User $finance, string $invoiceId, string $key, array $body): TestResponse
{
    $test->flushSession();
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $test->actingAs($finance, 'sanctum');

    return $test->withHeader('Idempotency-Key', 'e2e-'.$key)->postJson("/v1/invoices/{$invoiceId}/credit-note", $body);
}

it('redeems points in the cart, spends them with the card payment, shows them on the document and gives them back with a credit note', function () {
    $panel = [];
    $gate = [];
    LaravelNotification::fake();
    e2eIspPanel($panel);
    e2eComgateFake($gate);
    [$user, $org] = e2eSignUp($this, 'body.e2e@example.cz', 'Bodovka s.r.o.');
    LoyaltyPoint::query()->create(['organization_id' => $org->id, 'rule' => 'manual', 'reference' => 'e2e-arrange', 'points' => 500, 'note' => 'Odměna od podpory']); // arrangement: points earned earlier

    // ── 1. the panel shows the points and what may be redeemed ───────────────────────────────────────────────────
    $rewards = $this->withHeaders(e2eHeaders('rewards'))->getJson('/v1/account/rewards')->assertOk()->json('data');
    expect($rewards['points'])->toBe(500)->and($rewards['redeem'])->toMatchArray(['available' => 500, 'reserved' => 0, 'min_points' => 100, 'cap_pct' => 20]);

    // ── 2. the cart: the minimum is refused, 300 points become a line of their own ───────────────────────────────
    $this->withHeaders(e2eHeaders('cart'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'config' => ['fqdn' => 'bodovka-e2e.cz']]], 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();
    $this->withHeaders(e2eHeaders('redeem-min'))->postJson('/v1/cart/loyalty', ['points' => 99])->assertStatus(422)->assertJsonPath('error', 'loyalty_below_minimum');
    $this->withHeaders(e2eHeaders('redeem'))->postJson('/v1/cart/loyalty', ['points' => 300])->assertOk()->assertJsonPath('points', 300);
    $quote = $this->withHeaders(e2eHeaders('quote'))->postJson('/v1/cart/quote')->assertOk()->json('data');
    $line = collect($quote['lines'])->firstWhere('sku', LoyaltyRedemptions::SKU);
    expect($line['net'])->toBe(-30000)->and($line['tax'])->toBe(-6300)->and($line['name'])->toBe('Sleva za věrnostní body (300 bodů)')
        ->and($quote['discount'])->toBe(30000)->and($quote['renewal_total'])->toBe(189000)->and($quote['loyalty']['applied'])->toBe(300);
    e2eLoyaltyNoVendors([$line, $quote['loyalty']]); // the points line and its explanation (the service lines of a quote are the cart's own concern)

    // ── 3. card payment, provisioning, the points are spent once ─────────────────────────────────────────────────
    $placed = $this->withHeaders(e2eHeaders('order'))->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => e2eConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect((int) $order->total_minor)->toBe(192390)->and(LoyaltyRedemption::query()->where('order_id', $order->id)->value('state'))->toBe(LoyaltyRedemption::RESERVED)
        ->and($this->withHeaders(e2eHeaders('rewards-reserved'))->getJson('/v1/account/rewards')->json('data.redeem'))->toMatchArray(['available' => 200, 'reserved' => 300]);
    $gate['total'] = $order->total_minor;
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk(); // the gateway says it twice
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    app(OutboxPublisher::class)->relayPending();
    expect(Service::query()->where('organization_id', $org->id)->where('product_key', 'web-hosting')->sole()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE)
        ->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RULE)->sole()->points)->toBe(-300);

    $rewards = $this->withHeaders(e2eHeaders('rewards-after'))->getJson('/v1/account/rewards')->assertOk()->json('data');
    $earned = (int) LoyaltyPoint::query()->where('organization_id', $org->id)->whereNotIn('rule', ['manual', LoyaltyRedemptions::RULE])->sum('points'); // what the paid order, the payment and the first service earned
    expect($rewards['points'])->toBe(200 + $earned)->and($rewards['redeem']['reserved'])->toBe(0)->and(collect($rewards['history'])->pluck('rule'))->toContain(LoyaltyRedemptions::RULE)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Věrnostní body uplatněny: 300')->exists())->toBeTrue();

    $statement = Invoice::query()->where('order_id', $order->id)->where('type', 'statement')->sole();
    $document = $this->withHeaders(e2eHeaders('document'))->getJson("/v1/invoices/{$statement->id}")->assertOk()->json();
    $documentLine = collect($document['lines'] ?? $document['data']['lines'] ?? [])->firstWhere('sku', LoyaltyRedemptions::SKU);
    expect($documentLine['net']['minor'])->toBe(-30000)->and($documentLine['description'])->toBe('Sleva za věrnostní body (300 bodů)');
    e2eLoyaltyNoVendors([$document, $rewards]);

    // ── 4. finance credits half of the hosting: half of what was PAID comes back, and half of the points ─────────
    $ledger = app(LedgerService::class);
    $wallet = fn (): int => $ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor;
    $before = $wallet();
    $hosting = $statement->lines()->where('sku', 'web-hosting-standard')->sole();
    $half = e2eLoyaltyAsFinance($this, $this->staff('billing_finance_admin'), $statement->id, 'cn-half', ['reason' => 'Polovina období', 'amounts' => [(string) $hosting->id => intdiv((int) $hosting->total_minor, 2)], 'return_to_credit' => true])->assertCreated();
    expect($half->json('invoice.total_minor'))->toBe(-(114345 - 18150))->and($half->json('returned_to_credit.minor'))->toBe(114345 - 18150)->and($wallet() - $before)->toBe(114345 - 18150);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending();
    expect((int) LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RETURN_RULE)->sum('points'))->toBe(150)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Uplatněné body se vrátily: 150')->exists())->toBeTrue()
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();

    // ── 5. a second order by transfer reserves points; cancelled before payment, they are free again ─────────────
    $this->flushSession();
    $this->actingAs($user, 'sanctum'); // the customer again (the finance request above replaced the test client's user)
    $this->withHeaders(e2eHeaders('cart-2'))->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'config' => ['fqdn' => 'bodovka-druha.cz']]], 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();
    $this->withHeaders(e2eHeaders('redeem-2'))->postJson('/v1/cart/loyalty', ['points' => 100])->assertOk();
    $second = $this->withHeaders(e2eHeaders('quote-2'))->postJson('/v1/cart/quote')->assertOk()->json('data');
    $bank = $this->withHeaders(e2eHeaders('order-2'))->postJson('/v1/orders', ['quote_id' => $second['quote_id'], 'consents' => e2eConsents(), 'payment' => ['mode' => 'bank']])->assertCreated();
    $free = fn () => $this->withHeaders(e2eHeaders('rewards-free'))->getJson('/v1/account/rewards')->json('data.redeem');
    $reservedBefore = $free();
    expect($reservedBefore['reserved'])->toBe(100);
    $this->withHeaders(e2eHeaders('cancel-2'))->postJson("/v1/orders/{$bank->json('order_id')}/transition", ['to' => 'cancelled', 'reason' => 'Rozmyslel jsem si to'])->assertOk();
    expect($free())->toMatchArray(['reserved' => 0, 'available' => $reservedBefore['available'] + 100])
        ->and(LoyaltyRedemption::query()->where('order_id', $bank->json('order_id'))->value('state'))->toBe(LoyaltyRedemption::RELEASED);
});
