<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Tests\TestCase;

/*
 * Review round 2 of TASK-0021 (billing lens). A held credit order may carry a plan change priced for the moment it was
 * placed: the plan the service ran, its period, its price and the share of the period still ahead. When the owner approves
 * days later, the service may run another plan, its period may have renewed, or most of the period has passed — paying the
 * frozen line then charges the difference twice or for time that is gone. The approval refuses such a line with 409
 * `order_approval_stale`; nothing is reserved and no document is issued.
 *
 * Also the two LOW findings of the round: a customer cancellation racing an approval, and a resubmitted held cart after
 * the balance dropped.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config(['onhost.orders.credit_approval.enabled' => true]);
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
});

function staleApprovalMember(Organization $org, string $role, array $attributes = []): User
{
    $member = User::factory()->create($attributes);
    app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), true);

    return $member;
}

function staleApprovalPost(TestCase $test, User $as, string $uri, array $data): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->postJson($uri, $data, ['Idempotency-Key' => (string) Str::ulid()]);
}

/** A running web hosting on `start`, half way through its monthly period, with its subscription. */
function staleApprovalService(Organization $org): Service
{
    $start = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month');
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['plan_version_id' => $start['version']->id, 'entitlements' => $start['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $start['version']->id, 'price_id' => $start['price']->id, 'currency' => 'CZK', 'period' => 'month',
        'amount_minor' => $start['price']->renewalAmount()->minor, 'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15),
        'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();

    return $service;
}

function staleApprovalUpgrade(TestCase $test, User $as, Organization $org, Service $service, string $plan = 'standard', string $mode = 'wallet'): TestResponse
{
    $quote = app(QuoteService::class)->quote(
        [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => $plan, 'qty' => 1, 'period' => 'month', 'config' => ['upgrade_of' => $service->id]]],
        'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org,
    );

    return staleApprovalPost($test, $as, '/v1/orders', ['quote_id' => $quote->id, 'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []], 'payment' => ['mode' => $mode]]);
}

/** @return array<string,string> an invoicing address, so the paid orders get their documents */
function staleApprovalAddress(): array
{
    return ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz'];
}

/**
 * @param  array{0:User,1:Organization}  $customer  the owner and the organization (TestCase::customerWithOrganization)
 * @return array{0:User,1:Organization,2:User,3:Service} owner, organization, an org_admin, the service
 */
function staleApprovalSetup(array $customer, string $credit = '5000'): array
{
    [$owner, $org] = $customer;
    app(WalletService::class)->topup($org, Money::decimal($credit, 'CZK'), 'card', 'seed:'.Str::ulid(), CommandContext::system('test'), bankProvider: 'comgate');

    return [$owner, $org, staleApprovalMember($org, 'org_admin'), staleApprovalService($org)];
}

/** The refused approval left the waiting order and the credit exactly as they were. */
function staleApprovalUntouched(string $orderId, Organization $org, int $spendableBefore): void
{
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::NEW)->and($order->meta['approval']['state'])->toBe('pending')
        ->and($order->wallet_hold_id)->toBeNull()->and($order->invoice_id)->toBeNull()->and($order->paid_at)->toBeNull()
        ->and(WalletHold::query()->where('reference_id', $orderId)->exists())->toBeFalse()
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($spendableBefore);
}

it('approves a held plan change that still matches the service', function () {
    [$owner, $org, $admin, $service] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    $held = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated();
    expect($held->json('state'))->toBe(OrderStateMachine::NEW)->and($held->json('approval'))->toBe('pending');
    $this->travel(2)->hours();

    staleApprovalPost($this, $owner, "/v1/orders/{$held->json('order_id')}/approval", ['decision' => 'approve'])->assertOk()->assertJsonPath('approval', 'approved');
    expect(Order::query()->findOrFail($held->json('order_id'))->wallet_hold_id)->not->toBeNull();
});

it('refuses approving a held upgrade after the owner upgraded the same service: no hold, no document, credit unchanged', function () {
    [$owner, $org, $admin, $service] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    $orderId = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated()->json('order_id');
    $this->travel(20)->minutes(); // outside the duplicate window: the owner's cart is an order of its own
    $paid = staleApprovalUpgrade($this, $owner, $org, $service)->assertCreated();
    expect($paid->json('order_id'))->not->toBe($orderId)->and($paid->json('state'))->toBeIn([OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE]);
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;

    $refused = staleApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_approval_stale');
    expect($refused->json('reason'))->toBeIn(['plan_changed', 'competing_change']);
    staleApprovalUntouched($orderId, $org, $before);
});

it('refuses the second of two held upgrades of one service once the first was approved', function () {
    [$owner, $org, $admin, $service] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    $first = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated()->json('order_id');
    $this->travel(20)->minutes();
    $second = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated()->json('order_id');
    expect($second)->not->toBe($first);

    staleApprovalPost($this, $owner, "/v1/orders/{$first}/approval", ['decision' => 'approve'])->assertOk();
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;
    $refused = staleApprovalPost($this, $owner, "/v1/orders/{$second}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_approval_stale');
    expect($refused->json('reason'))->toBeIn(['plan_changed', 'competing_change']);
    staleApprovalUntouched($second, $org, $before);
});

it('refuses approving a held upgrade after the subscription renewed into a new period', function () {
    [$owner, $org, $admin, $service] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    $orderId = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated()->json('order_id');
    $this->travel(16)->days();
    $subscription = Subscription::query()->where('service_id', $service->id)->firstOrFail();
    $renewedFrom = $subscription->current_period_end;
    $subscription->forceFill(['current_period_start' => $renewedFrom, 'current_period_end' => $renewedFrom->copy()->addMonth(), 'next_renewal_at' => $renewedFrom->copy()->addMonth()->subWeek()])->save();
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;

    staleApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_approval_stale')->assertJsonPath('reason', 'period_renewed');
    staleApprovalUntouched($orderId, $org, $before);
});

it('refuses approving a held upgrade priced for a share of the period that has since passed', function () {
    [$owner, $org, $admin, $service] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    $orderId = staleApprovalUpgrade($this, $admin, $org, $service)->assertCreated()->json('order_id');
    $this->travel(3)->days();
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;

    staleApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_approval_stale')->assertJsonPath('reason', 'proration_expired');
    staleApprovalUntouched($orderId, $org, $before);
});

it('a customer cancellation that read the order before an approval committed does not undo the paid order', function () {
    [$owner, $org] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()));
    // a disposable address on a new account: the intake pre-check holds the approved order in PAID for staff, the state the race hits
    $admin = staleApprovalMember($org, 'org_admin', ['email' => 'nekdo@mailinator.com']);
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'start']], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $orderId = staleApprovalPost($this, $admin, '/v1/orders', ['quote_id' => $quote->id, 'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []], 'payment' => ['mode' => 'wallet']])->assertCreated()->json('order_id');

    $readByTheRequester = Order::query()->findOrFail($orderId); // the cancellation read the order while it was still waiting…
    staleApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertOk()->assertJsonPath('state', OrderStateMachine::PAID); // …and the owner's approval committed

    $refused = null;
    try {
        app(CheckoutService::class)->cancel($readByTheRequester, $this->contextFor($admin, $org), 'nechci', false);
    } catch (DomainError $e) {
        $refused = $e;
    }
    $order = Order::query()->findOrFail($orderId);
    expect($refused)->not->toBeNull()->and($order->state)->not->toBe(OrderStateMachine::CANCELLED)->and($order->meta['approval']['state'])->toBe('approved')
        ->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('active');
});

it('a resubmitted held cart returns the waiting order even after the balance dropped', function () {
    [$owner, $org, $admin] = staleApprovalSetup($this->customerWithOrganization([], staleApprovalAddress()), '250'); // covers the waiting order (107,69 Kč) or the owner's (228,69 Kč), not both
    $quoteFor = fn (string $plan) => app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => $plan]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
    $first = staleApprovalPost($this, $admin, '/v1/orders', ['quote_id' => $quoteFor('start')->id, 'consents' => $consents, 'payment' => ['mode' => 'wallet']])->assertCreated()->json('order_id');
    staleApprovalPost($this, $owner, '/v1/orders', ['quote_id' => $quoteFor('standard')->id, 'consents' => $consents, 'payment' => ['mode' => 'wallet']])->assertCreated()->assertJsonPath('state', OrderStateMachine::PAID);

    $again = staleApprovalPost($this, $admin, '/v1/orders', ['quote_id' => $quoteFor('start')->id, 'consents' => $consents, 'payment' => ['mode' => 'wallet']]);
    expect($again->status())->toBeLessThan(300)->and($again->json('order_id'))->toBe($first)
        ->and(Order::query()->where('organization_id', $org->id)->count())->toBe(2);
});
