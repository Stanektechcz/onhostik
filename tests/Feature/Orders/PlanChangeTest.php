<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Commerce: a running service moves to another plan of its product through the ordinary order pipeline — the quote
 * prices the pro-rated difference for the rest of the period, the order is paid from credit (or by transfer), the
 * fulfilment resizes the node through the `resize` action, the subscription renews at the new price, and the customer
 * hears about it. A downgrade costs nothing now; the same plan, a stranger's service and a guest are refused.
 */

function planChangeConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => []];
}

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('changes the plan of a running service: pro-rated charge now, new price from the next period, node resized, subscription and notification', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $service = featureWebService($org, 'aapanel');
    $catalog = app(CatalogService::class);
    $start = $catalog->resolve('web-hosting', 'start', 'CZK', 'month');
    $standard = $catalog->resolve('web-hosting', 'standard', 'CZK', 'month');
    $oldNet = $start['price']->renewalAmount()->minor;
    $newNet = $standard['price']->renewalAmount()->minor;
    $service->forceFill(['plan_version_id' => $start['version']->id, 'entitlements' => $start['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $start['version']->id, 'price_id' => $start['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $oldNet,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();
    $this->actingAs($owner, 'sanctum');

    // what the service may move to, priced per its month, with the cost of changing halfway through the period
    $options = $this->getJson("/v1/services/{$service->id}/plans")->assertOk()->json('data');
    $up = collect($options['plans'])->firstWhere('plan_key', 'standard');
    $expectedNow = (int) round(($newNet - $oldNet) * 0.5);
    expect($options['current_plan'])->toBe('start')->and($options['changeable'])->toBeTrue()->and($options['fraction'])->toBeGreaterThan(0.45)->toBeLessThan(0.55)
        ->and(collect($options['plans'])->firstWhere('plan_key', 'start')['current'])->toBeTrue()
        ->and($up['direction'])->toBe('upgrade')->and($up['price']['minor'])->toBe($newNet)->and($up['entitlements']['php_workers'])->toBe($standard['version']->entitlements['php_workers'])
        ->and($up['change_now']['minor'])->toBeGreaterThanOrEqual($expectedNow - 5)->toBeLessThanOrEqual($expectedNow + 5);

    // the panel's flow: cart → quote → order from credit
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect($quote['lines'])->toHaveCount(1)->and($quote['lines'][0]['name'])->toStartWith('Změna tarifu:')->and($quote['lines'][0]['net'])->toBe($up['change_now']['minor'])
        ->and($quote['lines'][0]['config']['plan_change'])->toMatchArray(['from_plan' => 'start', 'to_plan' => 'standard', 'old_net_minor' => $oldNet, 'new_net_minor' => $newNet, 'period' => 'month'])
        ->and($quote['lines'][0]['renewal_net'])->toBe($newNet);
    $placed = $this->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => planChangeConsents(), 'payment' => ['mode' => 'wallet'], 'source' => 'panel'])->assertCreated()->json();
    $order = Order::query()->findOrFail($placed['order_id']);
    expect($order->state)->toBeIn([OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE]); // the bus relays the outbox at once, so fulfilment may already have started
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $service->refresh();
    $subscription->refresh();
    $order->refresh();
    expect($order->state)->toBe(OrderStateMachine::ACTIVE)->and(OrderItem::query()->where('order_id', $order->id)->value('state'))->toBe('active')
        ->and($service->state)->toBe(ServiceStateMachine::ACTIVE)->and($service->plan_version_id)->toBe($standard['version']->id)
        ->and($service->entitlements['php_workers'])->toBe($standard['version']->entitlements['php_workers'])->and($service->entitlements['nvme_gb'])->toBe($standard['version']->entitlements['nvme_gb'])
        ->and($subscription->plan_version_id)->toBe($standard['version']->id)->and($subscription->amount_minor)->toBe($newNet)->and($subscription->current_period_end->toDateString())->toBe(now()->addDays(15)->toDateString());
    // aaPanel has one PHP-FPM pool per PHP version for the WHOLE NODE: a customer's plan change must never resize it (it used to — a
    // downgrade to a two-worker plan set `pm.max_children = 2` for every site of every customer on that PHP version)
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'SetPHPMaxChildren'));
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Tarif služby % změněn na Standard')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-plan-changed')->exists())->toBeTrue();
    $available = (int) $this->getJson('/v1/wallet')->assertOk()->json('data.balances.available.minor');
    expect($available)->toBeLessThan(500000)->toBeGreaterThanOrEqual(500000 - (int) round($up['change_now']['minor'] * 1.21) - 2); // the gross difference left the credit

    // a downgrade costs nothing now and takes effect at once
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $down = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    expect($down['lines'][0]['net'])->toBe(0)->and($down['total'])->toBe(0)->and($down['lines'][0]['config']['plan_change']['from_plan'])->toBe('standard');
    $downOrder = Order::query()->findOrFail($this->postJson('/v1/orders', ['quote_id' => $down['quote_id'], 'consents' => planChangeConsents(), 'payment' => ['mode' => 'bank'], 'source' => 'panel'])->assertCreated()->json('order_id'));
    expect($downOrder->state)->toBeIn([OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE]); // nothing to pay: settled at once whatever the method
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect(Service::query()->findOrFail($service->id)->plan_version_id)->toBe($start['version']->id)->and($subscription->refresh()->amount_minor)->toBe($oldNet)->and($downOrder->refresh()->state)->toBe(OrderStateMachine::ACTIVE);

    // refused: the plan the service already runs, and a service of another organization
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->postJson('/v1/cart/quote')->assertStatus(422)->assertJsonPath('error', 'plan_change_same_plan');
    [, $other] = $this->customerWithOrganization();
    $foreign = Service::query()->create(['organization_id' => $other->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'hostname' => 'other.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()]);
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $foreign->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->postJson('/v1/cart/quote')->assertNotFound();
    $this->getJson("/v1/services/{$foreign->id}/plans")->assertForbidden();
});

it('switches the billing period of a running service to yearly: a new period starts now, the unused rest of the month is credited, no node work', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $service = featureWebService($org, 'aapanel');
    $catalog = app(CatalogService::class);
    $monthly = $catalog->resolve('web-hosting', 'start', 'CZK', 'month');
    $yearly = $catalog->resolve('web-hosting', 'start', 'CZK', 'year');
    $oldNet = $monthly['price']->renewalAmount()->minor;   // 8 900
    $yearNet = $yearly['price']->renewalAmount()->minor;   // 89 000
    $service->forceFill(['plan_version_id' => $monthly['version']->id, 'entitlements' => $monthly['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $monthly['version']->id, 'price_id' => $monthly['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $oldNet,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();
    $this->actingAs($owner, 'sanctum');

    // the options name both periods of the current plan: yearly costs the yearly price minus the unused half of the month, and saves two months a year
    $options = $this->getJson("/v1/services/{$service->id}/plans")->assertOk()->json('data');
    $year = collect($options['periods'])->firstWhere('period', 'year');
    $month = collect($options['periods'])->firstWhere('period', 'month');
    $unused = (int) round($oldNet * 0.5);
    expect($month['current'])->toBeTrue()->and($month['change_now']['minor'])->toBe(0)
        ->and($year['current'])->toBeFalse()->and($year['price']['minor'])->toBe($yearNet)->and($year['saving_per_year']['minor'])->toBe($oldNet * 12 - $yearNet)
        ->and($year['change_now']['minor'])->toBeGreaterThanOrEqual($yearNet - $unused - 5)->toBeLessThanOrEqual($yearNet - $unused + 5)
        ->and(substr((string) $year['period_end_after'], 0, 10))->toBe(now()->addYear()->toDateString());

    // the same plan, the other period: a period-change line
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'period' => 'year', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $quote = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
    $line = $quote['lines'][0];
    expect($quote['lines'])->toHaveCount(1)->and($line['name'])->toStartWith('Změna období:')->toEndWith('(ročně)')->and($line['period'])->toBe('year')
        ->and($line['net'])->toBe($year['change_now']['minor'])->and($line['renewal_net'])->toBe($yearNet)
        ->and($line['config']['plan_change'])->toMatchArray(['from_plan' => 'start', 'to_plan' => 'start', 'period' => 'year', 'from_period' => 'month', 'period_change' => true, 'old_net_minor' => $oldNet, 'new_net_minor' => $yearNet])
        ->and($line['config']['plan_change']['unused_credit_minor'])->toBeGreaterThanOrEqual($unused - 5)->toBeLessThanOrEqual($unused + 5);
    $placed = $this->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => planChangeConsents(), 'payment' => ['mode' => 'wallet'], 'source' => 'panel'])->assertCreated()->json();
    app(OutboxPublisher::class)->relayPending();
    driveOperations();

    $subscription->refresh();
    $service->refresh();
    expect(Order::query()->findOrFail($placed['order_id'])->state)->toBe(OrderStateMachine::ACTIVE)
        ->and($subscription->period)->toBe('year')->and($subscription->amount_minor)->toBe($yearNet)->and($subscription->plan_version_id)->toBe($monthly['version']->id)
        ->and($subscription->current_period_start->toDateString())->toBe(now()->toDateString())->and($subscription->current_period_end->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($subscription->next_renewal_at->toDateString())->toBe(now()->addYear()->subDays((int) config('onhost.billing.renew_lead_days', 7))->toDateString())
        ->and($service->plan_version_id)->toBe($monthly['version']->id)->and($service->state)->toBe(ServiceStateMachine::ACTIVE);
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'SetPHPMaxChildren')); // the same plan: nothing to resize on the node
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Období platby služby % změněno na roční')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-period-changed')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-plan-changed')->exists())->toBeFalse();

    // now yearly: the same plan and period is refused, a plan change keeps the yearly period, and monthly is offered back at the monthly price minus the unused year
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'period' => 'year', 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $this->postJson('/v1/cart/quote')->assertStatus(422)->assertJsonPath('error', 'plan_change_same_plan');
    $this->putJson('/v1/cart', ['items' => [['product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['upgrade_of' => $service->id]]], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
    $up = $this->postJson('/v1/cart/quote')->assertOk()->json('data.lines.0');
    expect($up['period'])->toBe('year')->and($up['config']['plan_change']['period_change'])->toBeFalse()->and($up['name'])->toStartWith('Změna tarifu:');
    $back = collect($this->getJson("/v1/services/{$service->id}/plans")->assertOk()->json('data.periods'))->firstWhere('period', 'month');
    expect($back['current'])->toBeFalse()->and($back['change_now']['minor'])->toBe(0); // a whole unused year outweighs one month: nothing to pay now
});
