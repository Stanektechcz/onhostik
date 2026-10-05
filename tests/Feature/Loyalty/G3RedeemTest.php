<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Loyalty\Commands\RedeemPointsCommand;
use Onhost\Domain\Loyalty\LoyaltyExpiry;
use Onhost\Domain\Loyalty\LoyaltyRedemptions;
use Onhost\Domain\Loyalty\LoyaltyService;
use Onhost\Domain\Loyalty\Models\LoyaltyExpiryNotice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Loyalty\Models\LoyaltyRedemption;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * G3 — owner decision G-R2: loyalty points can be redeemed for a discount.
 *
 * Only when the customer asks (`loyalty.redeem` through the bus), always as a line of its own on the order and its document:
 * 1 point = 1 CZK off the price before VAT, at least 100 points, never more than the organization has free, and every discount on
 * the lines points may discount stays within 20 % of their list price before VAT (a promo code shares that room). A domain keeps
 * its list price, a credit top-up is no cart line. The order reserves the points (under a lock on the organization), the payment
 * spends them, an unpaid order that is cancelled releases them, a credit note gives them back in the share it credited. Points
 * expire 24 months after they were credited, with a warning 30 days before; the level stays.
 *
 * The web hosting "standard" costs 1 890 Kč a year before VAT (21 %): its cap is 378 Kč, i.e. 378 points.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    config()->set('onhost.billing.fx.fetch', false);
});

const G3_YEAR_NET = 189000;

/** Points credited to the organization (a manual award, so no level reward or wallet movement gets in the way). */
function g3Points(Organization $org, int $points, ?string $at = null): LoyaltyPoint
{
    $row = LoyaltyPoint::query()->create(['organization_id' => $org->id, 'rule' => 'manual', 'reference' => 'g3:'.uniqid('', true), 'points' => $points, 'note' => 'test']);
    if ($at !== null) {
        $row->forceFill(['created_at' => Carbon::parse($at)])->save();
    }

    return $row;
}

function g3Hosting(int $qty = 1): array
{
    return [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => $qty]];
}

function g3Quote(Organization $org, array $items, int $points, string $currency = 'CZK', ?string $promo = null): Quote
{
    return app(QuoteService::class)->quote($items, $currency, ['country' => 'CZ'], 12, $promo, $org, 'cs', null, $points);
}

function g3Ctx(User $user, Organization $org): CommandContext
{
    return new CommandContext('user', $user->id, $org->id, null, '127.0.0.1', 'pest', 'test-session');
}

function g3Consents(): array
{
    return ['terms' => [], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jan Novák'], 'registry_terms_cz' => ['person' => 'Jan Novák']];
}

function g3Place(object $test, Quote $quote, User $owner, Organization $org, string $mode = 'wallet', ?string $key = null): Order
{
    return app(CheckoutService::class)->placeOrder($quote, $org, $owner, g3Consents(), ['mode' => $mode], $key ?? 'g3-'.uniqid(), g3Ctx($owner, $org))['order']->refresh();
}

function g3Credit(object $test, User $owner, Organization $org, string $amount = '10000'): void
{
    app(WalletService::class)->topup($org, Money::decimal($amount, 'CZK'), 'card', 'g3-seed-'.uniqid(), g3Ctx($owner, $org), bankProvider: 'comgate');
}

function g3Line(Quote|Invoice $where, string $sku): ?array
{
    if ($where instanceof Quote) {
        return collect($where->lines)->first(fn ($l) => $l['sku'] === $sku);
    }
    $line = $where->lines()->where('sku', $sku)->first();

    return $line?->toArray();
}

function g3Returned(Organization $org): int
{
    return (int) LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RETURN_RULE)->sum('points');
}

it('redeems points through the bus as a line of its own, reserves them with the order and spends them with the payment', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3a@example.cz']);
    g3Points($org, 500);
    g3Credit($this, $owner, $org);
    $this->actingAs($owner, 'sanctum');
    $this->withHeader('Idempotency-Key', 'g3-cart')->putJson('/v1/cart', ['items' => g3Hosting(), 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();

    $chosen = $this->withHeader('Idempotency-Key', 'g3-redeem')->postJson('/v1/cart/loyalty', ['points' => 300])->assertOk()->json();
    expect($chosen)->toMatchArray(['points' => 300, 'available' => 500, 'min_points' => 100, 'cap_pct' => 20])
        ->and($this->getJson('/v1/cart')->json('data.loyalty_points'))->toBe(300);

    $quote = $this->withHeader('Idempotency-Key', 'g3-quote')->postJson('/v1/cart/quote')->assertOk()->json('data');
    $line = collect($quote['lines'])->firstWhere('sku', LoyaltyRedemptions::SKU);
    expect($line)->not->toBeNull()
        ->and($line['net'])->toBe(-30000)->and($line['tax'])->toBe(-6300)->and($line['renewal_net'])->toBe(0) // a one-off: renewals are at the list price
        ->and($quote['subtotal'])->toBe(G3_YEAR_NET)->and($quote['discount'])->toBe(30000)
        ->and($quote['total'])->toBe(G3_YEAR_NET - 30000 + (int) round((G3_YEAR_NET - 30000) * 0.21))
        ->and($quote['renewal_total'])->toBe(G3_YEAR_NET)
        ->and($quote['loyalty'])->toMatchArray(['requested' => 300, 'applied' => 300, 'value' => 30000, 'max_points' => 378, 'reason' => null]);

    $placed = $this->withHeader('Idempotency-Key', 'g3-order')->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => g3Consents(), 'payment' => ['mode' => 'wallet']])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $redemption = LoyaltyRedemption::query()->where('order_id', $order->id)->sole();

    // paid from the credit at once: the reservation became a spend, a row of its own in the history
    expect($order->paid_at)->not->toBeNull()->and($redemption->state)->toBe(LoyaltyRedemption::CONSUMED)->and($redemption->points)->toBe(300)
        ->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RULE)->sole()->points)->toBe(-300)
        ->and(app(LoyaltyService::class)->points($org->id))->toBe(200 + (int) LoyaltyPoint::query()->where('organization_id', $org->id)->whereIn('rule', ['order.paid', 'payment.on_time'])->sum('points')) // what the paid order earned comes on top
        ->and(Cart::query()->where('user_id', $owner->id)->value('loyalty_points'))->toBeNull(); // the next order starts without it
    $item = OrderItem::query()->where('order_id', $order->id)->where('product_key', LoyaltyRedemptions::PRODUCT)->sole();
    expect($item->state)->toBe(LoyaltyRedemptions::ITEM_STATE)->and((int) $item->total_minor)->toBe(-36300);

    // the document carries it as a line of its own, never folded into the price of the hosting
    $statement = Invoice::query()->findOrFail($order->invoice_id);
    expect(g3Line($statement, LoyaltyRedemptions::SKU))->toMatchArray(['net_minor' => -30000, 'tax_minor' => -6300, 'total_minor' => -36300])
        ->and(g3Line($statement, 'web-hosting-standard'))->toMatchArray(['net_minor' => G3_YEAR_NET, 'discount_minor' => 0])
        ->and((int) $statement->total_minor)->toBe((int) $order->total_minor);

    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Věrnostní body uplatněny: 300')->exists())->toBeTrue();
    $rewards = $this->getJson('/v1/account/rewards')->assertOk()->json('data');
    expect($rewards['redeem'])->toMatchArray(['reserved' => 0, 'min_points' => 100, 'cap_pct' => 20])->and(collect($rewards['history'])->pluck('rule'))->toContain(LoyaltyRedemptions::RULE)
        ->and($rewards['level']['key'])->toBe(app(LoyaltyService::class)->levelFor(app(LoyaltyService::class)->standing($org->id))['key']); // spending never takes a level
});

it('refuses a guest, fewer than the minimum and more than the organization has free', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3b@example.cz']);
    g3Points($org, 500);
    $this->withHeader('Idempotency-Key', 'g3-guest')->postJson('/v1/cart/loyalty', ['points' => 100])->assertStatus(401); // a guest has no points
    $this->actingAs($owner, 'sanctum');

    $this->withHeader('Idempotency-Key', 'g3-min')->postJson('/v1/cart/loyalty', ['points' => 99])->assertStatus(422)->assertJsonPath('error', 'loyalty_below_minimum');
    $this->withHeader('Idempotency-Key', 'g3-many')->postJson('/v1/cart/loyalty', ['points' => 501])->assertStatus(422)->assertJsonPath('error', 'loyalty_points_unavailable');
    $this->withHeader('Idempotency-Key', 'g3-neg')->postJson('/v1/cart/loyalty', ['points' => -5])->assertStatus(422);
    $this->withHeader('Idempotency-Key', 'g3-ok')->postJson('/v1/cart/loyalty', ['points' => 100])->assertOk();
    $this->withHeader('Idempotency-Key', 'g3-zero')->postJson('/v1/cart/loyalty', ['points' => 0])->assertOk(); // 0 takes the choice back
    expect(Cart::query()->where('user_id', $owner->id)->value('loyalty_points'))->toBeNull();

    // the quote itself never applies fewer than the minimum: a request below it is priced without points, and says why
    $quote = g3Quote($org, g3Hosting(), 50);
    expect(g3Line($quote, LoyaltyRedemptions::SKU))->toBeNull()->and($quote->versions['loyalty']['reason'])->toBe('below_minimum');

    auth()->forgetGuards();
    $this->flushSession();
    $this->app['auth']->guard('web')->logout();
    $this->withHeader('Idempotency-Key', 'g3-guest')->postJson('/v1/cart/loyalty', ['points' => 100])->assertStatus(401);
});

it('caps every discount of the eligible lines at 20 % of their list price before VAT, and a promo code shares that room', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'g3c@example.cz']);
    g3Points($org, 1000);

    $alone = g3Quote($org, g3Hosting(), 1000);
    expect($alone->versions['loyalty'])->toMatchArray(['requested' => 1000, 'applied' => 378, 'value' => 37800, 'max_points' => 378])
        ->and($alone->discount_minor)->toBe(37800)->and(g3Line($alone, LoyaltyRedemptions::SKU)['net'])->toBe(-37800);

    // ONHOST10 takes 10 % (18 900): the points fill only the rest of the 20 % (another 18 900), never 20 % on top of the code
    $both = g3Quote($org, g3Hosting(), 1000, 'CZK', 'ONHOST10');
    $hosting = g3Line($both, 'web-hosting-standard');
    expect($hosting['discount'])->toBe(18900)->and($both->versions['loyalty'])->toMatchArray(['applied' => 189, 'value' => 18900])
        ->and($both->discount_minor)->toBe(37800)->and($both->discount_minor)->toBeLessThanOrEqual(intdiv(G3_YEAR_NET * 20, 100));

    // a small order cannot carry the minimum within the cap: priced without points, and the cap is named as the reason
    $small = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ'], 1, null, $org, 'cs', null, 100);
    expect(g3Line($small, LoyaltyRedemptions::SKU))->toBeNull()->and($small->versions['loyalty']['reason'])->toBe('cap')->and($small->discount_minor)->toBe(0);
});

it('never discounts a domain below its list price and has no way onto a credit top-up', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'g3d@example.cz']);
    g3Points($org, 1000);

    $domainOnly = g3Quote($org, [['product_key' => 'domain', 'config' => ['fqdn' => 'g3-body.cz', 'period_years' => 1]]], 500);
    expect(g3Line($domainOnly, LoyaltyRedemptions::SKU))->toBeNull()->and($domainOnly->versions['loyalty']['reason'])->toBe('nothing_eligible')->and($domainOnly->discount_minor)->toBe(0);

    // a domain next to a hosting: the cap is measured on the hosting alone and the domain keeps its list price
    $mixed = g3Quote($org, array_merge(g3Hosting(), [['product_key' => 'domain', 'config' => ['fqdn' => 'g3-body.cz', 'period_years' => 1]]]), 1000);
    $domain = g3Line($mixed, 'domain-cz-register');
    expect($mixed->versions['loyalty'])->toMatchArray(['applied' => 378, 'eligible' => ['l1']])->and($domain['discount'])->toBe(0)->and($domain['net'])->toBe($domain['unit_net'])
        ->and(g3Line($mixed, LoyaltyRedemptions::SKU)['config']['loyalty']['eligible_lines'])->toBe(['l1']);

    // a credit top-up is a payment, not a product: no cart line can be one, so points never reach it
    expect(fn () => g3Quote($org, [['product_key' => 'credit', 'plan_key' => 'topup']], 500))->toThrow(DomainError::class);
});

it('converts the value of points into euro at the bank rate, and redeems nothing while the rate is unknown', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'g3e@example.cz'], ['currency' => 'EUR']);
    g3Points($org, 1000);

    $unknown = g3Quote($org, g3Hosting(), 300, 'EUR');
    expect(g3Line($unknown, LoyaltyRedemptions::SKU))->toBeNull()->and($unknown->versions['loyalty']['reason'])->toBe('rate_unknown');

    ExchangeRate::query()->create(['source' => 'cnb', 'currency' => 'EUR', 'valid_on' => now('Europe/Prague')->toDateString(), 'amount' => 1, 'rate_micro' => 25_000_000, 'fetched_at' => now()]);
    $quote = g3Quote($org, g3Hosting(), 300, 'EUR');
    $line = g3Line($quote, LoyaltyRedemptions::SKU);
    expect($quote->currency)->toBe('EUR')->and($line['net'])->toBe(-1200) // 300 CZK at 25.000 = 12.00 EUR
        ->and($line['config']['loyalty'])->toMatchArray(['points' => 300, 'value_minor' => 1200, 'rate_micro' => 25_000_000]);
});

it('releases the points of an unpaid order that is cancelled, and two orders never spend the same points', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3f@example.cz']);
    g3Points($org, 500);
    $redemptions = app(LoyaltyRedemptions::class);

    $first = g3Quote($org, g3Hosting(), 300);
    $second = g3Quote($org, array_merge(g3Hosting(), [['product_key' => 'domain', 'config' => ['fqdn' => 'g3-druha.cz', 'period_years' => 1]]]), 300); // another order, priced while nothing was reserved yet
    $order = g3Place($this, $first, $owner, $org, 'bank', 'g3-bank-1');
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($redemptions->reserved($org->id))->toBe(300)->and($redemptions->available($org->id))->toBe(200);

    // the second order waits for the lock and finds the points taken: nothing is placed, the customer refreshes the cart
    expect(fn () => g3Place($this, $second, $owner, $org, 'bank', 'g3-bank-2'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('loyalty_points_unavailable')->and($e->status)->toBe(409));
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(1)->and($second->refresh()->state)->toBe('open');

    // a retry of the first placement with its key is the same order, with the same one reservation
    $again = app(CheckoutService::class)->placeOrder($first, $org, $owner, g3Consents(), ['mode' => 'bank'], 'g3-bank-1', $this->contextFor($owner, $org))['order'];
    expect($again->id)->toBe($order->id)->and(LoyaltyRedemption::query()->where('organization_id', $org->id)->count())->toBe(1);

    app(CheckoutService::class)->cancel($order, $this->contextFor($owner, $org), 'rozmyslel jsem si to', false);
    expect(LoyaltyRedemption::query()->where('order_id', $order->id)->value('state'))->toBe(LoyaltyRedemption::RELEASED)
        ->and($redemptions->available($org->id))->toBe(500)->and(app(LoyaltyService::class)->points($org->id))->toBe(500)
        ->and(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RULE)->exists())->toBeFalse();
});

it('gives the points back in the share a credit note credits, once, and the money of what the points paid stays points', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3g@example.cz']);
    g3Points($org, 500);
    g3Credit($this, $owner, $org);
    $order = g3Place($this, g3Quote($org, g3Hosting(), 300), $owner, $org);
    $statement = Invoice::query()->findOrFail($order->invoice_id);
    $hosting = $statement->lines()->where('sku', 'web-hosting-standard')->sole();
    $ctx = CommandContext::system('test')->withScope($org->id);
    $available = fn () => app(WalletService::class)->balances($org, 'CZK')['available']->minor;
    $before = $available();

    // half of the hosting is given back: the points line goes back by the same half, so the money is half of what was PAID
    $given = app(InvoiceService::class)->giveBack($statement, [$hosting->id => intdiv((int) $hosting->total_minor, 2)], 'Polovina období', $ctx);
    $note = $given['credit_note'];
    expect((int) $note->total_minor)->toBe(-(intdiv(228690, 2) - 18150))
        ->and($note->lines()->where('sku', LoyaltyRedemptions::SKU)->sole()->total_minor)->toBe(18150)
        ->and($given['to_credit_minor'])->toBe(intdiv(228690, 2) - 18150)->and($available() - $before)->toBe(intdiv(228690, 2) - 18150);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending(); // a redelivery gives nothing twice
    expect(g3Returned($org))->toBe(150)->and(LoyaltyRedemption::query()->where('order_id', $order->id)->value('returned_points'))->toBe(150)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Uplatněné body se vrátily: 150')->exists())->toBeTrue();

    // the rest of the document: the rest of the points, never more than were spent
    app(InvoiceService::class)->giveBack($statement->refresh(), null, 'Storno zbytku', $ctx);
    app(OutboxPublisher::class)->relayPending();
    expect(g3Returned($org))->toBe(300)->and($statement->refresh()->state)->toBe(Invoice::CREDITED)
        ->and($available() - $before)->toBe((int) $order->total_minor); // the money that was paid, not the list price
});

it('settles an order with an undelivered line: the points share of that line goes back with it', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3h@example.cz']);
    g3Points($org, 800);
    g3Credit($this, $owner, $org);
    $order = g3Place($this, g3Quote($org, g3Hosting(2), 600), $owner, $org); // two hostings (two lines), 600 points = 600 Kč + VAT
    expect((int) $order->total_minor)->toBe(2 * 228690 - 72600);
    $order->forceFill(['state' => OrderStateMachine::PARTIALLY_ACTIVE])->save();
    $lines = OrderItem::query()->where('order_id', $order->id)->where('product_key', 'web-hosting')->orderBy('created_at')->get();
    $lines[0]->forceFill(['state' => 'active'])->save();
    $lines[1]->forceFill(['state' => 'failed'])->save();

    $settled = app(OrderSettlement::class)->settle($order, CommandContext::system('test'));
    $note = Invoice::query()->where('order_id', $order->id)->where('type', 'credit_note')->sole();
    expect($settled)->toMatchArray(['captured_minor' => 228690 - 36300, 'returned_minor' => 228690 - 36300])
        ->and((int) $note->total_minor)->toBe(-(228690 - 36300))
        ->and(app(WalletService::class)->balances($org, 'CZK')['reserved']->minor)->toBe(0);
    // the books: the revenue of the delivered hosting is its price less its share of the points, the VAT likewise
    $ledger = app(LedgerService::class);
    expect($ledger->verifyInvariant()['balanced'])->toBeTrue()
        ->and($ledger->balance(LedgerService::revenueAccount('web', 'CZK'), 'CZK')->minor)->toBe(G3_YEAR_NET - 30000)
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(39690 - 6300);
    app(OutboxPublisher::class)->relayPending();
    expect(g3Returned($org))->toBe(300); // half of the 600 points went with the hosting that was never delivered
});

it('returns every redeemed point when a consumer withdraws from an order nothing of which was delivered', function () {
    app(AutomationLedger::class)->setEnabled('billing.withdrawal', true);
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3w@mailinator.com'], ['type' => 'person', 'name' => 'Jana Nováková', 'billing_email' => 'g3w@mailinator.com']);
    g3Points($org, 500);
    g3Credit($this, $owner, $org);
    $order = g3Place($this, g3Quote($org, g3Hosting(), 300), $owner, $org, 'wallet', 'g3-withdraw');
    expect($order->meta['review']['state'] ?? null)->toBe('pending'); // a throwaway address: paid, documented, held for review
    $order->forceFill(['meta' => array_replace_recursive((array) $order->meta, ['review' => ['state' => 'released']])])->save();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $this->withHeader('Idempotency-Key', 'g3-wd')->postJson("/v1/orders/{$order->id}/withdrawal", ['confirm_refund_to_credit' => true])->assertStatus(202);
    app(OutboxPublisher::class)->relayPending();
    app(OutboxPublisher::class)->relayPending();
    expect($order->refresh()->state)->toBe(OrderStateMachine::CANCELLED)->and(g3Returned($org))->toBe(300)
        ->and(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(1000000); // the credit as it was before the order
});

it('expires points 24 months after they were credited, warns 30 days before once, and never takes the level', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3x@example.cz']);
    $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'UTC'));
    g3Points($org, 400); // credited before the rule existed: counts as credited on 2026-10-05
    $this->travelTo(Carbon::parse('2027-06-01 12:00:00', 'UTC'));
    g3Points($org, 200);
    LoyaltyPoint::query()->create(['organization_id' => $org->id, 'rule' => LoyaltyRedemptions::RULE, 'reference' => 'ord_g3_old', 'points' => -100, 'note' => 'spent']); // spent oldest first
    $loyalty = app(LoyaltyService::class);
    $standing = $loyalty->standing($org->id);
    $level = $loyalty->summary($org->id)['level']['key'];

    // nothing is due a month before: the cutoff lies before the day the rule began
    $this->travelTo(Carbon::parse('2028-08-01 12:00:00', 'UTC'));
    expect(Artisan::call('onhost:loyalty:expire'))->toBe(0)->and(LoyaltyExpiryNotice::query()->count())->toBe(0);

    // 30 days before: one warning for the 300 left of the oldest 400 (100 were spent), the newer 200 are not due
    $this->travelTo(Carbon::parse('2028-09-06 12:00:00', 'UTC'));
    Artisan::call('onhost:loyalty:expire');
    Artisan::call('onhost:loyalty:expire'); // the next day's run must not warn again for the same month
    app(OutboxPublisher::class)->relayPending();
    expect(LoyaltyExpiryNotice::query()->sole())->toMatchArray(['window' => '2028-10', 'points' => 300])
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Věrnostní body brzy propadnou: 300')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'loyalty-expiring')->count())->toBe(1)
        ->and(app(LoyaltyExpiry::class)->upcoming($org->id))->toMatchArray(['points' => 300, 'next_on' => '2028-10-05']);

    // an unpaid order holds 100 of them meanwhile: reserved points count as spent and do not expire under it
    LoyaltyRedemption::query()->create(['organization_id' => $org->id, 'order_id' => 'ord_g3_open', 'state' => LoyaltyRedemption::RESERVED, 'points' => 100, 'value_minor' => 10000, 'currency' => 'CZK', 'reserved_at' => now()]);

    $this->travelTo(Carbon::parse('2028-10-05 12:00:00', 'UTC'));
    Artisan::call('onhost:loyalty:expire');
    Artisan::call('onhost:loyalty:expire'); // twice on the day: once
    app(OutboxPublisher::class)->relayPending();
    expect(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyExpiry::RULE)->sole()->points)->toBe(-200)
        ->and($loyalty->points($org->id))->toBe(300)->and(app(LoyaltyRedemptions::class)->available($org->id))->toBe(200)
        ->and($loyalty->standing($org->id))->toBe($standing)->and($loyalty->summary($org->id)['level']['key'])->toBe($level)
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'Věrnostní body propadly: 200')->exists())->toBeTrue();
});

it('keeps organizations apart: nobody redeems, reserves or gets back another organization\'s points', function () {
    [$ownerA, $a] = $this->customerWithOrganization(['email' => 'g3i@example.cz']);
    [$ownerB, $b] = $this->customerWithOrganization(['email' => 'g3j@example.cz']);
    g3Points($a, 500);
    g3Credit($this, $ownerA, $a);

    // B has nothing of its own to redeem, whatever A holds
    $this->actingAs($ownerB, 'sanctum');
    $this->withHeader('Idempotency-Key', 'g3-b')->postJson('/v1/cart/loyalty', ['points' => 100])->assertStatus(422)->assertJsonPath('error', 'loyalty_points_unavailable')->assertJsonPath('available', 0);

    // a cart is its user's: B's command naming A's cart finds nothing
    $cartA = Cart::query()->create(['user_id' => $ownerA->id, 'state' => 'open', 'currency' => 'CZK', 'commit_months' => 12, 'items' => g3Hosting(), 'expires_at' => now()->addDay()]);
    expect(fn () => app(CommandBus::class)->dispatch(new RedeemPointsCommand($b->id, 'g3-foreign', ['cart_id' => $cartA->id, 'points' => 100]), $this->contextFor($ownerB, $b)))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(404));
    expect($cartA->refresh()->loyalty_points)->toBeNull();

    // A's credit note replayed under B's id gives B nothing
    $order = g3Place($this, g3Quote($a, g3Hosting(), 300), $ownerA, $a);
    $note = app(InvoiceService::class)->creditNote(Invoice::query()->findOrFail($order->invoice_id), 'Storno', CommandContext::system('test')->withScope($a->id));
    expect(app(LoyaltyRedemptions::class)->onCreditNote($b->id, $note->id, CommandContext::system('test')))->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    expect(g3Returned($a))->toBe(300)->and(g3Returned($b))->toBe(0)->and(app(LoyaltyService::class)->points($b->id))->toBe(0);
});

it('applies a choice of points only to the organization it was made for, when one person works for two', function () {
    [$owner, $a] = $this->customerWithOrganization(['email' => 'g3m@example.cz']);
    [, $b] = $this->customerWithOrganization(['email' => 'g3n@example.cz']);
    app(OrganizationService::class)->attachMember($b, $owner, 'org_admin', CommandContext::system('test'), true);
    g3Points($a, 500);
    g3Points($b, 500);
    $this->actingAs($owner, 'sanctum');
    $this->withHeaders(['X-Organization' => $a->id, 'Idempotency-Key' => 'g3-two-cart'])->putJson('/v1/cart', ['items' => g3Hosting(), 'commit_months' => 12, 'currency' => 'CZK'])->assertOk();
    $this->withHeaders(['X-Organization' => $a->id, 'Idempotency-Key' => 'g3-two-a'])->postJson('/v1/cart/loyalty', ['points' => 300])->assertOk();

    // the same cart quoted for B carries no points: the choice was A's
    $forB = $this->withHeaders(['X-Organization' => $b->id, 'Idempotency-Key' => 'g3-two-qb'])->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect(collect($forB['lines'])->firstWhere('sku', LoyaltyRedemptions::SKU))->toBeNull()->and($forB['loyalty'])->toBeNull()->and($forB['discount'])->toBe(0);
    $forA = $this->withHeaders(['X-Organization' => $a->id, 'Idempotency-Key' => 'g3-two-qa'])->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect($forA['loyalty']['applied'])->toBe(300);
});

it('spends the points once however often the payment is reported', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'g3k@example.cz']);
    g3Points($org, 500);
    g3Credit($this, $owner, $org); // what the transfer would have brought to the credit before the order is marked paid
    $order = g3Place($this, g3Quote($org, g3Hosting(), 200), $owner, $org, 'bank', 'g3-bank-paid');
    $ctx = CommandContext::system('test')->withScope($org->id);

    app(CheckoutService::class)->markPaid($order, $ctx, 'bank', $order->payment_intent_id);
    app(CheckoutService::class)->markPaid($order->refresh(), $ctx, 'bank', $order->payment_intent_id);
    app(LoyaltyRedemptions::class)->consume($order, $ctx);
    expect(LoyaltyPoint::query()->where('organization_id', $org->id)->where('rule', LoyaltyRedemptions::RULE)->count())->toBe(1)
        ->and(app(LoyaltyService::class)->points($org->id))->toBe(300);
});

it('shows redeemed points in the cart as a line of their own and names them in the discount row (cart seam)', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/loyalty-cart.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/loyalty-cart.harness.mjs')], base_path(), null, null, 60);
    $process->run();
    $out = json_decode($process->getOutput(), true);

    expect($out)->toBeArray('harness output: '.$process->getOutput().$process->getErrorOutput())
        ->and($out['failures'])->toBe([])->and($process->getExitCode())->toBe(0)
        ->and(file_get_contents(base_path('app/Http/Support/SurfaceRenderer.php')))->toContain('window.OnhostCart.discountLabel(s, cs)');
});
