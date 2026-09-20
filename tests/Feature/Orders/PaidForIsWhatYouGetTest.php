<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * What was paid for is what is delivered (the order-time twin of Brain card H21). A cart line carries a free-form
 * `config`. Its options were PRICED from the product's own option list, clamped to each option's range — and turned
 * into resources from whatever the customer sent, unclamped and whether the product sells that option or not. A web
 * hosting ordered with `mailboxes: 1000` paid for the hundred the price list allows and got a thousand; a Managed
 * WordPress with `ssh: true` (an option it does not sell) paid nothing for it and got it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
    Event::fake(['onhost.order.paid']); // provisioning is not the subject
});

it('delivers the resources of the options that were priced, and nothing the price list does not know', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('500000', 'CZK'), 'bank', 'paid-for-seed', $ctx);
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'];
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $start = Plan::query()->where('key', 'start')->firstOrFail()->currentVersion();
    $wp = Plan::query()->where('key', 'managed-wp')->firstOrFail()->currentVersion();
    $compute = Plan::query()->where('key', 'compute-4')->firstOrFail()->currentVersion();

    $quote = app(QuoteService::class)->quote([
        // far beyond the ranges the price list sells (100 mailboxes, 20 databases), plus keys web hosting does not sell at all
        ['line_id' => 'web', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'levne.cz', 'options' => ['mailboxes' => 1000, 'databases' => 500, 'sites' => 100, 'php_workers' => 64, 'vcpu' => 64],
            'limits' => ['outbound_mail_per_hour' => 1000000, 'inodes' => 99999999], 'entitlements' => ['nvme_gb' => 5000]]],
        // options Managed WordPress does not sell: they cost nothing, so they give nothing
        ['line_id' => 'wp', 'product_key' => 'wordpress', 'plan_key' => 'managed-wp', 'config' => ['fqdn' => 'blog.cz', 'options' => ['ssh' => true, 'staging' => true, 'mailboxes' => 50]]],
        // a slider far beyond its range: charged for the maximum of sixteen, so sixteen is what it gets; a negative one is its minimum
        ['line_id' => 'vps', 'product_key' => 'vps', 'plan_key' => 'compute-4', 'config' => ['options' => ['vcpu' => 1000, 'ram_gb' => -50]]],
    ], 'CZK', $customer, 1, null, $org);
    expect($quote->subtotal_minor)->toBe((8900 + 100 * 500 + 20 * 1900) + 69000 + (44900 + 16 * 9900));

    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'paid-for-1', $ctx)['order'];
    $items = OrderItem::query()->where('order_id', $order->id)->get()->keyBy(fn (OrderItem $i) => (string) $i->config['line_id']);
    expect($items['web']->config['options'])->toBe(['mailboxes' => 100, 'databases' => 20]) // the order itself says what was bought
        ->and($items['wp']->config['options'] ?? [])->toBe([])
        ->and($items['vps']->config['options'])->toBe(['vcpu' => 16, 'ram_gb' => 0]);
    $services = app(ServiceService::class);
    $web = $services->createFromOrderItem($items['web'], $order, $ctx);
    $blog = $services->createFromOrderItem($items['wp'], $order, $ctx);
    $vps = $services->createFromOrderItem($items['vps'], $order, $ctx);

    expect($web->entitlements['mailboxes'])->toBe((int) $start->entitlements['mailboxes'] + 100)->and($web->entitlements['databases'])->toBe((int) $start->entitlements['databases'] + 20)
        ->and($web->entitlements['sites'])->toBe($start->entitlements['sites'])->and($web->entitlements['php_workers'])->toBe($start->entitlements['php_workers'])
        ->and($web->entitlements['nvme_gb'])->toBe($start->entitlements['nvme_gb'])->and($web->entitlements)->not->toHaveKey('vcpu')
        ->and(data_get($web->desired_spec, 'limits'))->toBe($start->limits); // the plan's fair-use limits reach the panel, not the customer's
    expect($blog->entitlements)->toBe($wp->entitlements);
    expect($vps->entitlements['vcpu'])->toBe((int) $compute->entitlements['vcpu'] + 16)->and($vps->entitlements['ram_mb'])->toBe((int) $compute->entitlements['ram_mb']);
});

it('refuses a region that does not exist and ignores a project of another organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [, $otherOrg] = $this->customerWithOrganization();
    $foreignProject = Project::query()->create(['organization_id' => $otherOrg->id, 'name' => 'Cizí projekt', 'slug' => 'cizi', 'tags' => []]);
    $ownProject = Project::query()->create(['organization_id' => $org->id, 'name' => 'Můj projekt', 'slug' => 'muj', 'tags' => []]);
    $customer = ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'];
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $quote = fn (array $config) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => $config]], 'CZK', $customer, 1, null, $org);

    expect(fn () => $quote(['fqdn' => 'kdekoli.cz', 'region' => 'mars-1']))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('region_unknown')->and($e->status)->toBe(422)); // paid and never provisioned otherwise

    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'paid-for-seed-2', $ctx);
    $services = app(ServiceService::class);
    $order = app(CheckoutService::class)->placeOrder($quote(['fqdn' => 'projekt.cz', 'project_id' => $foreignProject->id]), $org, $owner, $consents, ['mode' => 'wallet'], 'paid-for-2', $ctx)['order'];
    expect($services->createFromOrderItem(OrderItem::query()->where('order_id', $order->id)->sole(), $order, $ctx)->project_id)->toBeNull();
    $order = app(CheckoutService::class)->placeOrder($quote(['fqdn' => 'muj-projekt.cz', 'project_id' => $ownProject->id]), $org, $owner, $consents, ['mode' => 'wallet'], 'paid-for-3', $ctx)['order'];
    expect($services->createFromOrderItem(OrderItem::query()->where('order_id', $order->id)->sole(), $order, $ctx)->project_id)->toBe($ownProject->id);
});

/*
 * Who the customer is for tax — and for the regional price list — is a fact of the organization. The quote endpoint
 * let the request say it: a Czech consumer sent `country: DE, customer_class: b2b, vat_status: valid` and was quoted,
 * ordered and invoiced with reverse charge, 0 % VAT.
 */
it('taxes a signed-in organization by what it is, not by what the cart claims', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown']);
    $this->actingAs($owner, 'sanctum');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'dph.cz']]]])->assertOk();

    $honest = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    $claimed = $this->postJson('/v1/cart/quote', ['country' => 'DE', 'customer_class' => 'b2b', 'vat_status' => 'valid'])->assertOk()->json('data');
    expect($honest['tax'])->toBe(Money::minor(8900, 'CZK')->percent(21)->minor)
        ->and($claimed['tax'])->toBe($honest['tax'])->and($claimed['subtotal'])->toBe($honest['subtotal'])->and($claimed['total'])->toBe($honest['total']);

    // a guest gets an estimate for the country they say, but never a verified VAT number
    $this->app['auth']->forgetGuards();
    $this->withHeaders(['X-Cart-Token' => 'guest-cart-token-0123456789'])->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['fqdn' => 'host.de']]]])->assertOk();
    $guest = $this->withHeaders(['X-Cart-Token' => 'guest-cart-token-0123456789'])->postJson('/v1/cart/quote', ['country' => 'DE', 'customer_class' => 'b2b', 'vat_status' => 'valid'])->assertOk()->json('data');
    expect($guest['tax'])->toBeGreaterThan(0);
});

it('turns a quantity into that many lines: each its own price, its own service, its own add-on', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('500000', 'CZK'), 'bank', 'qty-seed', $ctx);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    PromoCode::query()->create(['code' => 'STOVKA', 'kind' => 'fixed', 'value' => 100, 'applies_to' => ['cloud'], 'first_period_only' => true, 'state' => 'active']);

    // the cart of the storefront offers a quantity: it used to be priced × 3 for ONE server, then it was refused — an order for
    // three servers could not be placed at all. The add-on line comes with the quantity of the line it belongs to.
    $quote = app(QuoteService::class)->quote([
        ['line_id' => 'l1', 'product_key' => 'vps', 'plan_key' => 'compute-4', 'qty' => 3, 'config' => ['label' => 'uzel']],
        ['line_id' => 'l2', 'product_key' => 'backup-plus', 'plan_key' => 'backup-7', 'qty' => 3, 'config' => ['parent_line_id' => 'l1']],
    ], 'CZK', [], 1, 'STOVKA', $org);

    expect(array_column($quote->lines, 'line_id'))->toBe(['l1', 'l1#2', 'l1#3', 'l2', 'l2#2', 'l2#3'])
        ->and(array_unique(array_column($quote->lines, 'qty')))->toBe([1])
        ->and(array_map(fn (array $l) => $l['config']['parent_line_id'] ?? null, $quote->lines))->toBe([null, null, null, 'l1', 'l1#2', 'l1#3'])
        ->and(array_map(fn (array $l) => $l['config']['label'] ?? null, array_slice($quote->lines, 0, 3)))->toBe(['uzel', 'uzel 2', 'uzel 3']);
    // three servers and three backups at the list price; "100 Kč off" is spent once, by the first line
    expect($quote->subtotal_minor)->toBe(3 * 44900 + 3 * 4900)->and($quote->discount_minor)->toBe(10000)
        ->and(array_column($quote->lines, 'discount'))->toBe([10000, 0, 0, 0, 0, 0])
        ->and(array_sum(array_column($quote->lines, 'renewal_net')))->toBe(3 * 44900 + 3 * 4900);

    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'qty-1', $ctx)['order'];
    $items = OrderItem::query()->where('order_id', $order->id)->get()->keyBy(fn (OrderItem $i) => (string) $i->config['line_id']);
    expect($items)->toHaveCount(6);
    $services = app(ServiceService::class);
    foreach (['l2', 'l2#2', 'l2#3'] as $addon) { // the add-on builds its parent first, then hangs under it
        $services->createFromOrderItem($items[$addon], $order, $ctx);
    }
    $items = OrderItem::query()->where('order_id', $order->id)->get()->keyBy(fn (OrderItem $i) => (string) $i->config['line_id']);
    $servers = [$items['l1']->service_id, $items['l1#2']->service_id, $items['l1#3']->service_id];
    expect(array_filter($servers))->toHaveCount(3)->and(array_unique($servers))->toHaveCount(3); // three servers, not one
    foreach ([['l2', 'l1'], ['l2#2', 'l1#2'], ['l2#3', 'l1#3']] as [$addon, $parent]) {
        expect(Service::query()->findOrFail($items[$addon]->service_id)->tags['parent_service_id'] ?? null)->toBe($items[$parent]->service_id); // each backup under its own server
    }

    // what cannot be had twice is refused, and so is a quantity nobody means
    $refused = fn (array $items) => fn () => app(QuoteService::class)->quote($items, 'CZK', [], 1, null, $org);
    expect($refused([['product_key' => 'domain', 'qty' => 2, 'config' => ['fqdn' => 'dvakrat.cz']]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('quantity_unsupported')->and($e->status)->toBe(422));
    expect($refused([['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 2, 'config' => ['fqdn' => 'jeden-web.cz']]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('quantity_unsupported'));
    expect($refused([['product_key' => 'vps', 'plan_key' => 'compute-4', 'qty' => 11]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('quantity_too_large'));
    expect($refused([['line_id' => 'a', 'product_key' => 'vps', 'plan_key' => 'compute-4', 'qty' => 2], ['product_key' => 'backup-plus', 'plan_key' => 'backup-7', 'qty' => 1, 'config' => ['parent_line_id' => 'a']]]))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('addon_quantity_mismatch'));
    config(['onhost.orders.max_lines' => 5]);
    expect($refused([['product_key' => 'vps', 'plan_key' => 'compute-4', 'qty' => 6]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('order_too_large'));
});
