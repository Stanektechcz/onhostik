<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Lexicon;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Penpot\PenpotDockerProvider;
use Tests\TestCase;

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * TASK-0131 (phase I, I1): the Penpot leftovers after H9–H11. The panel's service list says "Penpot v ceně" on a web hosting
 * whose tariff includes it and names the service on a Penpot row; a paid Penpot line that the delivery refuses because no node
 * can run it is told to the customer, in the organization's language.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
});

/** A running web hosting `$plan` of the organization, billed monthly (no panel behind it). */
function i1Web(Organization $org, string $plan = 'start', string $label = ''): Service
{
    $version = app(CatalogService::class)->resolve('web-hosting', $plan, 'CZK', 'month')['version'];
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_version_id' => $version->id, 'family' => 'web', 'name' => "web-hosting {$plan}", 'label' => $label ?: null, 'state' => ServiceStateMachine::ACTIVE,
        'region_code' => 'cz1', 'entitlements' => (array) $version->entitlements, 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [], 'desired_spec' => [], 'hostname' => Str::lower(Str::random(8)).'.example.cz',
    ]);
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $version->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 9900, 'state' => Subscription::ACTIVE,
        'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth(), 'next_renewal_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service->fresh();
}

/** A Penpot row carried by `$parent` (as the delivery writes it; no node is asked). */
function i1Penpot(Service $parent): Service
{
    return Service::query()->create([
        'organization_id' => $parent->organization_id, 'product_key' => 'penpot', 'family' => 'penpot', 'name' => 'Penpot', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['parent_service_id' => $parent->id], 'desired_spec' => ['parent_service_id' => $parent->id],
    ]);
}

/** @return array<string, array<string,mixed>> service id → its row in the panel payload */
function i1Rows(TestCase $test, User $user, Organization $org): array
{
    $test->actingAs($user);
    $seam = $test->get('/surfaces/onhost-panel.js?organization='.$org->id)->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    $rows = [];
    foreach ((array) $payload['services'] as $group) {
        foreach ((array) $group as $row) {
            if (is_array($row) && isset($row['id'])) {
                $rows[$row['id']] = $row;
            }
        }
    }

    return $rows;
}

/** Places a wallet order of one Penpot line for `$parent`; the order is paid from credit. */
function i1Order(Organization $org, User $owner, Service $parent, CommandContext $ctx): Order
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'penpot', 'plan_key' => 'penpot-team', 'config' => ['parent_service_id' => $parent->id]]], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $doc) {
        $consents[$doc] = ['person' => 'test'];
    }

    return app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'i1-'.Str::random(8), $ctx, 'panel')['order'];
}

it('labels a web hosting whose tariff includes Penpot and names the service on its Penpot row, only while Penpot is on sale', function () {
    penpotLab();
    [$owner, $org] = $this->customerWithOrganization();
    $included = i1Web($org, 'start', 'Firemní web');
    $priced = i1Web($org, 'start', 'Blog');
    app(PenpotOffer::class)->set(['plans' => []]); // every tariff follows web_default: included
    $penpot = i1Penpot($included);

    $rows = i1Rows($this, $owner, $org);
    expect($rows[$included->id]['meta'])->toContain('Penpot v ceně (aktivní)')
        ->and($rows[$included->id]['penpot'])->toBe(['included' => true, 'instance_id' => $penpot->id])
        ->and($rows[$priced->id]['meta'])->toContain('Penpot v ceně')->not->toContain('aktivní')
        ->and($rows[$penpot->id]['meta'])->toContain('ke službě Firemní web · v ceně tarifu')
        ->and($rows[$penpot->id]['penpot'])->toBe(['included' => true, 'parent_service_id' => $included->id]);

    // a tariff staff priced: no "v ceně" claim on the row without a Penpot; the row with one still names it
    app(PenpotOffer::class)->set(['web_default' => ['included' => false, 'price_minor' => ['CZK' => 2900]]]);
    $rows = i1Rows($this, $owner, $org);
    expect($rows[$priced->id]['meta'])->not->toContain('Penpot')->and($rows[$priced->id]['penpot'])->toBeNull()
        ->and($rows[$included->id]['meta'])->toContain(' · Penpot (aktivní)')->not->toContain('v ceně')
        ->and($rows[$penpot->id]['meta'])->toContain('ke službě Firemní web')->not->toContain('v ceně tarifu');

    // Penpot withdrawn from sale: the label promises nothing that cannot be ordered
    app(PenpotOffer::class)->set(['plans' => []]);
    Product::query()->where('key', 'penpot')->update(['state' => 'draft']);
    $rows = i1Rows($this, $owner, $org);
    expect($rows[$priced->id]['meta'])->not->toContain('Penpot')->and($rows[$included->id]['meta'])->toContain('Penpot v ceně (aktivní)');
});

it('says it in English for an English organization and never reads another organization\'s Penpot', function () {
    penpotLab();
    [$owner, $org] = $this->customerWithOrganization(['email' => 'i1-en@studio.uk', 'locale' => 'en'], ['locale' => 'en']);
    $web = i1Web($org, 'start', 'Studio');
    [, $other] = $this->customerWithOrganization(['email' => 'i1-other@example.cz']);
    $foreign = i1Web($other, 'start', 'Cizí web');
    $stray = i1Penpot($foreign);

    $rows = i1Rows($this, $owner, $org);
    expect($rows[$web->id]['meta'])->toContain('Penpot included')->not->toContain('v ceně')
        ->and($rows)->not->toHaveKey($stray->id)->and($rows)->not->toHaveKey($foreign->id);
});

it('tells the customer when a paid Penpot line is refused at delivery because no node can run it', function () {
    penpotLab();
    Queue::fake();
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = CommandContext::system('i1 test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'i1-seed-'.Str::random(6), $ctx);
    app(PenpotOffer::class)->set(['plans' => ['web-hosting/start' => ['included' => false, 'price_minor' => ['CZK' => 2900]]]]);
    $order = i1Order($org, $owner, i1Web($org), $ctx);
    expect((int) $order->total_minor)->toBeGreaterThan(0);

    Node::query()->where('role', 'penpot')->update(['state' => 'maintenance']); // the node is gone between the payment and the delivery
    app(OutboxPublisher::class)->relayPending(1000);
    app(OutboxPublisher::class)->relayPending(1000);

    expect(OrderItem::query()->where('order_id', $order->id)->value('state'))->toBeIn(['failed', 'refunded'])
        ->and(Service::query()->where('family', 'penpot')->exists())->toBeFalse();
    $row = Notification::query()->where('organization_id', $org->id)->where('event', 'order.fulfilment_failed')->where('audience', 'customer')->sole();
    expect($row->title)->toBe('Penpot jsme nemohli zřídit')
        ->and($row->body)->toContain('Server pro Penpot teď není připravený')->toContain('Částku za Penpot vracíme (na kredit, nebo odečtením z faktury)')->toContain((string) $order->number)
        ->and($row->severity)->toBe('warn');
    // the settlement says the amount on its own (order.refunded), and staff keep their internal row
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'order.refunded')->exists())->toBeTrue()
        ->and(Notification::query()->where('event', 'order.fulfilment_failed')->where('audience', 'internal')->exists())->toBeTrue();
});

it('says nothing was charged for an included Penpot, in English for an English organization', function () {
    penpotLab();
    Queue::fake();
    [$owner, $org] = $this->customerWithOrganization(['email' => 'i1-lead@studio.uk', 'locale' => 'en'], ['locale' => 'en']);
    $ctx = CommandContext::system('i1 test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('500', 'CZK'), 'bank', 'i1-seed-'.Str::random(6), $ctx);
    $order = i1Order($org, $owner, i1Web($org), $ctx);
    expect((int) $order->total_minor)->toBe(0);

    Node::query()->where('role', 'penpot')->update(['state' => 'maintenance']);
    app(OutboxPublisher::class)->relayPending(1000);
    app(OutboxPublisher::class)->relayPending(1000);

    $row = Notification::query()->where('organization_id', $org->id)->where('event', 'order.fulfilment_failed')->where('audience', 'customer')->sole();
    expect($row->title)->toBe('We could not set up Penpot')
        ->and($row->body)->toContain('Penpot is included in your plan; nothing was charged.')->toContain('Order no. '.$order->number)
        ->and(Lexicon::untranslated($row->title.' '.$row->body))->toBe([]);
});

it('stays silent for another refusal, another product, or an item of another organization', function () {
    penpotLab();
    [$owner, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization(['email' => 'i1-x@example.cz']);
    $ctx = CommandContext::system('i1 test')->withScope($other->id);
    app(WalletService::class)->topup($other, Money::decimal('500', 'CZK'), 'bank', 'i1-seed-'.Str::random(6), $ctx);
    $foreign = OrderItem::query()->where('order_id', i1Order($other, User::query()->where('email', 'i1-x@example.cz')->sole(), i1Web($other), $ctx)->id)->sole();
    Notification::query()->delete();

    $publish = fn (array $payload, string $organizationId) => app(OutboxPublisher::class)->publish(GenericEvent::of('order.fulfilment_failed', 'order', (string) $foreign->order_id, $payload + ['number' => 'X-1', 'reason' => 'x'], $organizationId));
    $publish(['item_id' => $foreign->id, 'error' => 'penpot_unavailable'], $org->id); // a payload naming somebody else's line
    $publish(['item_id' => $foreign->id, 'error' => 'penpot_exists'], $other->id); // another refusal: the generic refund notice covers it
    $publish(['item_id' => $foreign->id], $other->id); // a provider failure, no slug
    app(OutboxPublisher::class)->relayPending(1000);

    expect(Notification::query()->where('event', 'order.fulfilment_failed')->where('audience', 'customer')->count())->toBe(0);
});
