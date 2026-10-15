<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Cart;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * W4-5: query budgets of the three public hot paths — the pricing data script (#/ceny), the cart quote and the guest checkout.
 * Each path must not grow with the size of what it lists (N against 2N), must stay under an absolute ceiling, and must not
 * notice another organization's data. Only query counts are measured; times are not (SQLite is not representative).
 * The doctor is covered by tests/Feature/Platform/DoctorPerformanceTest.php (H5).
 *
 * W5-2 (variant B): the core assertions are the scaling ones (N against 2N, a second organization with 10x data); they hold on
 * every driver. The absolute QB_* ceilings are a ratchet checked only under SQLite (pgsql issues other bookkeeping queries).
 * ASSUMED / BLOCKED: the ceilings below are still the W4-5 estimates - vendor/ could not be installed in the W5-2 sandbox
 * (composer: "Could not authenticate against github.com"), so no count was measured. Replace them with the counts measured by
 * `php artisan test --compact tests/Feature/Performance/QueryBudgetTest.php` (commit 8817e21, funnel_metrics off and on);
 * after that they may only go down.
 */
const QB_PRICING_DATA_CEILING = 400;
const QB_CART_QUOTE_CEILING = 250;
const QB_CART_QUOTE_FUNNEL_CEILING = 250;
const QB_GUEST_CHECKOUT_CEILING = 600;
const QB_N = 3;

beforeEach(function () {
    Http::preventStrayRequests(); // the guest checkout pays by bank transfer: no gateway is asked, so nothing may reach the network
    Http::fake(['*' => Http::response(['error' => 'qb: no gateway expected'], 599)]); // a payment call fails the checkout, it never reaches a provider
    Notification::fake();
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

/** Number of SQL queries $callback runs, with every cache emptied first. */
function qbCountQueries(callable $callback): int
{
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $callback();

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

/** Adds $count active, priced plans to a product (the pricing page's product unless named otherwise). */
function qbAddPlans(int $count, string $productKey = 'web-hosting', string $prefix = 'qb'): void
{
    $product = Product::query()->where('key', $productKey)->firstOrFail();
    $start = Plan::query()->where('product_id', $product->id)->count();
    for ($i = 1; $i <= $count; $i++) {
        $key = $prefix.'-'.($start + $i);
        $plan = Plan::query()->create([
            'product_id' => $product->id, 'key' => $key, 'name' => ['cs' => 'Plán '.$key, 'en' => 'Plan '.$key], 'description' => ['cs' => 'Měření', 'en' => 'Measurement'],
            'sla_class' => 'standard', 'highlighted' => false, 'state' => 'active', 'sort' => 1000 + $start + $i, 'current_version' => 1,
        ]);
        $version = PlanVersion::query()->create(['plan_id' => $plan->id, 'version' => 1, 'entitlements' => ['sites' => 1, 'nvme_gb' => 10], 'limits' => [], 'features' => [], 'effective_from' => now()->subDay()]);
        foreach (['CZK' => 9900, 'EUR' => 400] as $currency => $amount) {
            Price::query()->create(['plan_version_id' => $version->id, 'currency' => $currency, 'period' => 'month', 'amount_minor' => $amount, 'renewal_amount_minor' => $amount, 'setup_minor' => 0, 'effective_from' => now()->subDay(), 'state' => 'active']);
        }
    }
}

/** $count cart lines of web hosting, cycling through the seeded plans. */
function qbItems(int $count): array
{
    $plans = ['start', 'standard', 'profi'];
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $items[] = ['line_id' => 'qb'.$i, 'product_key' => 'web-hosting', 'plan_key' => $plans[$i % 3]];
    }

    return $items;
}

/** Another organization with ten times the plans, carts and orders the measured request works with. */
function qbNoisyNeighbour(int $n): void
{
    $user = User::factory()->create();
    $organization = app(OrganizationService::class)->create($user, ['name' => 'Soused s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK'], CommandContext::system('test'));
    $hidden = Product::query()->create(['key' => 'qb-neighbour', 'family' => 'web', 'name' => ['cs' => 'Soused', 'en' => 'Neighbour'], 'description' => ['cs' => '', 'en' => ''], 'executor' => null, 'billing_model' => 'subscription', 'state' => 'draft', 'sort' => 999, 'meta' => []]);
    qbAddPlans(10 * $n, $hidden->key, 'qbn');
    for ($i = 0; $i < 10 * $n; $i++) {
        Cart::query()->create(['organization_id' => $organization->id, 'user_id' => $i === 0 ? $user->id : null, 'session_token' => Str::random(40), 'currency' => 'CZK', 'commit_months' => 1, 'items' => qbItems(2), 'state' => 'open', 'expires_at' => now()->addDays(30)]);
        Order::query()->create([
            'number' => sprintf('QB-NEIGHBOUR-%04d', $i), 'organization_id' => $organization->id, 'user_id' => $user->id, 'state' => 'NEW', 'currency' => 'CZK',
            'subtotal_minor' => 10000, 'tax_minor' => 2100, 'total_minor' => 12100, 'payment_mode' => 'bank', 'idempotency_key' => 'qb-neighbour-'.$i,
        ]);
    }
}

/** Queries of one pricing data script request. */
function qbMeasurePricingData(TestCase $test): int
{
    return qbCountQueries(fn () => $test->get('/surfaces/onhost-data.js')->assertOk());
}

/** Absolute ceilings are a SQLite ratchet only; under pgsql just the scaling assertions apply. */
function qbUnderCeiling(int $count, int $ceiling): void
{
    if (DB::getDriverName() !== 'sqlite') {
        return; // pgsql: other bookkeeping queries, the absolute count is not comparable - only scaling is asserted
    }
    expect($count)->toBeLessThanOrEqual($ceiling, 'ratchet: lower only, raise only with a reason in the commit');
}

/** Queries of one guest cart quote with $count lines (the cart is filled before the measurement). */
function qbMeasureCartQuote(TestCase $test, int $count, bool $funnelMetrics = false): int
{
    config(['onhost.funnel_metrics' => $funnelMetrics]);
    $token = Str::random(40);
    $test->withHeaders(['X-Cart-Token' => $token])->putJson('/v1/cart', ['items' => qbItems($count)])->assertOk();

    return qbCountQueries(fn () => $test->withHeaders(['X-Cart-Token' => $token])->postJson('/v1/cart/quote')->assertOk());
}

/** Queries of one guest checkout with $count lines, by a visitor nobody knows yet. */
function qbMeasureGuestCheckout(TestCase $test, int $count): int
{
    $test->app['auth']->forgetGuards();
    $test->flushSession();
    $id = Str::lower(Str::random(10));
    RateLimiter::clear(md5('auth'.'ip:127.0.0.1'));
    RateLimiter::clear(md5('auth'.'email:qb-'.$id.'@example.cz'));
    $payload = [
        'customer' => ['email' => 'qb-'.$id.'@example.cz', 'name' => 'Měření Rozpočtu', 'country' => 'CZ', 'street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => qbItems($count), 'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ];

    return qbCountQueries(fn () => $test->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'qb-'.$id])->postJson('/v1/checkout/guest', $payload)->assertCreated());
}

it('builds the pricing data script with as many queries for 2N plans as for N, under the ceiling, whatever another organization holds', function () {
    qbAddPlans(QB_N);
    qbMeasurePricingData($this); // warm-up: one-time boot costs are not part of the budget
    $n = qbMeasurePricingData($this);
    qbAddPlans(QB_N);
    $twoN = qbMeasurePricingData($this);

    expect($twoN)->toBe($n, 'the pricing data script must not run a query per plan');
    qbUnderCeiling($twoN, QB_PRICING_DATA_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasurePricingData($this))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the pricing data script');
});

it('quotes a cart of 2N lines with as many queries as N lines, under the ceiling, whatever another organization holds', function () {
    qbMeasureCartQuote($this, QB_N); // warm-up
    $n = qbMeasureCartQuote($this, QB_N);
    $twoN = qbMeasureCartQuote($this, 2 * QB_N);

    expect($twoN)->toBe($n, 'the cart quote must not run a query per line');
    qbUnderCeiling($twoN, QB_CART_QUOTE_CEILING);

    // funnel_metrics on adds a constant (the Plan::whereIn lookup and the outbox insert), never a query per line
    qbMeasureCartQuote($this, QB_N, true); // warm-up
    $metricN = qbMeasureCartQuote($this, QB_N, true);
    $metricTwoN = qbMeasureCartQuote($this, 2 * QB_N, true);
    expect($metricTwoN)->toBe($metricN, 'the funnel metric must not run a query per line')
        ->and($metricN)->toBeGreaterThan($n, 'funnel_metrics=true must add its constant');
    qbUnderCeiling($metricTwoN, QB_CART_QUOTE_FUNNEL_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasureCartQuote($this, 2 * QB_N))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the cart quote')
        ->and(qbMeasureCartQuote($this, 2 * QB_N, true))->toBe($metricTwoN, 'nor the cart quote with the funnel metric');
});

it('places a guest order of 2N lines with at most one extra query per extra line (the order item insert), under the ceiling, whatever another organization holds', function () {
    qbMeasureGuestCheckout($this, QB_N); // warm-up
    $n = qbMeasureGuestCheckout($this, QB_N);
    $twoN = qbMeasureGuestCheckout($this, 2 * QB_N);

    // CheckoutService inserts one order_items row per line, so N extra lines may cost N extra queries, never more.
    expect($twoN - $n)->toBeLessThanOrEqual(QB_N, 'the guest checkout may only add the order item insert per line');
    qbUnderCeiling($twoN, QB_GUEST_CHECKOUT_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasureGuestCheckout($this, 2 * QB_N))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the guest checkout');
});
