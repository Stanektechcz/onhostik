<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PenpotHealth;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Penpot\PenpotDockerProvider;
use Tests\TestCase;

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * Owner decision H-R7 (2026-10-07, TASK-0128): Penpot is on sale — included in every web hosting tariff, an add-on at 29 Kč a
 * month next to any other service. Staff set per web hosting tariff whether it is included or at what price (four eyes). It is
 * ordered for one service, one per service, and never sold while no qualified Penpot node can run it.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

/** A running service of `$product/$plan` of the organization, billed per `$period` (no panel behind it: the quote never asks one). */
function penpotOfferParent(Organization $org, string $product, string $plan, string $period = 'month'): Service
{
    $version = app(CatalogService::class)->resolve($product, $plan, 'CZK', 'month')['version'];
    $family = (string) Product::query()->where('key', $product)->value('family');
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => $product, 'plan_version_id' => $version->id, 'family' => $family, 'name' => "{$product} {$plan}", 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => (array) $version->entitlements, 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [], 'desired_spec' => [], 'hostname' => Str::lower(Str::random(8)).'.example.cz',
    ]);
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $version->id, 'currency' => 'CZK', 'period' => $period, 'amount_minor' => 9900, 'state' => Subscription::ACTIVE,
        'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth(), 'next_renewal_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service->fresh();
}

/** @param array<string,mixed> $config @return array<string,mixed> */
function penpotOfferItem(array $config, array $extra = []): array
{
    return ['product_key' => 'penpot', 'plan_key' => 'penpot-team'] + $extra + ['config' => $config];
}

/** @param list<array<string,mixed>> $items */
function penpotOfferRefusal(Organization $org, array $items, string $currency = 'CZK'): string
{
    try {
        app(QuoteService::class)->quote($items, $currency, [], 1, null, $org);
    } catch (DomainError $e) {
        return $e->error;
    }

    return 'accepted';
}

function penpotOfferStaff(string $role): User
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

    return $user;
}

function penpotOfferSend(TestCase $test, User $as, string $method, string $uri, array $body = []): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->withHeader('Idempotency-Key', 'ppo-'.Str::ulid())->json($method, $uri, $body);
}

it('is on sale after the revision: priced at 29 Kč a month, a year at twelve months, ordered for a service, every web tariff includes it', function () {
    expect(CatalogRevisions::ids())->toContain('2026-10-penpot-on-sale')->and(CatalogRevisions::proposals())->not->toContain('2026-10-penpot')
        ->and(CatalogRevisions::seededProducts())->toContain('penpot');
    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    expect($product->state)->toBe('active')->and($product->isSellable())->toBeTrue()->and(data_get($product->meta, 'listed'))->toBeFalse()
        ->and(data_get($product->meta, 'admin_priced'))->toBeTrue();
    $version = Plan::query()->where('product_id', $product->id)->where('key', 'penpot-team')->firstOrFail()->currentVersion();
    $prices = Price::query()->where('plan_version_id', $version->id)->get()->mapWithKeys(fn (Price $p) => [$p->currency.'/'.$p->period => (int) $p->amount_minor])->sortKeys()->all();
    expect($prices)->toBe(['CZK/month' => 2900, 'CZK/year' => 34800, 'EUR/month' => 119, 'EUR/year' => 1428]);

    $rules = app(PenpotOffer::class)->rules();
    expect(app(PenpotOffer::class)->configured())->toBeTrue()->and($rules['web_default']['included'])->toBeTrue()
        ->and(array_keys($rules['plans']))->toEqualCanonicalizing(array_keys(app(PenpotOffer::class)->webPlans()))
        ->and(collect($rules['plans'])->every(fn (array $entry) => $entry['included']))->toBeTrue()
        ->and(array_keys(app(PenpotOffer::class)->webPlans()))->toContain('web-hosting/start', 'wordpress/managed-woo')
        ->and(app(CatalogRevisions::class)->pending('2026-10-penpot-on-sale'))->toBe([]); // a fresh install has nothing left of it to publish

    // never on the public price list: it is ordered for a service
    expect(collect(app(CatalogService::class)->publicCatalog('cs', 'CZK'))->pluck('key')->all())->not->toContain('penpot');
});

it('publishes on a running catalogue through the bus, and turns the untouched draft of the old proposal into the priced product', function () {
    // a catalogue from before H-R7 that applied the old proposal: Penpot a draft at zero prices, no per-tariff rules
    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    $versionIds = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('product_id', $product->id))->pluck('id');
    Price::query()->whereIn('plan_version_id', $versionIds)->update(['amount_minor' => 0, 'renewal_amount_minor' => 0]);
    $product->forceFill(['state' => 'draft'])->save();
    app(SettingsStore::class)->forget(PenpotOffer::KEY);

    $pending = app(CatalogRevisions::class)->pending('2026-10-penpot-on-sale')['2026-10-penpot-on-sale'];
    expect($pending['priced'])->toBe(['penpot'])->and($pending['offer'])->toBeTrue()->and($pending)->not->toHaveKey('create');
    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-penpot-on-sale'])->assertSuccessful(); // the dry run writes nothing
    expect(Product::query()->where('key', 'penpot')->value('state'))->toBe('draft');

    $this->artisan('onhost:catalog:revise', ['revision' => '2026-10-penpot-on-sale', '--apply' => true, '--yes' => true])->assertSuccessful();
    $product->refresh();
    $plan = Plan::query()->where('product_id', $product->id)->where('key', 'penpot-team')->firstOrFail();
    expect($product->state)->toBe('active')->and((int) $plan->current_version)->toBe(2)
        ->and(Price::query()->where('plan_version_id', $plan->currentVersion()->id)->where('currency', 'CZK')->where('period', 'month')->value('amount_minor'))->toBe(2900)
        ->and(app(PenpotOffer::class)->configured())->toBeTrue()
        ->and(app(CatalogRevisions::class)->pending('2026-10-penpot-on-sale'))->toBe([]);

    // a product staff took off sale since is staff's: a later run never puts it back on sale
    $product->forceFill(['state' => 'draft'])->save();
    expect(app(CatalogRevisions::class)->pending('2026-10-penpot-on-sale'))->toBe([]);
});

it('quotes Penpot as included with a web hosting tariff, at the add-on price next to anything else, a year at twelve months', function () {
    penpotLab();
    Price::query()->whereIn('plan_version_id', PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'penpot-team'))->pluck('id'))
        ->get()->each(fn (Price $p) => $p->forceFill(['amount_minor' => ['CZK' => 2900, 'EUR' => 119][$p->currency] * ($p->period === 'year' ? 12 : 1), 'renewal_amount_minor' => null])->save()); // penpotLab's own test price back to the published one
    [, $org] = $this->customerWithOrganization();

    // in one cart with the web hosting it belongs to
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start', 'line_id' => 'w1'], penpotOfferItem(['parent_line_id' => 'w1'])], 'CZK', [], 1, null, $org);
    $line = collect($quote->lines)->firstWhere('product_key', 'penpot');
    expect($line['net'])->toBe(0)->and($line['renewal_net'])->toBe(0)->and($line['discount'])->toBe(0)->and($line['sku'])->toBe('penpot-penpot-team-included')
        ->and($line['name'])->toContain('(v ceně tarifu)')->and($line['config']['penpot'])->toMatchArray(['included' => true, 'source' => 'tariff', 'parent_product' => 'web-hosting', 'parent_plan' => 'start'])
        ->and($line['config']['parent_line_id'])->toBe('w1')->and($line['config']['executor'])->toBe('penpot');

    // next to a running server: the add-on price, per the server's own billing period
    $vps = penpotOfferParent($org, 'vps', 'compute-4');
    $line = app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $vps->id])], 'CZK', [], 1, null, $org)->lines[0];
    expect($line['net'])->toBe(2900)->and($line['renewal_net'])->toBe(2900)->and($line['period'])->toBe('month')->and($line['config']['penpot']['source'])->toBe('addon')
        ->and($line['config']['parent_service_id'])->toBe($vps->id);
    $yearly = penpotOfferParent($org, 'vps', 'compute-4', 'year');
    expect(app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $yearly->id])], 'CZK', [], 1, null, $org)->lines[0]['net'])->toBe(34800)
        ->and(app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $vps->id])], 'EUR', [], 1, null, $org)->lines[0]['net'])->toBe(119);

    // a promo code or a commitment discount never touches it: the price is the configured price
    $line = app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $vps->id])], 'CZK', [], 12, null, $org)->lines[0];
    expect($line['discount'])->toBe(0);
});

it('prices a web hosting tariff the way staff set it, and refuses a currency staff priced nothing in', function () {
    penpotLab();
    [, $org] = $this->customerWithOrganization();
    app(PenpotOffer::class)->set(['plans' => ['web-hosting/start' => ['included' => false, 'price_minor' => ['CZK' => 1900]], 'web-hosting/standard' => ['price_minor' => ['CZK' => 0, 'EUR' => 0]]], 'web_default' => ['included' => true]]);
    $start = penpotOfferParent($org, 'web-hosting', 'start');
    $line = app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $start->id])], 'CZK', [], 1, null, $org)->lines[0];
    expect($line['net'])->toBe(1900)->and($line['config']['penpot'])->toMatchArray(['included' => false, 'source' => 'tariff']);
    expect(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $start->id])], 'EUR'))->toBe('penpot_unpriced');

    // "0 = included", and a tariff without a rule of its own follows the default
    expect(app(PenpotOffer::class)->rules()['plans']['web-hosting/standard']['included'])->toBeTrue();
    $pro = penpotOfferParent($org, 'web-hosting', 'profi');
    expect(app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $pro->id])], 'CZK', [], 1, null, $org)->lines[0]['config']['penpot'])->toMatchArray(['included' => true, 'source' => 'web_default']);

    // a rule is only for a web hosting tariff, and a tariff that does not include Penpot needs a price
    expect(fn () => app(PenpotOffer::class)->normalize(['plans' => ['vps/compute-4' => ['included' => true]]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_offer_plan_invalid'))
        ->and(fn () => app(PenpotOffer::class)->normalize(['plans' => ['web-hosting/start' => ['included' => false]]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_offer_price_required'))
        ->and(fn () => app(PenpotOffer::class)->normalize(['plans' => ['web-hosting/start' => ['price_minor' => ['USD' => 100]]]]))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_offer_invalid'));
});

it('sells one Penpot per service, only for a running service of the customer, and never without a node to run it', function () {
    [, $org] = $this->customerWithOrganization();
    [, $stranger] = $this->customerWithOrganization();
    $web = penpotOfferParent($org, 'web-hosting', 'start');

    // no Penpot node registered at all: refused before anybody pays
    expect(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $web->id])]))->toBe('penpot_unavailable');
    penpotLab();
    // a node that is not active is no node
    Node::query()->where('role', 'penpot')->update(['state' => 'maintenance']);
    expect(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $web->id])]))->toBe('penpot_unavailable');
    Node::query()->where('role', 'penpot')->update(['state' => 'active']);

    expect(penpotOfferRefusal($org, [penpotOfferItem([])]))->toBe('penpot_parent_required') // never on its own
        ->and(penpotOfferRefusal($org, [['product_key' => 'web-hosting', 'plan_key' => 'start', 'line_id' => 'w1'], penpotOfferItem(['parent_line_id' => 'w1', 'parent_service_id' => $web->id])]))->toBe('penpot_parent_required')
        ->and(penpotOfferRefusal($org, [penpotOfferItem(['parent_line_id' => 'nope'])]))->toBe('addon_parent_missing')
        ->and(penpotOfferRefusal($stranger, [penpotOfferItem(['parent_service_id' => $web->id])]))->toBe('not_found') // somebody else's service
        ->and(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $web->id]), penpotOfferItem(['parent_service_id' => $web->id])]))->toBe('penpot_exists')
        ->and(penpotOfferRefusal($org, [['product_key' => 'web-hosting', 'plan_key' => 'start', 'line_id' => 'w1'], penpotOfferItem(['parent_line_id' => 'w1']), penpotOfferItem(['parent_line_id' => 'w1'])]))->toBe('penpot_exists');

    // an ended service gets none; a service that already has its Penpot gets no second one
    $ending = penpotOfferParent($org, 'web-hosting', 'start');
    $ending->forceFill(['terminate_at' => now()->addDays(30)])->save();
    expect(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $ending->id])]))->toBe('penpot_parent_inactive');
    $ctx = CommandContext::system('pest')->withScope($org->id);
    Queue::fake();
    $penpot = app(ServiceService::class)->create($org, Product::query()->where('key', 'penpot')->firstOrFail(), PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'penpot-team'))->firstOrFail(), ['parent_service_id' => $web->id], $ctx);
    expect($penpot->tags['parent_service_id'])->toBe($web->id)
        ->and(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $web->id])]))->toBe('penpot_exists')
        ->and(penpotOfferRefusal($org, [penpotOfferItem(['parent_service_id' => $penpot->id])]))->toBe('penpot_parent_invalid'); // a Penpot carries no Penpot
});

it('delivers an ordered Penpot under its service, free when included, and refunds the line when the node is gone at delivery', function () {
    penpotLab();
    Queue::fake();
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = CommandContext::system('penpot offer test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'ppo-seed-'.Str::random(6), $ctx);
    $web = penpotOfferParent($org, 'web-hosting', 'start');

    $place = function (array $items, string $key) use ($org, $owner, $ctx) {
        $quote = app(QuoteService::class)->quote($items, 'CZK', [], 1, null, $org);
        $consents = [];
        foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $doc) {
            $consents[$doc] = ['person' => 'test'];
        }

        return app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], $key, $ctx, 'panel')['order'];
    };
    $order = $place([penpotOfferItem(['parent_service_id' => $web->id])], 'ppo-'.Str::random(8));
    expect((int) $order->total_minor)->toBe(0); // included: nothing to pay
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    $penpot = app(ServiceService::class)->createFromOrderItem($item, $order->fresh(), $ctx);
    expect($penpot->family)->toBe('penpot')->and($penpot->tags['parent_service_id'])->toBe($web->id)->and($penpot->organization_id)->toBe($org->id);
    $subscription = app(SubscriptionService::class)->ensureForService($penpot, $item->fresh(), $ctx);
    expect((int) $subscription->amount_minor)->toBe(0); // it renews at the price it was sold at: included

    // a second hosting: the node goes away between the payment and the delivery — the line fails, nothing is built
    $other = penpotOfferParent($org, 'web-hosting', 'start');
    $order = $place([penpotOfferItem(['parent_service_id' => $other->id])], 'ppo-'.Str::random(8));
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    Node::query()->where('role', 'penpot')->update(['state' => 'maintenance']);
    expect(fn () => app(ServiceService::class)->createFromOrderItem($item, $order->fresh(), $ctx))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_unavailable'));
    expect(Service::query()->where('family', 'penpot')->where('tags->parent_service_id', $other->id)->exists())->toBeFalse();

    // and the checkout itself asks again: a quote made while the node was there is not placed once it is gone
    Node::query()->where('role', 'penpot')->update(['state' => 'active']);
    $third = penpotOfferParent($org, 'web-hosting', 'start');
    $quote = app(QuoteService::class)->quote([penpotOfferItem(['parent_service_id' => $third->id])], 'CZK', [], 1, null, $org);
    Node::query()->where('role', 'penpot')->update(['state' => 'maintenance']);
    expect(fn () => app(CheckoutService::class)->placeOrder($quote, $org, $owner, ['terms' => ['person' => 't'], 'privacy' => ['person' => 't'], 'withdrawal_waiver' => ['person' => 't'], 'dpa' => ['person' => 't']], ['mode' => 'wallet'], 'ppo-late', $ctx, 'panel'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_unavailable'));
});

it('ends the Penpot of a service with it, and says so in the cancellation preview', function () {
    [, $org] = $this->customerWithOrganization();
    $web = penpotOfferParent($org, 'web-hosting', 'start');
    $penpot = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'penpot', 'plan_version_id' => PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'penpot-team'))->value('id'), 'family' => 'penpot',
        'name' => 'Penpot Team', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'sla_class' => 'standard', 'hostname' => 'ab12cd34.penpot.onhost.cz',
        'tags' => ['parent_service_id' => $web->id], 'desired_spec' => [], 'activated_at' => now(),
    ]);
    // a row of another organization that names the same parent is not the parent's Penpot
    [, $stranger] = $this->customerWithOrganization();
    $foreign = Service::query()->create([
        'organization_id' => $stranger->id, 'product_key' => 'penpot', 'family' => 'penpot', 'name' => 'Cizí Penpot', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'sla_class' => 'standard',
        'tags' => ['parent_service_id' => $web->id], 'desired_spec' => [], 'activated_at' => now(),
    ]);
    expect(app(DestructivePreview::class)->of($web, 'terminate')['depends'])->toContain('Zruší se i Penpot služby: ab12cd34.penpot.onhost.cz.');

    // the step of the terminate chain, on its own (the chain's panel steps are covered by the web hosting tests)
    $step = (new ReflectionMethod(ServiceActionWorkflow::class, 'endIncludedServicesStep'))->invoke(app(ServiceActionWorkflow::class));
    $operation = (new Operation)->forceFill(['id' => (string) Str::ulid(), 'service_id' => $web->id, 'attempts' => 1, 'desired' => ['action' => 'terminate'], 'context' => []]);
    $result = $step->run(new StepContext($operation, $web, app(ProviderRegistry::class), app(), CommandContext::system('pest')));
    expect($result->context['included_ended'] ?? null)->toBe(['ab12cd34.penpot.onhost.cz'])
        ->and($penpot->fresh()->state)->toBe(ServiceStateMachine::TERMINATED) // never reached a node: nothing to archive or remove
        ->and($foreign->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // a Penpot whose service failed (its order line refunded) is named by the doctor
    $failed = penpotOfferParent($org, 'web-hosting', 'start');
    Service::query()->create(['organization_id' => $org->id, 'product_key' => 'penpot', 'family' => 'penpot', 'name' => 'Penpot Team', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'sla_class' => 'standard', 'hostname' => 'ff00ff00.penpot.onhost.cz', 'tags' => ['parent_service_id' => $failed->id], 'desired_spec' => [], 'activated_at' => now()]);
    $failed->forceFill(['state' => ServiceStateMachine::FAILED])->save();
    $row = collect(app(PenpotHealth::class)->checks())->keyBy('check')['no Penpot outlives the service it was ordered for'];
    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('ff00ff00.penpot.onhost.cz')->not->toContain('ab12cd34');
});

it('lets staff set Penpot per web hosting tariff only with a second person, and refuses a broken rule before anybody is asked', function () {
    $pm = penpotOfferStaff('product_manager');
    $finance = penpotOfferStaff('billing_finance_admin');
    $overview = penpotOfferSend($this, $pm, 'GET', '/v1/staff/pricing/penpot')->assertOk();
    expect(collect($overview->json('data.tariffs') ?? $overview->json('tariffs'))->pluck('target')->all())->toContain('web-hosting/start')
        ->and($overview->json('data.addon.prices') ?? $overview->json('addon.prices'))->toContain(['currency' => 'CZK', 'period' => 'month', 'amount_minor' => 2900]);

    penpotOfferSend($this, $pm, 'PUT', '/v1/staff/pricing/penpot', ['plans' => ['vps/compute-4' => ['included' => true]]])->assertStatus(422)->assertJsonPath('error', 'penpot_offer_plan_invalid');
    expect(Approval::query()->count())->toBe(0);

    $body = ['plans' => ['web-hosting/start' => ['included' => false, 'price_minor' => ['CZK' => 1900, 'EUR' => 79]]], 'web_default' => ['included' => true], 'reason' => 'Start bez Penpotu v ceně'];
    $asked = (string) penpotOfferSend($this, $pm, 'PUT', '/v1/staff/pricing/penpot', $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(app(PenpotOffer::class)->rules()['plans']['web-hosting/start']['included'])->toBeTrue();
    penpotOfferSend($this, $finance, 'POST', "/v1/staff/approvals/{$asked}/decision", ['decision' => 'approved'])->assertOk();
    penpotOfferSend($this, $pm, 'PUT', '/v1/staff/pricing/penpot', $body)->assertOk();
    expect(app(PenpotOffer::class)->rules()['plans'])->toBe(['web-hosting/start' => ['included' => false, 'price_minor' => ['CZK' => 1900, 'EUR' => 79]]])
        ->and(Approval::query()->findOrFail($asked)->state)->toBe('consumed');

    // the editor in the settings page drives the same route, behind the same step-up dialog
    $this->actingAs($this->staff('sre'));
    expect($this->get('/sprava/nastaveni/integrace')->assertOk()->getContent())->toContain('Penpot k tarifům webhostingu')->toContain("'/staff/pricing/penpot'")->toContain('guarded(function () { return api(\'PUT\', \'/staff/pricing/penpot\'');

    // a customer reads nothing here
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum')->getJson('/v1/staff/pricing/penpot')->assertForbidden();
});

it('tells the customer what a Penpot costs next to a service and whether it can be ordered now', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $web = penpotOfferParent($org, 'web-hosting', 'start');
    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$web->id}/penpot-offer")->assertOk()
        ->assertJsonPath('data.orderable', false)->assertJsonPath('data.reason', 'penpot_unavailable')->assertJsonPath('data.included', true);
    penpotLab();
    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$web->id}/penpot-offer")->assertOk()
        ->assertJsonPath('data.orderable', true)->assertJsonPath('data.included', true)->assertJsonPath('data.price.minor', 0);
    $vps = penpotOfferParent($org, 'vps', 'compute-4');
    Price::query()->whereIn('plan_version_id', PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'penpot-team'))->pluck('id'))->where('currency', 'CZK')->where('period', 'month')->update(['amount_minor' => 2900, 'renewal_amount_minor' => 2900]);
    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$vps->id}/penpot-offer")->assertOk()->assertJsonPath('data.included', false)->assertJsonPath('data.price.minor', 2900);

    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/services/{$web->id}/penpot-offer")->assertNotFound();
});

it('names a Penpot on sale without a node in the doctor as a finding, not a deploy blocker', function () {
    $rows = collect(app(PenpotHealth::class)->checks())->keyBy('check');
    expect($rows['Penpot is sold only with a Penpot node to run it']['ok'])->toBeFalse()->and($rows['Penpot is sold only with a Penpot node to run it']['blocking'])->toBeFalse()
        ->and($rows['Penpot is sold only with a Penpot node to run it']['detail'])->toContain('penpot_unavailable')
        ->and($rows['Penpot has a rule for every web hosting tariff']['ok'])->toBeTrue()
        ->and($rows['Penpot has a price before it is on sale']['ok'])->toBeTrue();
    penpotLab();
    expect(collect(app(PenpotHealth::class)->checks())->keyBy('check')['Penpot is sold only with a Penpot node to run it']['ok'])->toBeTrue();
});
