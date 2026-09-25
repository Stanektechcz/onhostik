<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Commands\StaffCustomerCommand;
use Onhost\Domain\Orders\Listeners\FulfillPaidOrder;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Provisioning\Commands\ProvisioningCommand;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\Limits\LimitRaisePolicy;
use Onhost\Domain\Services\Limits\LimitRaises;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\PlanChangeService;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SuspensionHold;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Owner decision 8 (2026-09-25, TASK-0022 limit-raise): a limit of ONE service can be raised, and a raise is paid for —
 * the parent product's own option price per unit and per period, renewed every period like the service. Before it the only
 * ways to give a service more were unbilled: a staff `resize` with any numbers, or a staff `service.create` with its own
 * entitlements. Only a number the platform actually enforces for the family can be raised (MetricRegistry); a raise at no
 * charge is a four-eyes decision bound to the service, the key and the price it waives.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** An active subscription for a service (what an order would have created). */
function limitRaiseSubscribe(Service $service, string $period = 'month', int $amount = 8900): Subscription
{
    $subscription = Subscription::query()->create([
        'organization_id' => $service->organization_id, 'service_id' => $service->id, 'plan_version_id' => $service->plan_version_id, 'price_id' => null, 'currency' => 'CZK', 'period' => $period, 'amount_minor' => $amount,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(3), 'current_period_end' => $period === 'year' ? now()->addYear()->subDays(3) : now()->addDays(27), 'next_renewal_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $subscription;
}

/** The shared web fixture, with a panel id of its own (the fixture always binds the same one, and a test here needs several). */
function limitRaiseWeb(Organization $org, string $executor = 'aapanel'): Service
{
    foreach (ProviderBinding::query()->whereIn('remote_type', ['site', 'web_domain'])->whereIn('remote_id', ['41', '7'])->get() as $earlier) {
        $earlier->forceFill(['remote_id' => (string) random_int(1000, 999999)])->save();
    }

    return featureWebService($org, $executor);
}

/** A running web hosting on the web-hosting plan `$plan`, bought per `$period`. */
function limitRaiseParent(Organization $org, string $executor = 'aapanel', string $plan = 'start', string $period = 'month'): Service
{
    $version = app(CatalogService::class)->resolve('web-hosting', $plan, 'CZK', 'month')['version'];
    $service = limitRaiseWeb($org, $executor);
    $service->forceFill(['plan_version_id' => $version->id, 'entitlements' => (array) $version->entitlements])->save();
    limitRaiseSubscribe($service, $period);

    return $service->fresh();
}

/** @param array<string,mixed> $raise */
function limitRaiseItem(array $raise, array $extra = []): array
{
    return ['product_key' => 'limit-raise'] + $extra + ['config' => ['limit_raise' => $raise]];
}

/** @return array<string,mixed> the single quote line of a raise */
function limitRaiseQuoteLine(Organization $org, array $raise, array $extraConfig = [], int $commitMonths = 1, ?string $promo = null): array
{
    $item = limitRaiseItem($raise);
    $item['config'] = array_merge($item['config'], $extraConfig);

    return app(QuoteService::class)->quote([$item], 'CZK', [], $commitMonths, $promo, $org)->lines[0];
}

function limitRaiseRefusal(callable $call): string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->error;
    }

    return 'accepted';
}

/**
 * Orders and delivers a raise the way staff place one (an assisted order paid from credit), without the outbox.
 *
 * @return array{0: Service, 1: Order}
 */
function limitRaiseDeliver(Organization $org, Service $parent, string $metric, int $units): array
{
    $context = CommandContext::system('limit raise test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'lr-seed-'.Str::random(6), $context);
    $quote = app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => $metric, 'units' => $units])], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-'.Str::random(10), $context, 'staff')['order'];
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    $addon = app(ServiceService::class)->createFromOrderItem($item, $order, $context);

    return [$addon->fresh(), $order->fresh()];
}

function limitRaiseStaff(string $role, bool $stepUp = true): User
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    if ($stepUp) {
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    }

    return $user;
}

function limitRaiseSend(TestCase $test, User $as, string $method, string $uri, array $body = []): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->withHeader('Idempotency-Key', 'lr-'.Str::ulid())->json($method, $uri, $body);
}

it('prices a raise at the parent product\'s option price per unit and period, with no commitment, promo, loyalty or region discount', function () {
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['settings' => ['loyalty_discount' => ['pct' => 10]]])->save();
    PromoCode::query()->create(['code' => 'VSE50', 'kind' => 'percent', 'value' => 50, 'state' => 'active', 'first_period_only' => false]);
    $parent = limitRaiseParent($org);
    $unit = (int) ProductOption::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->where('key', 'mailboxes')->sole()->price_per_unit_minor['CZK'];
    expect($unit)->toBe(500);

    $line = limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5], [], 12, 'VSE50');
    expect($line['product_key'])->toBe('limit-raise')->and($line['sku'])->toBe('limit-raise-mailboxes')
        ->and($line['unit_net'])->toBe(2500)->and($line['discount'])->toBe(0)->and($line['net'])->toBe(2500)->and($line['renewal_net'])->toBe(2500)
        ->and($line['period'])->toBe('month') // the parent's own period, not the cart's commitment
        ->and($line['plan_version_id'])->toBeNull()->and($line['price_id'])->toBeNull()
        ->and($line['entitlements'])->toBe(['limit_raise' => ['metric' => 'mailboxes', 'delta' => 5]])
        ->and($line['config']['parent_service_id'])->toBe($parent->id)
        ->and($line['config']['limit_raise'])->toMatchArray(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5, 'delta' => 5, 'option_key' => 'mailboxes', 'unit_price_minor' => 500, 'months' => 1]);

    // a parent paid per year: twelve months of the option price, renewed per year
    $yearly = limitRaiseParent($org, 'aapanel', 'start', 'year');
    $line = limitRaiseQuoteLine($org, ['service_id' => $yearly->id, 'metric' => 'mailboxes', 'units' => 5]);
    expect($line['period'])->toBe('year')->and($line['net'])->toBe(2500 * 12)->and($line['renewal_net'])->toBe(2500 * 12);
});

it('raises only a number the platform enforces for the family, and only where it is priced', function () {
    [, $org] = $this->customerWithOrganization();
    $web = limitRaiseParent($org);
    $raise = fn (Service $s, string $metric, int $units = 1) => limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $s->id, 'metric' => $metric, 'units' => $units]));

    // sold but not enforced (MetricRegistry GAP) — for the family it is asked for
    $wordpress = limitRaiseWeb($org);
    $wordpress->forceFill(['product_key' => 'wordpress', 'family' => 'managed'])->save();
    limitRaiseSubscribe($wordpress);
    $mail = featureMailService($org, 'posta.cz');
    limitRaiseSubscribe($mail);
    $vps = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => ['vcpu' => 2, 'snapshots' => 3], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()]);
    limitRaiseSubscribe($vps);
    $game = featureGameService($org);
    limitRaiseSubscribe($game);
    expect($raise($wordpress, 'php_workers'))->toBe('limit_raise_not_enforced')
        ->and($raise($mail, 'aliases'))->toBe('limit_raise_not_enforced')
        ->and($raise($vps, 'snapshots'))->toBe('limit_raise_not_enforced')
        ->and($raise($web, 'made_up_key'))->toBe('limit_raise_not_enforced')
        ->and($raise($web, 'cron_concurrency'))->toBe('limit_raise_not_enforced') // organization-wide, not one service's
        // v1: no cloud (a resize has no node-capacity check), and nothing that takes a node's dedicated share
        ->and($raise($vps, 'vcpu'))->toBe('limit_raise_family')
        ->and($raise($game, 'ram_mb'))->toBe('limit_raise_capacity')
        // enforced, but the product sells no priced option for it
        ->and($raise($mail, 'mailboxes'))->toBe('limit_raise_unpriced')
        ->and($raise($web, 'sites'))->toBe('limit_raise_unpriced');
    // a game server's databases are enforced by the panel and priced by the configurator
    expect($raise($game, 'databases'))->toBe('accepted');

    // an option priced at zero is not a price
    ProductOption::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->where('key', 'mailboxes')->update(['price_per_unit_minor' => json_encode(['CZK' => 0, 'EUR' => 0])]);
    expect($raise($web, 'mailboxes'))->toBe('limit_raise_unpriced');
});

it('refuses a stranger\'s service, a service that is not running or not billed, a quantity, and too many units', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $foreign = limitRaiseParent($other);
    $raise = fn (Service $s, array $raise = []) => limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, $raise + ['service_id' => $s->id, 'metric' => 'mailboxes', 'units' => 5]));

    expect($raise($foreign))->toBe('not_found');
    expect(limitRaiseRefusal(fn () => app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])], 'CZK', ['country' => 'CZ'])))->toBe('limit_raise_requires_account');
    expect($raise($parent, ['units' => 0]))->toBe('limit_raise_units')
        ->and($raise($parent, ['units' => 101]))->toBe('limit_raise_units') // onhost.limit_raise.max_units
        ->and(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'nvme_gb', 'units' => 15])))->toBe('limit_raise_step'); // the option sells whole steps of 10 GB
    config()->set('onhost.limit_raise.max_units', 500);
    expect($raise($parent, ['units' => 101]))->toBe('limit_raise_above_max'); // the option sells at most 100 mailboxes above the plan
    expect(limitRaiseRefusal(fn () => app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 60]), limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 60])], 'CZK', [], 1, null, $org)))
        ->toBe('limit_raise_above_max'); // two lines of one cart count together
    expect(limitRaiseRefusal(fn () => app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5], ['qty' => 2])], 'CZK', [], 1, null, $org)))->toBe('quantity_unsupported');

    Subscription::query()->where('service_id', $parent->id)->update(['state' => Subscription::CANCELLED]);
    expect($raise($parent))->toBe('limit_raise_no_subscription');
    $parent->forceFill(['state' => ServiceStateMachine::SUSPENDED])->save();
    expect($raise($parent))->toBe('limit_raise_state');
});

it('takes nothing from the cart but the service, the number and the units: no price, parent, entitlements or waiver', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $elsewhere = limitRaiseParent($org);
    $line = limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5, 'delta' => 500, 'unit_price_minor' => 1, 'waived' => ['approval_ids' => ['apr_x']], 'price' => 0], [
        'parent_service_id' => $elsewhere->id, 'parent_line_id' => 'l9', 'entitlements' => ['mailboxes' => 999], 'limits' => ['cpu_pct' => 800], 'price' => 0, 'unit_net' => 0,
    ]);
    expect($line['net'])->toBe(2500)->and($line['discount'])->toBe(0)
        ->and($line['config']['parent_service_id'])->toBe($parent->id)
        ->and($line['config'])->not->toHaveKeys(['parent_line_id', 'entitlements', 'limits', 'price', 'unit_net'])
        ->and($line['config']['limit_raise'])->not->toHaveKeys(['waived', 'price'])
        ->and($line['config']['limit_raise']['delta'])->toBe(5)->and($line['config']['limit_raise']['unit_price_minor'])->toBe(500)
        ->and($line['entitlements'])->toBe(['limit_raise' => ['metric' => 'mailboxes', 'delta' => 5]]);
});

it('keeps customer checkout of a raise behind a switch that is off, while the staff assisted order works either way', function () {
    Event::fake(['onhost.order.paid']); // delivery is another test
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'lr-cust-seed', $this->contextFor($owner, $org), bankProvider: 'comgate');
    $parent = limitRaiseParent($org);
    $item = limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5]);
    $order = function () use ($owner, $org, $item): TestResponse {
        $this->actingAs($owner, 'sanctum');
        $this->putJson('/v1/cart', ['items' => [$item], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk();
        $quote = $this->postJson('/v1/cart/quote')->assertOk()->json('data');
        $consents = [];
        foreach (app(CheckoutService::class)->requiredDocuments(Quote::query()->findOrFail($quote['quote_id']), $org) as $key) {
            $consents[$key] = ['version' => '2026-09', 'person' => 'Jan Novák'];
        }

        return $this->withHeader('Idempotency-Key', 'lr-co-'.Str::ulid())->postJson('/v1/orders', ['quote_id' => $quote['quote_id'], 'consents' => $consents, 'payment' => ['mode' => 'wallet'], 'source' => 'panel']);
    };

    expect(config('onhost.limit_raise.customer_orders'))->toBeFalse();
    $order()->assertForbidden()->assertJsonPath('error', 'limit_raise_staff_only');
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(0);

    config()->set('onhost.limit_raise.customer_orders', true);
    $order()->assertCreated();
    config()->set('onhost.limit_raise.customer_orders', false);

    $sales = limitRaiseStaff('sales', false);
    limitRaiseSend($this, $sales, 'POST', "/v1/staff/customers/{$org->id}/orders", ['items' => [$item], 'payment' => 'bank', 'note' => 'Telefonát zákazníka, tiket #42'])->assertCreated();
    expect(Order::query()->where('organization_id', $org->id)->where('source', 'staff')->count())->toBe(1);
});

it('delivers a raise: the parent gets the units, the panel gets the new client limit, the raise gets its own subscription', function () {
    Event::fake(['onhost.order.paid']);
    $calls = [];
    Http::fake([ISP.'/remote/json.php*' => function (Request $request) use (&$calls) {
        $method = (string) parse_url($request->url(), PHP_URL_QUERY); // …/remote/json.php?client_get
        $calls[] = ['method' => $method, 'body' => $request->data()];

        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ($method) {
            'login' => 'sess-1',
            'client_get' => ['client_id' => 3, 'username' => 'onh_client3', 'limit_mailbox' => 10, 'limit_web_domain' => 1, 'limit_database' => 1, 'limit_web_quota' => 10240],
            'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'system_user' => 'web7', 'sys_groupid' => 3, 'hd_quota' => 10240, 'active' => 'y', 'document_root' => '/var/www/clients/client3/web7'],
            'monitor_jobqueue_count' => 0,
            default => true,
        }]);
    }]);
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org, 'ispconfig');
    $before = (int) $parent->entitlements['mailboxes'];

    [$addon, $order] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    expect($addon->family)->toBe('addon')->and($addon->product_key)->toBe('limit-raise')->and($addon->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and(data_get($addon->tags, 'parent_service_id'))->toBe($parent->id)
        ->and(data_get($addon->tags, 'addon.delta'))->toBe(['mailboxes' => 5])
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($before + 5);

    // the panel is asked to apply it, with every number of the service (a partial resize would clear the others)
    $resize = Operation::query()->where('idempotency_key', "limit-raise:{$item->id}")->sole();
    expect($resize->service_id)->toBe($parent->id)->and(data_get($resize->desired, 'action'))->toBe('resize')
        ->and(data_get($resize->desired, 'entitlements.mailboxes'))->toBe($before + 5)
        ->and(data_get($resize->desired, 'entitlements.nvme_gb'))->toBe((int) $parent->entitlements['nvme_gb']);
    driveOperation($resize);
    $update = collect($calls)->firstWhere('method', 'client_update');
    expect($update)->not->toBeNull()->and((int) data_get($update, 'body.params.limit_mailbox'))->toBe($before + 5);

    // and it is billed every period, not once
    $subscription = Subscription::query()->where('service_id', $addon->id)->sole();
    expect($subscription->amount_minor)->toBe(2500)->and($subscription->period)->toBe('month')->and($subscription->state)->toBe(Subscription::ACTIVE)
        ->and($subscription->cancel_at_period_end)->toBeFalse()->and($addon->subscription_id)->toBe($subscription->id);

    // the renewal is a statement line of its own, for the raise alone
    $subscription->forceFill(['next_renewal_at' => now()->subMinute()])->save();
    Subscription::query()->where('service_id', $parent->id)->update(['next_renewal_at' => now()->addDays(20)]);
    expect(app(SubscriptionService::class)->tick()['renewed'])->toBe(1);
    $statement = Invoice::query()->where('meta->subscription_id', $subscription->id)->sole();
    expect(collect($statement->lines)->pluck('sku')->all())->toBe(['limit-raise-renewal'])
        ->and((int) $statement->subtotal_minor)->toBe(2500)
        ->and(collect($statement->lines)->first()['service_id'])->toBe($addon->id);
});

it('gives back exactly its own units when one of two raises ends, and tells the panel', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $base = (int) $parent->entitlements['mailboxes'];
    [$first] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    [$second] = limitRaiseDeliver($org, $parent->fresh(), 'mailboxes', 3);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe($base + 8);

    app(ServiceService::class)->requestAction($first, 'terminate', CommandContext::system('test')->withScope($org->id), 'lr-end-1', ['reason' => 'konec']);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe($base + 3)
        ->and(data_get($first->fresh()->tags, 'addon.revoked_at'))->not->toBeNull()
        ->and(data_get($second->fresh()->tags, 'addon.revoked_at'))->toBeNull();
    $push = Operation::query()->where('idempotency_key', "limit-raise-end:{$first->id}")->sole();
    expect($push->service_id)->toBe($parent->id)->and(data_get($push->desired, 'entitlements.mailboxes'))->toBe($base + 3);
});

it('does not let a customer end a raise the service already uses, while the system can', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $base = (int) $parent->entitlements['nvme_gb'];
    [$raise] = limitRaiseDeliver($org, $parent, 'nvme_gb', 10);
    $parent = $parent->fresh();
    expect($parent->entitlements['nvme_gb'])->toBe($base + 10);
    $parent->forceFill(['tags' => ['usage' => ['metrics' => ['disk' => ['used' => ($base + 5) * 1024 ** 3]]]]])->save();

    expect(limitRaiseRefusal(fn () => app(ServiceService::class)->requestAction($raise, 'terminate', $this->contextFor($owner, $org, 'totp'), 'lr-cust-end', ['reason' => 'šetřím'])))->toBe('plan_change_does_not_fit');
    expect(data_get($raise->fresh()->tags, 'addon.revoked_at'))->toBeNull();

    app(ServiceService::class)->requestAction($raise, 'terminate', CommandContext::system('dunning')->withScope($org->id), 'lr-sys-end', ['reason' => 'dunning']);
    expect($parent->fresh()->entitlements['nvme_gb'])->toBe($base);
});

it('keeps the active raises on top of a new plan', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'month')['version'];

    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard', 'period' => 'month', 'config' => ['upgrade_of' => $parent->id]]], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    $context = $this->contextFor($owner, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'wallet'], 'lr-pc-'.Str::random(6), $context, 'panel')['order'];
    app(PlanChangeService::class)->apply(OrderItem::query()->where('order_id', $order->id)->sole(), $order, CommandContext::system('fulfil')->withScope($org->id));
    driveOperations();

    expect($parent->fresh()->entitlements['mailboxes'])->toBe((int) $standard->entitlements['mailboxes'] + 5);
});

/** An overdue postpaid renewal invoice of a raise, due `$daysAgo` days ago, and its dunning case. */
function limitRaiseOverdue(Organization $org, Service $raise, int $daysAgo): array
{
    $invoice = Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0'.random_int(200, 99999), 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE,
        'subtotal_minor' => 2500, 'discount_minor' => 0, 'tax_minor' => 525, 'total_minor' => 3025, 'paid_minor' => 0, 'issued_at' => now()->subDays($daysAgo + 15), 'due_at' => now()->subDays($daysAgo), 'meta' => ['postpaid' => true]]);

    return [$invoice, app(DunningService::class)->open($org->id, $invoice->id, $raise->id, $invoice->due_at)];
}

it('keeps an unpaid raise through the suspension stage and ends it at the termination stage, never trying to suspend an add-on', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $base = (int) $parent->entitlements['mailboxes'];
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    [, $case] = limitRaiseOverdue($org, $raise, 45);
    $dunning = app(DunningService::class);

    // day 45: other services are suspended here and come back when paid; a raise has nothing to suspend, so it stays
    $dunning->tick();
    expect($case->fresh()->state)->toBe('SUSPENDED')
        ->and($case->actions()->where('action', 'addon_kept')->count())->toBe(1)
        ->and($case->actions()->where('action', 'terminate_addon')->exists())->toBeFalse()
        ->and(data_get($raise->fresh()->tags, 'addon.revoked_at'))->toBeNull()
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base + 5)
        ->and($case->actions()->whereIn('action', ['suspend', 'suspend_retry'])->exists())->toBeFalse();

    // day 61: the termination stage ends it, as it ends every unpaid service
    $this->travel(16)->days();
    $dunning->tick();
    $dunning->tick();
    expect(data_get($raise->fresh()->tags, 'addon.revoked_at'))->not->toBeNull()
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base)
        ->and($case->actions()->where('action', 'terminate')->whereNull('meta->error')->exists())->toBeTrue()
        ->and($case->actions()->whereIn('action', ['suspend', 'suspend_retry'])->exists())->toBeFalse();
});

it('keeps a yearly raise whose overdue renewal is paid after the suspension stage', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org, 'aapanel', 'start', 'year');
    $base = (int) $parent->entitlements['mailboxes'];
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    expect(Subscription::query()->where('service_id', $raise->id)->sole()->period)->toBe('year');
    [$invoice, $case] = limitRaiseOverdue($org, $raise, 40);
    $dunning = app(DunningService::class);

    $dunning->tick(); // day 40: past the suspension stage
    $invoice->forceFill(['state' => Invoice::PAID, 'paid_minor' => $invoice->total_minor])->save(); // paid in full before day 60
    $this->travel(1)->days();
    $dunning->tick();

    expect($case->fresh()->state)->toBe('RESOLVED')
        ->and(data_get($raise->fresh()->tags, 'addon.revoked_at'))->toBeNull() // what was paid for is still delivered
        ->and($raise->fresh()->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base + 5);
});

it('leaves an add-on sold today alone in dunning while the add-on renewals switch is off', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $context = CommandContext::system('addon dunning test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'lr-addon-dunning-seed', $context);
    $sellMailAddon = function () use ($org, $parent, $context): Service {
        $quote = app(QuoteService::class)->quote([['product_key' => 'mail-hosting', 'plan_key' => 'basic', 'period' => 'month', 'config' => ['parent_service_id' => $parent->id]]], 'CZK', [], 1, null, $org);
        $consents = [];
        foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
            $consents[$key] = ['person' => 'test'];
        }
        $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-addon-dunning-'.Str::random(8), $context, 'staff')['order'];

        return app(ServiceService::class)->createFromOrderItem(OrderItem::query()->where('order_id', $order->id)->sole(), $order, $context);
    };
    $openCase = function (Service $addon) use ($org) {
        $invoice = Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-1'.Str::random(6), 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE,
            'subtotal_minor' => 3900, 'discount_minor' => 0, 'tax_minor' => 819, 'total_minor' => 4719, 'paid_minor' => 0, 'issued_at' => now()->subDays(60), 'due_at' => now()->subDays(45), 'meta' => ['postpaid' => true]]);

        return app(DunningService::class)->open($org->id, $invoice->id, $addon->id, $invoice->due_at);
    };
    $today = $sellMailAddon();
    $todayCase = $openCase($today);

    expect(config('onhost.addon_renewals'))->toBeFalse();
    app(DunningService::class)->tick();
    expect($todayCase->actions()->where('action', 'terminate_addon')->exists())->toBeFalse() // an existing add-on is not ended by this change
        ->and(data_get($today->fresh()->tags, 'addon.revoked_at'))->toBeNull();

    config()->set('onhost.addon_renewals', true); // switched on: only an add-on sold renewing (with a live subscription) is dunned as renewing
    $renewing = $sellMailAddon();
    $renewingCase = $openCase($renewing);
    $cancelled = $sellMailAddon(); // renewing once, but its subscription already ended: nothing is billed any more
    Subscription::query()->where('service_id', $cancelled->id)->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
    $cancelledCase = $openCase($cancelled);
    app(DunningService::class)->tick();
    expect($renewingCase->actions()->where('action', 'addon_kept')->exists())->toBeTrue()
        ->and($cancelledCase->actions()->where('action', 'addon_kept')->exists())->toBeFalse()
        ->and($todayCase->actions()->where('action', 'addon_kept')->exists())->toBeFalse()
        ->and(data_get($today->fresh()->tags, 'addon.revoked_at'))->toBeNull();
});

it('grants a raise at no charge only with a second person, bound to the service, the number and the price', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $base = (int) $parent->entitlements['mailboxes'];
    $finance = limitRaiseStaff('billing_finance_admin');
    $uri = "/v1/staff/customers/{$org->id}/limit-raises/free";
    $body = ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5, 'note' => 'Kompenzace výpadku, tiket #812'];

    // who may not waive a price learns nothing; a raise that cannot be had is refused before anybody is asked
    limitRaiseSend($this, limitRaiseStaff('product_manager'), 'POST', $uri, $body)->assertForbidden();
    limitRaiseSend($this, $finance, 'POST', $uri, ['metric' => 'php_workers'] + $body)->assertStatus(422)->assertJsonPath('error', 'limit_raise_not_enforced');
    expect(Approval::query()->count())->toBe(0);

    $refused = limitRaiseSend($this, $finance, 'POST', $uri, $body)->assertForbidden()->assertJsonPath('error', 'approval_required');
    $approvalId = (string) $refused->json('approval_id');
    $approval = Approval::query()->findOrFail($approvalId);
    expect(data_get($approval->payload, 'command.payload'))->toMatchArray(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])
        ->and(data_get($approval->payload, 'command.payload.price'))->toBe(['currency' => 'CZK', 'net_minor' => 2500, 'period' => 'month'])
        ->and(Order::query()->where('organization_id', $org->id)->count())->toBe(0);

    // the approval is for five mailboxes of this service at this price — not for six
    $other = limitRaiseSend($this, $finance, 'POST', $uri, ['units' => 6] + $body)->assertForbidden();
    expect((string) $other->json('approval_id'))->not->toBe($approvalId);

    secondPersonApproves($approvalId);
    $done = limitRaiseSend($this, $finance, 'POST', $uri, $body)->assertCreated();
    $order = Order::query()->findOrFail((string) $done->json('order_id'));
    expect((int) $order->total_minor)->toBe(0)->and($order->source)->toBe('staff');
    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    expect(data_get($item->config, 'limit_raise.waived.approval_ids'))->toBe([$approvalId])
        ->and((int) $item->discount_minor)->toBe(2500); // the invoice shows what was waived
    $addon = Service::query()->where('order_item_id', $item->id)->sole();
    $subscription = Subscription::query()->where('service_id', $addon->id)->sole();
    expect($subscription->amount_minor)->toBe(2500)->and($subscription->cancel_at_period_end)->toBeTrue() // one period only; a renewal would cost the list price
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base + 5)
        ->and(Approval::query()->findOrFail($approvalId)->state)->toBe('consumed')
        ->and(AuditEvent::query()->where('action', 'staff.customer.limit_raise.free')->where('result', 'succeeded')->sole()->approval_ids)->toBe([$approvalId]);

    // one operator alone (ONHOST_FOUR_EYES=false): the step-up stays, the audit says nobody else signed
    config()->set('onhost.identity.four_eyes', false);
    $solo = limitRaiseSend($this, $finance, 'POST', $uri, ['units' => 2] + $body)->assertCreated();
    $soloItem = OrderItem::query()->where('order_id', (string) $solo->json('order_id'))->sole();
    expect(data_get($soloItem->config, 'limit_raise.waived.approval_ids'))->toBe(['waived:single-operator']);

    // money given away is seen by staff, not only by the customer
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Navýšení limitu zdarma:%')->count())->toBe(2)
        ->and(Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('body', 'like', '%na jedno období zdarma%')->exists())->toBeTrue();
});

it('refuses a free raise whose approval does not name the service, the number and the price', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $finance = limitRaiseStaff('billing_finance_admin');
    $context = new CommandContext('user', $finance->id, null, null, '127.0.0.1', 'pest', null, stepUpMethod: 'totp');
    $payload = ['op' => 'limit_raise.free', 'organization_id' => $org->id, 'service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5, 'note' => 'test', 'price' => ['currency' => 'CZK', 'net_minor' => 2500, 'period' => 'month']];
    $bus = app(CommandBus::class);

    // the bus opened an approval; somebody approved it; the price moved before the repeat reached the handler
    $approvalId = (string) (function () use ($bus, $payload, $context) {
        try {
            $bus->dispatch(new StaffCustomerCommand('lr-free-a', $payload), $context);
        } catch (DomainError $e) {
            return $e->extra['approval_id'] ?? '';
        }

        return '';
    })();
    secondPersonApproves($approvalId);
    ProductOption::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->where('key', 'mailboxes')->update(['price_per_unit_minor' => json_encode(['CZK' => 800, 'EUR' => 32])]);
    expect(limitRaiseRefusal(fn () => $bus->dispatch(new StaffCustomerCommand('lr-free-b', $payload), $context)))->toBe('limit_raise_price_changed');
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(0);
});

it('no longer raises a limit for free through a raw staff resize or a staff service without an order', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $staff = limitRaiseStaff('platform_owner', false);
    app(StepUpService::class)->grant($staff, 'totp', 'test-session', '127.0.0.1');
    $context = $this->contextFor($staff, $org, 'totp');
    $bus = app(CommandBus::class);
    $mailboxes = (int) $parent->entitlements['mailboxes'];

    expect(limitRaiseRefusal(fn () => $bus->dispatch(new ServiceActionCommand($org->id, 'lr-resize-up', ['service_id' => $parent->id, 'action' => 'resize', 'params' => ['entitlements' => ['mailboxes' => $mailboxes + 50]]]), $context)))->toBe('limit_raise_required');
    expect($parent->fresh()->entitlements['mailboxes'])->toBe($mailboxes);
    // a repair to what the service holds, or less, still runs
    expect(limitRaiseRefusal(fn () => $bus->dispatch(new ServiceActionCommand($org->id, 'lr-resize-same', ['service_id' => $parent->id, 'action' => 'resize', 'params' => ['entitlements' => ['mailboxes' => $mailboxes]]]), $context)))->toBe('accepted');

    expect(limitRaiseRefusal(fn () => $bus->dispatch(new ProvisioningCommand('lr-create-up', ['op' => 'service.create', 'organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['entitlements' => ['mailboxes' => 500]]]), $context)))->toBe('limit_raise_required')
        ->and(limitRaiseRefusal(fn () => $bus->dispatch(new ProvisioningCommand('lr-create-opt', ['op' => 'service.create', 'organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => ['options' => ['mailboxes' => 50]]]), $context)))->toBe('limit_raise_required');
});

it('is an add-on the platform delivers, never listed on the price list, and waived by a CRITICAL staff permission', function () {
    expect(Addons::handled())->toContain('limit-raise')->and(Addons::unsellable())->toBe([]);
    $product = Product::query()->where('key', 'limit-raise')->sole();
    expect($product->family)->toBe('addon')->and($product->state)->toBe('active')->and($product->executor)->toBeNull()
        ->and(data_get($product->meta, 'listed'))->toBeFalse();
    expect(collect(app(CatalogService::class)->publicCatalog('cs', 'CZK'))->pluck('key')->all())->not->toContain('limit-raise')->toContain('web-hosting');
    foreach (['web-hosting', 'web-custom', 'wordpress', 'eshop', 'vps', 'game'] as $parent) {
        expect(data_get(Product::query()->where('key', $parent)->value('meta'), 'addon_products', []))->not->toContain('limit-raise'); // never a cart upsell
    }

    expect(PermissionCatalog::risk('billing.limit_raise.waive'))->toBe(PermissionCatalog::CRITICAL)
        ->and(PermissionCatalog::all()['billing.limit_raise.waive']['audience'])->toBe('staff')
        ->and(RoleCatalog::all()['billing_finance_admin']['permissions'])->toContain('billing.limit_raise.waive')
        ->and(RoleCatalog::all()['platform_owner']['permissions'])->toContain('billing.limit_raise.waive')
        ->and(RoleCatalog::all()['product_manager']['permissions'])->not->toContain('billing.limit_raise.waive')
        ->and(RoleCatalog::all()['sales']['permissions'])->not->toContain('billing.limit_raise.waive');
});

it('reaches a running catalogue through the revision command, as a four-eyes catalogue operation', function () {
    Product::query()->where('key', 'limit-raise')->delete(); // a production installation: the seeder never ran there

    expect(app(CatalogRevisions::class)->pending())->toHaveKey('2026-09-limit-raise');
    $this->artisan('onhost:catalog:revise')->assertSuccessful()->expectsOutputToContain('create product limit-raise');
    expect(Product::query()->where('key', 'limit-raise')->exists())->toBeFalse(); // a dry run

    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful();
    $product = Product::query()->where('key', 'limit-raise')->sole();
    expect($product->family)->toBe('addon')->and(data_get($product->meta, 'listed'))->toBeFalse()->and($product->state)->toBe('active')
        ->and(AuditEvent::query()->where('action', 'catalog.product.create')->where('result', 'succeeded')->exists())->toBeTrue()
        ->and(app(CatalogRevisions::class)->pending())->toBe([]);
    $this->artisan('onhost:catalog:revise', ['--apply' => true, '--yes' => true])->assertSuccessful()->expectsOutputToContain('Nothing pending');

    // in the console it is a price change: a second person; and only a product the code defines can be created
    $command = new CatalogCommand('lr-create', ['op' => 'product.create', 'product_key' => 'limit-raise']);
    expect([$command->riskLevel(), $command->requiresStepUp(), $command->requiresApproval()])->toBe([PermissionCatalog::CRITICAL, true, true]);
    expect(limitRaiseRefusal(fn () => app(CatalogPreflight::class)->check(new CatalogCommand('lr-create-2', ['op' => 'product.create', 'product_key' => 'free-vps']))))->toBe('product_undefined')
        ->and(limitRaiseRefusal(fn () => app(CatalogPreflight::class)->check($command)))->toBe('product_exists');
});

it('tells the doctor about a raise that is neither billed nor approved', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $row = function (): array {
        Artisan::call('onhost:doctor', ['--json' => true]);

        return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'every limit raise is billed or approved');
    };
    expect($row()['status'])->toBe('OK');

    Subscription::query()->where('service_id', $raise->id)->delete();
    $checked = $row();
    expect($checked['status'])->toBe('WARN')->and($checked['detail'])->toContain($raise->id);
});

it('tells the customer, and lets the operator list the raises and push one that never reached the panel', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Limit služby % navýšen')->where('body', 'like', 'Schránky navíc +5%')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-limit-raised')->exists())->toBeTrue();

    // the panel could not be asked (a busy service, a frozen provisioning): the raise says so and the doctor names it
    $tags = (array) $raise->tags;
    $tags['addon']['panel'] = ['state' => 'pending', 'error' => 'operation_in_progress'];
    $raise->forceFill(['tags' => $tags])->save();
    expect(implode(' ', LimitRaises::problems()))->toContain('not on the panel yet');
    $this->artisan('onhost:limit-raise')->assertSuccessful()->expectsOutputToContain('pending');

    $before = Operation::query()->where('service_id', $parent->id)->count();
    $this->artisan('onhost:limit-raise', ['action' => 'push', 'addon' => $raise->id])->assertSuccessful()->expectsOutputToContain('Dry run');
    expect(Operation::query()->where('service_id', $parent->id)->count())->toBe($before);
    $this->artisan('onhost:limit-raise', ['action' => 'push', 'addon' => $raise->id, '--apply' => true])->assertSuccessful()->expectsOutputToContain('Resize requested');
    expect(Operation::query()->where('service_id', $parent->id)->where('idempotency_key', 'like', 'limit-raise-push:%')->count())->toBe(1)
        ->and(data_get($raise->fresh()->tags, 'addon.panel.state'))->toBe('requested')
        ->and(LimitRaises::problems())->toBe([]);
    $this->artisan('onhost:limit-raise', ['action' => 'push', 'addon' => $parent->id])->assertFailed(); // only a raise is pushed
});
it('renews the other add-ons only once the owner switches it on: nothing changes for an add-on sold today', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $context = CommandContext::system('addon renewal test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'lr-addon-seed', $context);
    $mailAddon = function () use ($org, $parent, $context): Service {
        $quote = app(QuoteService::class)->quote([['product_key' => 'mail-hosting', 'plan_key' => 'basic', 'period' => 'month', 'config' => ['parent_service_id' => $parent->id]]], 'CZK', [], 1, null, $org);
        $consents = [];
        foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
            $consents[$key] = ['person' => 'test'];
        }
        $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-addon-'.Str::random(8), $context, 'staff')['order'];

        return app(ServiceService::class)->createFromOrderItem(OrderItem::query()->where('order_id', $order->id)->sole(), $order, $context);
    };

    expect(config('onhost.addon_renewals'))->toBeFalse();
    $today = $mailAddon();
    expect(Subscription::query()->where('service_id', $today->id)->exists())->toBeFalse(); // as before: paid once

    config()->set('onhost.addon_renewals', true);
    $renewing = $mailAddon();
    $subscription = Subscription::query()->where('service_id', $renewing->id)->sole();
    expect($subscription->amount_minor)->toBe(3900)->and($subscription->period)->toBe('month')->and($subscription->state)->toBe(Subscription::ACTIVE)
        ->and(Subscription::query()->where('service_id', $today->id)->exists())->toBeFalse(); // an add-on sold before is not billed now
});

it('does not let a resize that was still running undo a raise delivered meanwhile', function () {
    Event::fake(['onhost.order.paid']);
    $busy = true; // the panel's job queue has not run yet: the first resize waits
    Http::fake([ISP.'/remote/json.php*' => function (Request $request) use (&$busy) {
        return Http::response(['code' => 'ok', 'message' => '', 'response' => match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
            'login' => 'sess-1',
            'client_get' => ['client_id' => 3, 'username' => 'onh_client3', 'limit_mailbox' => 10],
            'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'system_user' => 'web7', 'sys_groupid' => 3, 'hd_quota' => 10240, 'active' => 'y', 'document_root' => '/var/www/clients/client3/web7'],
            'monitor_jobqueue_count' => $busy ? 3 : 0,
            default => true,
        }]);
    }]);
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org, 'ispconfig');
    $base = (int) $parent->entitlements['mailboxes'];
    $context = CommandContext::system('limit raise test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'lr-race-seed', $context);
    // one cart, two raises of the same service (+5 and +3 mailboxes): delivered one after the other
    $quote = app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5]), limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 3])], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-race-'.Str::random(6), $context, 'staff')['order'];
    [$itemA, $itemB] = OrderItem::query()->where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get()->all();
    $first = app(ServiceService::class)->createFromOrderItem($itemA, $order, $context);
    $resize = Operation::query()->where('idempotency_key', "limit-raise:{$itemA->id}")->sole();
    expect($resize->state)->not->toBe(Operation::SUCCEEDED);
    $second = app(ServiceService::class)->createFromOrderItem($itemB, $order, $context);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe($base + 8)
        ->and(data_get($second->fresh()->tags, 'addon.panel.state'))->toBe('pending'); // the service is resizing: its number goes later

    $busy = false;
    driveOperation($resize);
    expect($resize->fresh()->state)->toBe(Operation::SUCCEEDED)
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base + 8); // the first resize's snapshot (base + 5) did not overwrite it

    $this->artisan('onhost:limit-raise', ['action' => 'push', 'addon' => $second->id, '--apply' => true])->assertSuccessful();
    $push = Operation::query()->where('service_id', $parent->id)->where('idempotency_key', 'like', 'limit-raise-push:%')->sole();
    driveOperation($push);
    expect($parent->fresh()->entitlements['mailboxes'])->toBe($base + 8)
        ->and(data_get($first->fresh()->tags, 'addon.revoked_at'))->toBeNull();
});

/*
 * Review round 1 (TASK-0022): a free raise could be switched back on at 0 Kč, an unpaid raise ended before the customer's
 * last chance to pay, a raise paid after its service ended was delivered and billed on, and a raise was priced for months
 * its parent would never run.
 */

it('renews a free raise the customer switches back on at the list price, never at zero', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$owner, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    config()->set('onhost.identity.four_eyes', false); // one operator: the waiver is recorded as single-operator
    $finance = limitRaiseStaff('billing_finance_admin');
    $done = limitRaiseSend($this, $finance, 'POST', "/v1/staff/customers/{$org->id}/limit-raises/free", ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5, 'note' => 'Kompenzace, tiket #9'])->assertCreated();
    $item = OrderItem::query()->where('order_id', (string) $done->json('order_id'))->sole();
    $addon = Service::query()->where('order_item_id', $item->id)->sole();
    $subscription = Subscription::query()->where('service_id', $addon->id)->sole();
    expect($subscription->cancel_at_period_end)->toBeTrue()->and($subscription->auto_renew)->toBeFalse();

    // the customer takes back the cancellation and switches renewal on — the endpoints the client area calls
    $context = $this->contextFor($owner, $org);
    app(SubscriptionService::class)->cancelAtPeriodEnd($subscription->fresh(), false, $context);
    app(SubscriptionService::class)->setAutoRenew($subscription->fresh(), true, $context);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'lr-free-renew-seed', CommandContext::system('test')->withScope($org->id));
    Subscription::query()->where('service_id', $parent->id)->update(['next_renewal_at' => now()->addYears(2)]);
    $this->travelTo($subscription->current_period_end->copy()->addHour());

    app(SubscriptionService::class)->tick();
    $statement = Invoice::query()->where('meta->subscription_id', $subscription->id)->sole();
    expect((int) $statement->subtotal_minor)->toBe(2500) // the waiver covered one period; the next one costs what the option costs
        ->and($subscription->fresh()->amount_minor)->toBe(2500);
});

it('cancels active raises and their subscriptions when the parent is terminated', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $subscription = Subscription::query()->where('service_id', $raise->id)->sole();
    foreach (Operation::query()->where('service_id', $parent->id)->whereNotIn('state', [Operation::SUCCEEDED, Operation::FAILED])->get() as $pending) {
        driveOperation($pending); // the raise's own resize first: one operation at a time per service
    }

    $operation = app(ServiceService::class)->requestAction($parent->fresh(), 'terminate', CommandContext::system('test')->withScope($org->id), 'lr-parent-end', ['reason' => 'konec']);
    driveOperation($operation);

    expect($subscription->fresh()->state)->toBe(Subscription::CANCELLED)
        ->and($subscription->fresh()->auto_renew)->toBeFalse()
        ->and($raise->fresh()->state)->not->toBe(ServiceStateMachine::ACTIVE);
});

it('does not deliver a raise paid after its service was cancelled, and gives the money back', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $base = (int) $parent->entitlements['mailboxes'];
    $context = CommandContext::system('limit raise test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'lr-late-seed', $context);
    $quote = app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    $order = app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-late-'.Str::random(6), $context, 'staff')['order'];
    // meanwhile the service was cancelled: it waits out its grace window, its own subscription is gone
    $parent->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(14)])->save();
    Subscription::query()->where('service_id', $parent->id)->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);

    $wallets = app(WalletService::class);
    $topupsBefore = WalletTopup::query()->where('organization_id', $org->id)->count();
    expect($wallets->spendable($org, 'CZK')->minor)->toBe(5000000 - (int) $order->total_minor); // the order holds its price

    $paid = fn () => app(FulfillPaidOrder::class)->handle((new OutboxMessage)->forceFill(['aggregate_id' => $order->id, 'name' => 'onhost.order.paid']));
    $paid();

    $item = OrderItem::query()->where('order_id', $order->id)->sole();
    expect($item->state)->toBe('refunded')
        ->and(Service::query()->where('order_item_id', $item->id)->exists())->toBeFalse()
        ->and(Subscription::query()->where('organization_id', $org->id)->where('state', Subscription::ACTIVE)->exists())->toBeFalse()
        ->and($parent->fresh()->entitlements['mailboxes'])->toBe($base);
    // the money path (review round 2): the tax document is corrected for exactly the raise's line, and the price is back to
    // spend — the order's reservation released, not captured and topped up again (no new top-up of any kind, no second return)
    $document = Invoice::query()->where('order_id', $order->id)->whereIn('type', ['statement', 'invoice'])->sole();
    $credit = Invoice::query()->where('order_id', $order->id)->where('type', 'credit_note')->sole();
    expect($credit->lines()->count())->toBe(1)
        ->and($credit->lines()->value('order_item_id'))->toBe($item->id)
        ->and((int) $credit->total_minor)->toBe(-(int) $item->total_minor)
        ->and($document->fresh()->state)->toBe(Invoice::CREDITED)
        ->and(WalletHold::query()->findOrFail($order->fresh()->wallet_hold_id)->state)->toBe('released')
        ->and(WalletTopup::query()->where('organization_id', $org->id)->count())->toBe($topupsBefore)
        ->and($wallets->spendable($org, 'CZK')->minor)->toBe(5000000)
        ->and((int) data_get($order->fresh()->meta, 'settlement.returned_minor'))->toBe((int) $item->total_minor);

    // idempotent: the paid event delivered again, or the settlement run again, gives nothing back a second time
    $paid();
    app(OrderSettlement::class)->settle($order->id, $context);
    expect(Invoice::query()->where('order_id', $order->id)->where('type', 'credit_note')->count())->toBe(1)
        ->and(WalletTopup::query()->where('organization_id', $org->id)->count())->toBe($topupsBefore)
        ->and($wallets->spendable($org, 'CZK')->minor)->toBe(5000000);
});

it('stops billing a raise whose service ended without taking it along, and tells the doctor', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $subscription = Subscription::query()->where('service_id', $raise->id)->sole();
    // the service went into its deletion grace window and the raise stayed behind (it arrived after the add-ons were cancelled)
    $parent->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->addDays(14)])->save();
    Subscription::query()->where('service_id', $parent->id)->update(['state' => Subscription::CANCELLED, 'auto_renew' => false]);
    expect(implode(' ', LimitRaises::problems()))->toContain($raise->id)->toContain('service it raises');

    $subscription->forceFill(['next_renewal_at' => now()->subMinute()])->save();
    $stats = app(SubscriptionService::class)->tick();
    expect($stats['renewed'] + $stats['invoiced'] + $stats['failed'])->toBe(0)
        ->and($subscription->fresh()->state)->toBe(Subscription::CANCELLED)
        ->and(Invoice::query()->where('meta->subscription_id', $subscription->id)->exists())->toBeFalse();
});

it('refuses a raise of a service whose subscription ends at the period end or does not renew', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $subscription = Subscription::query()->where('service_id', $parent->id)->sole();

    $subscription->forceFill(['cancel_at_period_end' => true, 'auto_renew' => false])->save();
    expect(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])))->toBe('limit_raise_parent_ending');

    $subscription->forceFill(['cancel_at_period_end' => false, 'auto_renew' => false])->save();
    expect(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])))->toBe('limit_raise_parent_ending');

    $subscription->forceFill(['auto_renew' => true])->save();
    expect(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])))->toBe('accepted');
});

it('counts raises already ordered and not yet delivered against the product maximum', function () {
    Event::fake(['onhost.order.paid']); // the first order waits for fulfilment
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    $context = CommandContext::system('limit raise test')->withScope($org->id);
    app(WalletService::class)->topup($org, Money::decimal('50000', 'CZK'), 'bank', 'lr-open-seed', $context);
    $quote = app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 60])], 'CZK', [], 1, null, $org);
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($quote, $org) as $key) {
        $consents[$key] = ['person' => 'test'];
    }
    app(CheckoutService::class)->placeOrder($quote, $org, null, $consents, ['mode' => 'wallet'], 'lr-open-'.Str::random(6), $context, 'staff');

    // 5 of the plan + 60 ordered + 60 more is above the 5 + 100 the product sells
    expect(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 60])))->toBe('limit_raise_above_max')
        ->and(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 40])))->toBe('accepted');
});

it('prices a raise in the organization currency and refuses one the option has no price for', function () {
    [, $org] = $this->customerWithOrganization([], ['currency' => 'EUR']);
    $parent = limitRaiseParent($org);
    Subscription::query()->where('service_id', $parent->id)->update(['currency' => 'EUR', 'amount_minor' => 390]); // the service is billed in EUR
    $quoteEur = fn () => app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])], 'EUR', [], 1, null, $org);
    $quote = $quoteEur();
    expect($quote->currency)->toBe('EUR')->and((int) $quote->lines[0]['unit_net'])->toBe(20 * 5) // the option's EUR unit price, not a conversion
        ->and((int) data_get($quote->lines[0], 'config.limit_raise.unit_price_minor'))->toBe(20);

    ProductOption::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->where('key', 'mailboxes')
        ->update(['price_per_unit_minor' => json_encode(['CZK' => 500])]);
    expect(limitRaiseRefusal($quoteEur))->toBe('limit_raise_unpriced');
});

it('fails loudly when the plan version a raise is measured against is missing', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    Service::query()->whereKey($parent->id)->update(['plan_version_id' => (string) Str::ulid()]);

    expect(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])))->toBe('limit_raise_plan_version_missing');
});

/*
 * Review round 2 (TASK-0022).
 */

it('refuses a staff resize or a staff service that lifts a limit through a sentinel, a word, a switch, a new key or the limits bag', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org); // start: 5 mailboxes, ssh off, waf basic
    $staff = limitRaiseStaff('platform_owner', false);
    app(StepUpService::class)->grant($staff, 'totp', 'test-session', '127.0.0.1');
    $context = $this->contextFor($staff, $org, 'totp');
    $bus = app(CommandBus::class);
    $resize = fn (array $params) => limitRaiseRefusal(fn () => $bus->dispatch(new ServiceActionCommand($org->id, 'lr-rs-'.Str::random(8), ['service_id' => $parent->id, 'action' => 'resize', 'params' => $params]), $context));
    $create = fn (array $config) => limitRaiseRefusal(fn () => $bus->dispatch(new ProvisioningCommand('lr-cr-'.Str::random(8), ['op' => 'service.create', 'organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_key' => 'start', 'config' => $config]), $context));
    $held = (array) $parent->entitlements;
    expect($held['mailboxes'])->toBe(5)->and($held['ssh'])->toBeFalse()->and($held['waf'])->toBe('basic');

    $raises = [
        'ISPConfig unlimited' => ['mailboxes' => -1],
        'unlimited as text' => ['mailboxes' => '-1'],
        'a word' => ['mailboxes' => 'unlimited'],
        'a boolean for a number' => ['mailboxes' => true],
        'no number (the platform stops counting)' => ['mailboxes' => null],
        'zero (the platform stops counting)' => ['mailboxes' => 0],
        'a switch the plan leaves off' => ['ssh' => true],
        'a switch as a word' => ['ssh' => 'yes'],
        'a better word' => ['waf' => 'advanced+cdn'],
        'a key the service does not hold' => ['dedicated_outbound_ip' => true],
        'a number the service does not hold' => ['snapshots' => 3],
        'a longer list' => ['php_versions' => ['8.0', '8.1', '8.2', '8.3', '8.4']],
    ];
    foreach ($raises as $what => $raise) {
        expect($resize(['entitlements' => $raise]))->toBe('limit_raise_required', "resize: {$what}")
            ->and($create(['entitlements' => $raise]))->toBe('limit_raise_required', "service.create: {$what}");
    }
    // the fair-use limits bag is held to what the plan (or the service) has, too
    expect($resize(['entitlements' => ['mailboxes' => 5], 'limits' => ['cpu_pct' => 400]]))->toBe('limit_raise_required')
        ->and($create(['limits' => ['cpu_pct' => 400]]))->toBe('limit_raise_required');
    expect($parent->fresh()->entitlements)->toBe($held)->and(Service::query()->where('organization_id', $org->id)->count())->toBe(1);

    // lowering and repeating what the service holds still run
    expect($resize(['entitlements' => ['mailboxes' => 2, 'ssh' => false, 'waf' => 'basic', 'php_versions' => $held['php_versions']], 'limits' => []]))->toBe('accepted');
});

it('lets a member raise only a service they may order for: the project of the context and a project-level grant count', function () {
    config()->set('onhost.limit_raise.customer_orders', true);
    [$owner, $org] = $this->customerWithOrganization();
    $mine = Project::query()->create(['organization_id' => $org->id, 'name' => 'Můj', 'slug' => 'muj', 'tags' => []]);
    $theirs = Project::query()->create(['organization_id' => $org->id, 'name' => 'Cizí', 'slug' => 'cizi', 'tags' => []]);
    $parent = limitRaiseParent($org);
    $parent->forceFill(['project_id' => $theirs->id])->save();
    $member = User::factory()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $member->id, 'role_key' => 'billing_admin', 'scope_type' => 'project', 'scope_id' => $mine->id, 'organization_id' => $org->id]);
    $quote = app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])], 'CZK', [], 1, null, $org);
    $orderable = fn (CommandContext $context, string $source = 'panel') => limitRaiseRefusal(fn () => LimitRaisePolicy::assertOrderable($quote, $source, $context));

    expect($orderable($this->contextFor($member, $org)))->toBe('limit_raise_scope') // a grant in another project
        ->and($orderable($this->contextFor($owner, $org)->withScope($org->id, $mine->id)))->toBe('limit_raise_scope') // working in another project
        ->and($orderable($this->contextFor($owner, $org)))->toBe('accepted')
        ->and($orderable($this->contextFor($owner, $org)->withScope($org->id, $theirs->id)))->toBe('accepted');

    $parent->forceFill(['project_id' => $mine->id])->save();
    expect($orderable($this->contextFor($member, $org)))->toBe('accepted');
});

it('waits with the renewal of a raise while its service is failed, and renews it once an operator brings the service back', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $subscription = Subscription::query()->where('service_id', $raise->id)->sole();
    $periodEnd = $subscription->current_period_end->toIso8601String();
    Subscription::query()->where('service_id', $parent->id)->update(['next_renewal_at' => now()->addDays(20)]);
    Service::query()->whereKey($parent->id)->update(['state' => ServiceStateMachine::FAILED]); // a failed resize (the raise's own push, too)
    $subscription->forceFill(['next_renewal_at' => now()->subMinute()])->save();

    $stats = app(SubscriptionService::class)->tick();
    expect($stats['cancelled'] + $stats['renewed'] + $stats['failed'])->toBe(0)
        ->and($subscription->fresh()->state)->toBe(Subscription::ACTIVE)
        ->and($subscription->fresh()->next_renewal_at->isFuture())->toBeTrue()
        ->and($subscription->fresh()->current_period_end->toIso8601String())->toBe($periodEnd)
        ->and(LimitRaises::problems())->toBe([]);

    Service::query()->whereKey($parent->id)->update(['state' => ServiceStateMachine::ACTIVE]); // FAILED → ACTIVE (operator)
    $this->travel(25)->hours();
    expect(app(SubscriptionService::class)->tick()['renewed'])->toBe(1)
        ->and($subscription->fresh()->state)->toBe(Subscription::ACTIVE)
        ->and(Invoice::query()->where('meta->subscription_id', $subscription->id)->count())->toBe(1);
});

it('does not let a raise take the money its unpaid service waits for', function () {
    Event::fake(['onhost.order.paid']);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org);
    [$raise] = limitRaiseDeliver($org, $parent, 'mailboxes', 5);
    $context = CommandContext::system('limit raise test')->withScope($org->id);
    $wallets = app(WalletService::class);
    $wallets->charge($org, $wallets->spendable($org, 'CZK'), 'services', 'lr-drain', $context); // the credit is empty
    $parentSub = Subscription::query()->where('service_id', $parent->id)->sole();
    $raiseSub = Subscription::query()->where('service_id', $raise->id)->sole();
    // the service's renewal failed and waits for money; the raise falls due before its retry
    $parentSub->forceFill(['state' => Subscription::PAST_DUE, 'renewal_failures' => 1, 'next_renewal_at' => now()->addHours(12)])->save();
    $raiseSub->forceFill(['next_renewal_at' => now()->subMinute()])->save();
    $raisePeriod = $raiseSub->current_period_end->toIso8601String();
    $wallets->topup($org, Money::decimal('120', 'CZK'), 'bank', 'lr-parent-only', $context); // covers the service (107.69), not both

    expect(app(SubscriptionService::class)->tick()['renewed'])->toBe(0)
        ->and($raiseSub->fresh()->state)->toBe(Subscription::ACTIVE)
        ->and($raiseSub->fresh()->current_period_end->toIso8601String())->toBe($raisePeriod)
        ->and($wallets->spendable($org, 'CZK')->minor)->toBe(12000);

    // the service renews first; the raise then fails on its own (the money went where the service needed it)
    $this->travel(25)->hours();
    app(SubscriptionService::class)->tick();
    expect($parentSub->fresh()->state)->toBe(Subscription::ACTIVE)
        ->and($raiseSub->fresh()->state)->toBe(Subscription::PAST_DUE);

    // a service stopped for an unpaid invoice (postpaid: its subscription stays active) holds the raise's renewal as well
    $raiseSub->forceFill(['state' => Subscription::ACTIVE, 'next_renewal_at' => now()->subMinute()])->save();
    $held = Service::query()->findOrFail($parent->id);
    $held->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'tags' => SuspensionHold::with((array) $held->tags, SuspensionHold::PAYMENT, 'dunning', $context)])->save();
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'bank', 'lr-more', $context);
    expect(app(SubscriptionService::class)->tick()['renewed'])->toBe(0)
        ->and($raiseSub->fresh()->current_period_end->toIso8601String())->toBe($raisePeriod);
});

it('refuses a raise priced in another currency than the service is billed in', function () {
    [, $org] = $this->customerWithOrganization();
    $parent = limitRaiseParent($org); // billed in CZK
    expect(limitRaiseRefusal(fn () => app(QuoteService::class)->quote([limitRaiseItem(['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])], 'EUR', [], 1, null, $org)))->toBe('limit_raise_currency')
        ->and(limitRaiseRefusal(fn () => limitRaiseQuoteLine($org, ['service_id' => $parent->id, 'metric' => 'mailboxes', 'units' => 5])))->toBe('accepted');
});
