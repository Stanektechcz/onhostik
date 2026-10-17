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
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
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
 *
 * W6-3: three more scenarios - the anonymous cart read (all queries and INSERTs apart), the pricing data script cold and warm,
 * and the panel script of two tenants (counts and isolation). ASSUMED / BLOCKED still holds for every QB_* below: the W6-3
 * sandbox could not install vendor/ either (2026-10-16, PHP 8.3.6, base 0c7691d; composer dist downloads from github.com
 * refused), so nothing was measured. QB_REPORT=1 writes build/query-budget.json (scenario => count) so CI reports the counts;
 * set QB_* = ceil(max of three runs x 1.1) from that report and replace this paragraph with the counts, date, commit and PHP.
 */
const QB_PRICING_DATA_CEILING = 400;
const QB_CART_QUOTE_CEILING = 250;
const QB_CART_QUOTE_FUNNEL_CEILING = 250;
const QB_GUEST_CHECKOUT_CEILING = 600;
const QB_GUEST_CART_READ_CEILING = 50;
const QB_GUEST_CART_READ_INSERTS = 1; // W7-0 lowers this to 0: GET /v1/cart without a known token still creates an open cart
const QB_SURFACE_DATA_COLD_CEILING = 1750; // first CI measurement 1711 (SQLite, 2026-10-16); the 400 was a guess made without vendor
const QB_PANEL_CEILING = 400;
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

/**
 * $count cart lines of web hosting, cycling through the seeded plans.
 *
 * @return list<array{line_id: string, product_key: string, plan_key: string}>
 */
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
    app('auth')->forgetGuards();
    $test->flushSession();
    $id = Str::lower(Str::random(10));
    RateLimiter::clear(md5('auth'.'ip:127.0.0.1'));
    RateLimiter::clear(md5('auth'.'email:qb-'.$id.'@example.cz'));
    $payload = [
        'customer' => ['email' => 'qb-'.$id.'@example.cz', 'name' => 'Měření Rozpočtu', 'country' => 'CZ', 'street' => 'Dlouhá 12', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => qbItems($count), 'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ];

    return qbCountQueries(fn () => $test->withHeaders(['Referer' => 'http://localhost', 'Idempotency-Key' => 'qb-'.$id])->postJson('/v1/checkout/guest', $payload)->assertCreated());
}

/** Records $count under $scenario in build/query-budget.json when QB_REPORT=1 (CI report step); a no-op otherwise. */
function qbReport(string $scenario, int $count): void
{
    if (getenv('QB_REPORT') !== '1') {
        return;
    }
    $path = base_path('build/query-budget.json');
    $report = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    $report = is_array($report) ? $report : [];
    $report[$scenario] = $count;
    ksort($report);
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

/**
 * All SQL statements and the INSERTs among them that $callback runs, without emptying any cache.
 *
 * @return array{queries: int, inserts: int}
 */
function qbCountStatements(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $callback();
        $log = DB::getQueryLog();
        $inserts = array_filter($log, fn (array $entry): bool => str_starts_with(strtolower(ltrim((string) $entry['query'])), 'insert'));

        return ['queries' => count($log), 'inserts' => count($inserts)];
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

/**
 * Queries and INSERTs of one anonymous GET /v1/cart, with no token or with a well-formed token nobody holds.
 *
 * @return array{queries: int, inserts: int}
 */
function qbGuestCartRead(TestCase $test, ?string $token): array
{
    app('auth')->forgetGuards();
    $headers = $token === null ? [] : ['X-Cart-Token' => $token];
    Cache::flush();

    return qbCountStatements(fn () => $test->withHeaders($headers)->getJson('/v1/cart')->assertOk());
}

/**
 * Queries of the pricing data script right after Cache::flush (cold) and of the next request (warm).
 *
 * @return array{cold: int, warm: int}
 */
function qbSurfaceData(TestCase $test): array
{
    Cache::flush();
    $cold = qbCountStatements(fn () => $test->get('/surfaces/onhost-data.js')->assertOk());
    $warm = qbCountStatements(fn () => $test->get('/surfaces/onhost-data.js')->assertOk());

    return ['cold' => $cold['queries'], 'warm' => $warm['queries']];
}

/**
 * Queries and body of the panel script for $user.
 *
 * @return array{queries: int, body: string}
 */
function qbPanelTwoTenants(TestCase $test, User $user): array
{
    app('auth')->forgetGuards();
    $test->actingAs($user, 'sanctum');
    $body = '';
    Cache::flush();
    $counts = qbCountStatements(function () use ($test, &$body): void {
        $body = (string) $test->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    });

    return ['queries' => $counts['queries'], 'body' => $body];
}

it('builds the pricing data script with as many queries for 2N plans as for N, under the ceiling, whatever another organization holds', function () {
    qbAddPlans(QB_N);
    qbMeasurePricingData($this); // warm-up: one-time boot costs are not part of the budget
    $n = qbMeasurePricingData($this);
    qbAddPlans(QB_N);
    $twoN = qbMeasurePricingData($this);

    expect($twoN)->toBe($n, 'the pricing data script must not run a query per plan');
    qbReport('pricing_data.2n', $twoN);
    qbUnderCeiling($twoN, QB_PRICING_DATA_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasurePricingData($this))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the pricing data script');
});

it('quotes a cart of 2N lines with as many queries as N lines, under the ceiling, whatever another organization holds', function () {
    qbMeasureCartQuote($this, QB_N); // warm-up
    $n = qbMeasureCartQuote($this, QB_N);
    $twoN = qbMeasureCartQuote($this, 2 * QB_N);

    expect($twoN)->toBe($n, 'the cart quote must not run a query per line');
    qbReport('cart_quote.2n', $twoN);
    qbUnderCeiling($twoN, QB_CART_QUOTE_CEILING);

    // funnel_metrics on adds a constant (the Plan::whereIn lookup and the outbox insert), never a query per line
    qbMeasureCartQuote($this, QB_N, true); // warm-up
    $metricN = qbMeasureCartQuote($this, QB_N, true);
    $metricTwoN = qbMeasureCartQuote($this, 2 * QB_N, true);
    expect($metricTwoN)->toBe($metricN, 'the funnel metric must not run a query per line')
        ->and($metricN)->toBeGreaterThan($n, 'funnel_metrics=true must add its constant');
    qbReport('cart_quote_funnel.2n', $metricTwoN);
    qbUnderCeiling($metricTwoN, QB_CART_QUOTE_FUNNEL_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasureCartQuote($this, 2 * QB_N))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the cart quote')
        ->and(qbMeasureCartQuote($this, 2 * QB_N, true))->toBe($metricTwoN, 'nor the cart quote with the funnel metric');
});

/** INSERT statements of one anonymous GET /v1/cart (W7-0: a read never creates a cart). */
function qbGuestCartReadInserts(TestCase $test, ?string $token): int
{
    $inserts = 0;
    qbCountQueries(function () use ($test, $token, &$inserts) {
        $test->withHeaders($token === null ? [] : ['X-Cart-Token' => $token])->getJson('/v1/cart')->assertOk();
        $inserts = count(array_filter(DB::getQueryLog(), fn (array $query) => str_starts_with(strtolower(ltrim((string) $query['query'])), 'insert')));
    });

    return $inserts;
}

it('reads a guest cart without a single INSERT, with or without a token (W7-0)', function () {
    expect(qbGuestCartReadInserts($this, null))->toBe(0)
        ->and(qbGuestCartReadInserts($this, Str::random(40)))->toBe(0)
        ->and(Cart::query()->count())->toBe(0);
});

it('places a guest order of 2N lines with at most one extra query per extra line (the order item insert), under the ceiling, whatever another organization holds', function () {
    qbMeasureGuestCheckout($this, QB_N); // warm-up
    $n = qbMeasureGuestCheckout($this, QB_N);
    $twoN = qbMeasureGuestCheckout($this, 2 * QB_N);

    // CheckoutService inserts one order_items row per line, so N extra lines may cost N extra queries, never more.
    expect($twoN - $n)->toBeLessThanOrEqual(QB_N, 'the guest checkout may only add the order item insert per line');
    qbReport('guest_checkout.2n', $twoN);
    qbUnderCeiling($twoN, QB_GUEST_CHECKOUT_CEILING);

    qbNoisyNeighbour(2 * QB_N);
    expect(qbMeasureGuestCheckout($this, 2 * QB_N))->toBe($twoN, 'another organization\'s plans, carts and orders must not change the guest checkout');
});

it('reads an anonymous cart with a bounded number of queries and at most one INSERT', function () {
    qbGuestCartRead($this, null); // warm-up
    $none = qbGuestCartRead($this, null);
    $unknown = qbGuestCartRead($this, 'qbUnknownToken'.Str::random(26));
    qbReport('guest_cart_read.no_token.queries', $none['queries']);
    qbReport('guest_cart_read.no_token.inserts', $none['inserts']);
    qbReport('guest_cart_read.unknown_token.queries', $unknown['queries']);
    qbReport('guest_cart_read.unknown_token.inserts', $unknown['inserts']);

    // W7-0 lowers this to 0: a read must not create a cart; today firstOrCreate inserts one open cart per unknown token
    expect($none['inserts'])->toBeLessThanOrEqual(QB_GUEST_CART_READ_INSERTS)
        ->and($unknown['inserts'])->toBeLessThanOrEqual(QB_GUEST_CART_READ_INSERTS);
    qbUnderCeiling($none['queries'], QB_GUEST_CART_READ_CEILING);
    qbUnderCeiling($unknown['queries'], QB_GUEST_CART_READ_CEILING);
});

it('serves a warm pricing data script without a single query', function () {
    qbSurfaceData($this); // warm-up: one-time boot costs are not part of the budget
    $data = qbSurfaceData($this);
    qbReport('surface_data.cold', $data['cold']);
    qbReport('surface_data.warm', $data['warm']);

    expect($data['warm'])->toBe(0, 'the warm pricing data script must come from the cache');
    qbUnderCeiling($data['cold'], QB_SURFACE_DATA_COLD_CEILING);
});

it('builds the panel script of two tenants with bounded queries, each without the other tenant\'s services or organization', function () {
    [$userA, $orgA] = $this->customerWithOrganization([], ['name' => 'QbTenantA s.r.o.']);
    [$userB, $orgB] = $this->customerWithOrganization([], ['name' => 'QbTenantB s.r.o.']);
    $nameA = 'qbsvc-alpha-'.Str::lower(Str::random(8));
    $nameB = 'qbsvc-bravo-'.Str::lower(Str::random(8));
    qbWebService($orgA)->forceFill(['name' => $nameA, 'hostname' => $nameA.'.cz'])->save();
    qbWebService($orgB)->forceFill(['name' => $nameB, 'hostname' => $nameB.'.cz'])->save();
    qbWebService($orgB)->forceFill(['name' => $nameB.'-2', 'hostname' => $nameB.'-2.cz'])->save();
    expect(Service::query()->where('organization_id', $orgB->id)->count())->toBe(2);

    qbPanelTwoTenants($this, $userA); // warm-up
    $a = qbPanelTwoTenants($this, $userA);
    $b = qbPanelTwoTenants($this, $userB);
    qbReport('panel.tenant_a', $a['queries']);
    qbReport('panel.tenant_b', $b['queries']);

    expect($a['body'])->toContain($nameA)->not->toContain($nameB)->not->toContain((string) $orgB->id)
        ->and($b['body'])->toContain($nameB)->not->toContain($nameA)->not->toContain((string) $orgA->id);
    qbUnderCeiling($a['queries'], QB_PANEL_CEILING);
    qbUnderCeiling($b['queries'], QB_PANEL_CEILING);
});

/** featureWebService always binds remote_id '7'; give each binding a unique remote id so several services can coexist. */
function qbWebService(Organization $org): Service
{
    $service = featureWebService($org, 'ispconfig');
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => 'qb-'.$service->id]);

    return $service;
}
