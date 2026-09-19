<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * The catalogue decides what can be bought together (Brain card H01). Two promises: a combination the catalogue does
 * not offer is refused while it is still a cart — before an order, a document or a movement of money exists — and
 * what was agreed stays agreed: an order and the service it paid for keep the plan version, the limits and the price
 * they were sold with after staff publish a new version of the plan.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** Everything a purchase would leave behind. @return array<string,int> */
function commerceFootprint(string $organizationId): array
{
    return [
        'quotes' => Quote::query()->count(), 'orders' => Order::query()->count(), 'invoices' => Invoice::query()->count(),
        'ledger' => DB::table('ledger_transactions')->count(), 'postings' => DB::table('ledger_postings')->count(), 'holds' => DB::table('wallet_holds')->count(),
        'order_events' => OutboxMessage::query()->where('name', 'like', 'order.%')->count(),
    ];
}

it('refuses a combination the catalogue does not offer while it is still a cart: no order, no document, no money moved', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('20000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $balance = app(WalletService::class)->spendable($org, 'CZK')->minor;
    $before = commerceFootprint($org->id);
    $quote = fn (array $items, int $commit = 1) => app(QuoteService::class)->quote($items, 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], $commit, null, $org);

    $off = Product::query()->where('key', 'vps')->firstOrFail();
    $webAddons = app(PricingRules::class)->addonProducts('web-hosting'); // the refusals are built from what the catalogue really holds
    $foreign = Product::query()->whereNotIn('key', array_merge($webAddons, ['web-hosting', 'domain']))->where('state', 'active')->get()->first(fn (Product $p) => $p->plans()->where('state', 'active')->exists());
    expect($foreign)->not->toBeNull();
    $foreignPlan = $foreign->plans()->where('state', 'active')->firstOrFail()->key;

    $refusals = [
        'addon_not_applicable' => fn () => $quote([['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => $foreign->key, 'plan_key' => $foreignPlan, 'config' => ['parent_line_id' => 'web']]]),
        'addon_parent_missing' => fn () => $quote([['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => $webAddons[0], 'plan_key' => Product::query()->where('key', $webAddons[0])->firstOrFail()->plans()->firstOrFail()->key, 'config' => ['parent_line_id' => 'gone']]]),
        'not_found' => fn () => $quote([['product_key' => 'web-hosting', 'plan_key' => 'compute-4']]), // a plan of another product
        'domain_period_invalid' => fn () => $quote([['product_key' => 'domain', 'config' => ['fqdn' => 'kratka.cz', 'period_years' => 0]]]),
        'invalid_commitment' => fn () => $quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 7),
        'product_not_sellable' => function () use ($quote, $off) {
            $off->forceFill(['state' => 'draft'])->save();
            try {
                return $quote([['product_key' => 'vps', 'plan_key' => 'compute-4']]);
            } finally {
                $off->forceFill(['state' => 'active'])->save();
            }
        },
        'price_unavailable' => function () use ($quote) {
            $version = Plan::query()->where('key', 'compute-2')->firstOrFail()->currentVersion();
            Price::query()->where('plan_version_id', $version->id)->where('period', 'year')->update(['state' => 'retired']);

            return $quote([['product_key' => 'vps', 'plan_key' => 'compute-2', 'period' => 'year']]);
        },
    ];
    foreach ($refusals as $error => $attempt) {
        $caught = null;
        try {
            $attempt();
        } catch (DomainError $e) {
            $caught = $e;
        }
        expect($caught?->error)->toBe($error, "expected {$error}")->and($caught->status)->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    }

    // the same through the API: the cart is refused as a whole, one bad line is enough
    $this->actingAs($owner, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start'], ['product_key' => $foreign->key, 'plan_key' => $foreignPlan, 'config' => ['parent_line_id' => 'web']]]]);
    $this->postJson('/v1/cart/quote')->assertStatus(422)->assertJsonPath('error', 'addon_not_applicable');
    // … and there is no way to an order without a quote
    $this->withHeader('Idempotency-Key', 'h01-no-quote')->postJson('/v1/orders', ['quote_id' => 'qt_does_not_exist', 'consents' => ['terms' => []], 'payment' => ['mode' => 'wallet']])->assertStatus(404);

    expect(commerceFootprint($org->id))->toBe($before)
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($balance);
});

it('keeps what was agreed: a new version of the plan changes neither the order, nor the service, nor the renewal price', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('20000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'];
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];

    $plan = Plan::query()->where('key', 'compute-4')->firstOrFail();
    $v1 = $plan->currentVersion();
    $quote = app(QuoteService::class)->quote([['product_key' => 'vps', 'plan_key' => 'compute-4']], 'CZK', $customer, 1, null, $org);
    expect($quote->lines[0]['plan_version_id'])->toBe($v1->id)->and($quote->lines[0]['entitlements']['ram_mb'])->toBe(8192);

    // staff publish version 2 while the quote is open: less memory for more money
    $v2 = PlanVersion::query()->create(['plan_id' => $plan->id, 'version' => 2, 'entitlements' => array_replace((array) $v1->entitlements, ['ram_mb' => 6144]), 'limits' => $v1->limits, 'features' => $v1->features, 'effective_from' => now()->subMinute()]);
    foreach (['month' => 59900, 'year' => 599000] as $period => $amount) {
        Price::query()->create(['plan_version_id' => $v2->id, 'currency' => 'CZK', 'period' => $period, 'amount_minor' => $amount, 'renewal_amount_minor' => $amount, 'setup_minor' => 0, 'effective_from' => now()->subMinute(), 'state' => 'active']);
    }
    $plan->forceFill(['current_version' => 2])->save();

    // the open quote is an offer: the order placed from it carries version 1, its limits and its price
    Event::fake(['onhost.order.paid']); // provisioning is not the subject here
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'h01-agreed', $ctx)['order']->refresh();
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    expect($order->state)->toBe(OrderStateMachine::PAID)->and($order->subtotal_minor)->toBe(44900)
        ->and($item->plan_version_id)->toBe($v1->id)->and($item->config['entitlements']['ram_mb'])->toBe(8192)->and($item->config['renewal_net_minor'])->toBe(44900);

    // a new customer gets version 2 …
    $fresh = app(QuoteService::class)->quote([['product_key' => 'vps', 'plan_key' => 'compute-4']], 'CZK', $customer, 1, null, $org);
    expect($fresh->lines[0]['plan_version_id'])->toBe($v2->id)->and($fresh->subtotal_minor)->toBe(59900)->and($fresh->lines[0]['entitlements']['ram_mb'])->toBe(6144);

    // … the service of the old order is built and renewed on what was agreed
    $service = app(ServiceService::class)->createFromOrderItem($item, $order, $ctx);
    $subscription = app(SubscriptionService::class)->ensureForService($service, $item, $ctx);
    expect($service->plan_version_id)->toBe($v1->id)->and($service->entitlements['ram_mb'])->toBe(8192)
        ->and($subscription->plan_version_id)->toBe($v1->id)->and($subscription->amount_minor)->toBe(44900);
    expect($order->fresh()->subtotal_minor)->toBe(44900)->and(OrderItem::query()->findOrFail($item->id)->plan_version_id)->toBe($v1->id);
});
