<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Contracts\IpGeoProvider;
use Onhost\Providers\IpGeo\HttpIpGeoProvider;
use Onhost\Providers\IpGeo\NullIpGeoProvider;

/*
 * Risk signals with data (audit §5g-4): the country of the order's address is one more signal when a lookup is
 * configured (never when not), and staff decisions teach the check — a release lightens the signals that held the
 * order, a reject makes them heavier, within bounds.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function feedbackQuote($org)
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
}

it('learns from staff decisions: a release lowers the weights that held the order, a reject raises them, within bounds', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $risk = app(OrderRiskService::class);
    expect($risk->weights())->toBe(OrderRiskService::WEIGHTS);

    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $held = app(CheckoutService::class)->placeOrder(feedbackQuote($org), $org, $owner, $consents, ['mode' => 'wallet'], 'fb-1', $ctx)['order']->refresh();
    expect($held->meta['risk']['reasons'])->toBe(['new_account', 'disposable_email'])->and($held->meta['review']['state'])->toBe('pending');

    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $this->withHeader('Idempotency-Key', 'fb-rv-1')->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'release', 'reason' => 'known customer'])->assertOk();
    $this->flushHeaders();
    expect($risk->weights())->toMatchArray(['new_account' => 20, 'disposable_email' => 45, 'rapid_orders' => 30])->and($risk->feedback()['disposable_email'])->toBe(['released' => 1, 'rejected' => 0]);
    expect(app(SettingsStore::class)->get(OrderRiskService::WEIGHTS_SETTING)['disposable_email'])->toBe(45);
    $rules = collect($this->getJson('/v1/staff/automation')->assertOk()->json('data'))->keyBy('key');
    expect($rules->get('order.risk')['now']['weights'])->toContain('disposable_email=45')->and($rules->get('order.risk')['now']['geo'])->toBeFalse();

    // a reject moves them back up; the bounds hold however many decisions come
    $second = app(CheckoutService::class)->placeOrder(feedbackQuote($org), $org, $owner, $consents, ['mode' => 'wallet'], 'fb-2', $ctx)['order']->refresh();
    expect($second->meta['risk']['score'])->toBe(45)->and($second->meta)->not->toHaveKey('review'); // 45 < 60 after the release, with a paid history
    app(SettingsStore::class)->set(OrderRiskService::WEIGHTS_SETTING, ['disposable_email' => 99, 'new_account' => 6]);
    $risk->learn($held, 'reject', 'test');
    expect($risk->weights())->toMatchArray(['disposable_email' => 100, 'new_account' => 11]);
    $risk->learn($held, 'release', 'test');
    $risk->learn($held, 'release', 'test');
    expect($risk->weights())->toMatchArray(['disposable_email' => 90, 'new_account' => 5]);
    expect($risk->learn($held, 'maybe'))->toBe($risk->weights()); // an unknown decision teaches nothing
});

it('reviews the model: held orders with their signals next to the outcome, per-signal precision, CSV export', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $held = app(CheckoutService::class)->placeOrder(feedbackQuote($org), $org, $owner, $consents, ['mode' => 'wallet'], 'rr-1', $ctx)['order']->refresh();
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $pending = $this->getJson('/v1/staff/orders/risk-review?days=30')->assertOk()->json('data');
    expect($pending['held'])->toBe(1)->and($pending['orders'][0])->toMatchArray(['order' => $held->number, 'organization' => 'Test s.r.o.', 'score' => 75, 'outcome' => 'pending'])->and($pending['signals']['disposable_email'])->toMatchArray(['held' => 1, 'pending' => 1, 'precision' => null]);
    $this->withHeader('Idempotency-Key', 'rr-rv-1')->postJson("/v1/staff/orders/{$held->id}/review", ['decision' => 'reject', 'reason' => 'stolen card'])->assertOk();
    $this->flushHeaders();
    $decided = $this->getJson('/v1/staff/orders/risk-review')->assertOk()->json('data');
    expect($decided['signals']['disposable_email'])->toMatchArray(['held' => 1, 'rejected' => 1, 'precision' => 1.0])->and($decided['orders'][0]['outcome'])->toBe('rejected')->and($decided['orders'][0]['decision_reason'])->toBe('stolen card');
    $csv = $this->get('/v1/staff/orders/risk-review?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8')->getContent();
    expect($csv)->toContain('order;organization;score;reasons;outcome')->toContain($held->number)->toContain('new_account|disposable_email')->toContain('rejected');
    $this->actingAs($owner, 'sanctum')->getJson('/v1/staff/orders/risk-review')->assertForbidden();
});

it('lets staff tune the weights and the hold threshold from the console and reset them', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'x@mailinator.com'], ['type' => 'company', 'billing_email' => 'x@mailinator.com']);
    $ctx = $this->contextFor($owner, $org);
    $quote = feedbackQuote($org);
    $risk = app(OrderRiskService::class);
    expect($risk->assess($quote, $org, $owner, $ctx))->toMatchArray(['score' => 75, 'hold' => true]);

    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');
    $tuned = $this->putJson('/v1/staff/automation/order.risk/tuning', ['weights' => ['disposable_email' => 30], 'hold_score' => 80, 'reason' => 'too many false holds'])->assertOk()->json();
    expect($tuned['weights']['disposable_email'])->toBe(30)->and($tuned['weights']['new_account'])->toBe(25)->and($tuned['hold_score'])->toBe(80)->and($tuned['defaults']['disposable_email'])->toBe(50);
    expect($risk->assess($quote, $org, $owner, $ctx))->toMatchArray(['score' => 55, 'hold' => false]);
    $rules = collect($this->getJson('/v1/staff/automation')->assertOk()->json('data'))->keyBy('key');
    expect($rules->get('order.risk')['now'])->toMatchArray(['hold_score' => 80])->and($rules->get('order.risk')['now']['weights'])->toContain('disposable_email=30');

    $this->putJson('/v1/staff/automation/order.risk/tuning', ['weights' => ['nope' => 30]])->assertStatus(422)->assertJsonPath('error', 'risk_signal_unknown');
    $this->putJson('/v1/staff/automation/order.risk/tuning', ['weights' => ['disposable_email' => 500]])->assertStatus(422);
    $reset = $this->putJson('/v1/staff/automation/order.risk/tuning', ['reset' => true])->assertOk()->json();
    expect($reset['weights'])->toBe(OrderRiskService::WEIGHTS)->and($reset['hold_score'])->toBe(60)->and($reset['feedback'])->toBe([]);
    $this->actingAs($owner, 'sanctum')->putJson('/v1/staff/automation/order.risk/tuning', ['hold_score' => 90])->assertForbidden();
});

it('adds the country of the address as a signal when a lookup is configured and never blocks on it', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['type' => 'company', 'billing_email' => 'jan@firma.cz']);
    $quote = feedbackQuote($org);
    $from = fn (string $ip) => new CommandContext('user', $owner->id, $org->id, null, $ip, 'pest', 'test-session');

    // no lookup configured: no signal, whatever the address
    expect(app(IpGeoProvider::class))->toBeInstanceOf(NullIpGeoProvider::class);
    expect(app(OrderRiskService::class)->assess($quote, $org, $owner, $from('198.51.100.7'))['reasons'])->toBe(['new_account']);

    // a lookup over a JSON endpoint: a foreign country adds the signal, the answer is cached, private addresses are never sent, failures mean no signal
    $calls = 0;
    Http::fake(function (Request $request) use (&$calls) {
        if (! str_starts_with($request->url(), 'https://geo.example.test/')) {
            return null;
        }
        $calls++;

        return match (true) {
            str_contains($request->url(), '198.51.100.7') => Http::response(['ip' => '198.51.100.7', 'country' => 'US', 'name' => 'United States']),
            str_contains($request->url(), '203.0.113.9') => Http::response(['country_code' => 'cz']),
            default => Http::response('upstream down', 503),
        };
    });
    $geo = new HttpIpGeoProvider(app('cache.store'), 'https://geo.example.test/country/{ip}.json');
    $this->app->instance(IpGeoProvider::class, $geo);
    expect($geo->country('10.0.0.5'))->toBeNull()->and($geo->country('not-an-ip'))->toBeNull()->and($calls)->toBe(0);
    expect($geo->country('198.51.100.7'))->toBe('US')->and($geo->country('198.51.100.7'))->toBe('US')->and($calls)->toBe(1);
    expect($geo->country('203.0.113.9'))->toBe('CZ')->and($geo->country('192.0.2.44'))->toBeNull()->and($calls)->toBe(3);

    $risk = app(OrderRiskService::class);
    $abroad = $risk->assess($quote, $org, $owner, $from('198.51.100.7'));
    expect($abroad['reasons'])->toBe(['new_account', 'ip_country_mismatch'])->and($abroad['score'])->toBe(45);
    expect($risk->assess($quote, $org, $owner, $from('203.0.113.9'))['reasons'])->toBe(['new_account'])->and($risk->assess($quote, $org, $owner, $from('192.0.2.44'))['reasons'])->toBe(['new_account']);
    expect($risk->assess($quote, $org, $owner, CommandContext::system('test'))['reasons'])->toBe(['new_account']); // no address, no signal
});
