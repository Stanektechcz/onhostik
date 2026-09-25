<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\CommerceHousekeeping;
use Onhost\Domain\Orders\CreditOrderApprovals;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\WalletLedger\Models\CreditLine;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Owner decision 20 (TASK-0021): account credit is spent by the organization owner or its billing admin. An order paid from
 * credit (wallet, postpaid) that anybody else places waits: nothing is reserved, documented or provisioned until an owner or
 * billing admin approves it — then it is paid from credit as if they had placed it. Card and bank orders do not change.
 * The behaviour is behind `onhost.orders.credit_approval.enabled` (off by default, so existing customers keep today's path).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config(['onhost.orders.credit_approval.enabled' => true]);
});

/** A member of the organization in one role. */
function creditApprovalMember(Organization $org, string $role, array $attributes = []): User
{
    $member = User::factory()->create($attributes);
    app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), true);

    return $member;
}

function creditApprovalQuote(Organization $org, string $plan = 'start', ?string $promo = null): Quote
{
    return app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => $plan]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, $promo, $org);
}

function creditApprovalConsents(): array
{
    return ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'sla' => []];
}

function creditApprovalTopup(Organization $org, string $amount = '5000'): void
{
    app(WalletService::class)->topup($org, Money::decimal($amount, 'CZK'), 'card', 'seed:'.Str::ulid(), CommandContext::system('test'), bankProvider: 'comgate');
}

/** POST as the given person with a key of its own (a header set with withHeader() would stay for the whole test). */
function creditApprovalPost(TestCase $test, User $as, string $uri, array $data): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->postJson($uri, $data, ['Idempotency-Key' => (string) Str::ulid()]);
}

function creditApprovalOrder(TestCase $test, User $as, Organization $org, string $mode = 'wallet', string $plan = 'start', ?string $promo = null): TestResponse
{
    return creditApprovalPost($test, $as, '/v1/orders', ['quote_id' => creditApprovalQuote($org, $plan, $promo)->id, 'consents' => creditApprovalConsents(), 'payment' => ['mode' => $mode]]);
}

function creditApprovalSpendable(Organization $org): int
{
    return app(WalletService::class)->spendable($org, 'CZK')->minor;
}

it('lets the owner and a billing admin pay from credit at once', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $billing = creditApprovalMember($org, 'billing_admin');

    foreach ([[$owner, 'start'], [$billing, 'standard']] as [$who, $plan]) {
        $placed = creditApprovalOrder($this, $who, $org, 'wallet', $plan)->assertCreated();
        expect($placed->json('state'))->toBe(OrderStateMachine::PAID)->and($placed->json('approval'))->toBeNull();
        $order = Order::query()->findOrFail($placed->json('order_id'));
        expect($order->wallet_hold_id)->not->toBeNull()->and($order->invoice_id)->not->toBeNull()->and($order->meta['approval'] ?? null)->toBeNull();
    }
});

it('keeps an org_admin credit order waiting: no hold, no invoice, no order.paid, credit unchanged', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $before = creditApprovalSpendable($org);

    $placed = creditApprovalOrder($this, $admin, $org)->assertCreated();
    expect($placed->json('state'))->toBe(OrderStateMachine::NEW)->and($placed->json('approval'))->toBe('pending');
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->wallet_hold_id)->toBeNull()->and($order->invoice_id)->toBeNull()->and($order->paid_at)->toBeNull()
        ->and($order->meta['approval'])->toMatchArray(['state' => 'pending', 'requester_id' => $admin->id])
        ->and(creditApprovalSpendable($org))->toBe($before)
        ->and(OutboxMessage::query()->where('name', 'order.approval.required')->where('aggregate_id', $order->id)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('name', 'order.paid')->exists())->toBeFalse();

    // the customer's own view says what it waits for
    expect($this->getJson("/v1/orders/{$order->id}")->assertOk()->json('data.approval'))->toMatchArray(['state' => 'pending', 'requester' => ['id' => $admin->id, 'name' => $admin->name]]);
});

it('holds a postpaid order of an org_admin the same way', function () {
    [, $org] = $this->customerWithOrganization();
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 5000000, 'risk_hold_minor' => 0, 'state' => 'approved']);
    $admin = creditApprovalMember($org, 'org_admin');

    $placed = creditApprovalOrder($this, $admin, $org, 'postpaid')->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->state)->toBe(OrderStateMachine::NEW)->and($order->meta['approval']['state'])->toBe('pending')
        ->and($order->wallet_hold_id)->toBeNull()->and($order->invoice_id)->toBeNull();
});

it('tells every owner and billing admin and nobody else', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@firma.test']);
    creditApprovalTopup($org);
    $billing = creditApprovalMember($org, 'billing_admin', ['email' => 'ucetni@firma.test']);
    $viewer = creditApprovalMember($org, 'viewer', ['email' => 'divak@firma.test']);
    $admin = creditApprovalMember($org, 'org_admin', ['email' => 'admin@firma.test']);

    creditApprovalOrder($this, $admin, $org)->assertCreated();
    app(OutboxPublisher::class)->relayPending();

    $told = Notification::query()->where('event', 'order.approval.required')->whereNotNull('user_id')->pluck('user_id')->all();
    expect($told)->toEqualCanonicalizing([$owner->id, $billing->id]);
    $mailed = MailOutbox::query()->where('template_key', 'order-approval-required')->pluck('to')->all();
    expect($mailed)->toEqualCanonicalizing(['majitel@firma.test', 'ucetni@firma.test'])
        ->and($mailed)->not->toContain($viewer->email)->not->toContain($admin->email);
});

it('a billing admin approves: credit reserved, invoice issued, fulfilment starts', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $billing = creditApprovalMember($org, 'billing_admin');
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');

    $this->actingAs($billing, 'sanctum');
    expect($this->getJson('/v1/orders?approval=pending')->assertOk()->json('data.*.id'))->toBe([$orderId]);

    $decided = creditApprovalPost($this, $billing, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertOk();
    expect($decided->json('state'))->toBe(OrderStateMachine::PAID)->and($decided->json('approval'))->toBe('approved');
    $order = Order::query()->findOrFail($orderId);
    expect($order->wallet_hold_id)->not->toBeNull()->and($order->invoice_id)->not->toBeNull()
        ->and($order->meta['approval'])->toMatchArray(['state' => 'approved', 'decided_by' => $billing->id])
        ->and(OutboxMessage::query()->where('name', 'order.paid')->where('aggregate_id', $orderId)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('name', 'order.approval.approved')->where('aggregate_id', $orderId)->exists())->toBeTrue();
    expect($this->getJson('/v1/orders?approval=pending')->assertOk()->json('data'))->toBe([]);

    // decided once: a second decision is refused
    creditApprovalPost($this, $billing, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_not_awaiting_approval');
});

it('the member who ordered cannot approve it', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');

    creditApprovalPost($this, $admin, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertForbidden();
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::NEW)->and($order->meta['approval']['state'])->toBe('pending')->and($order->wallet_hold_id)->toBeNull();
});

it('rejecting cancels, returns the promo use and tells the requester', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin', ['email' => 'admin@firma.test']);
    $uses = (int) PromoCode::query()->where('code', 'ONHOST10')->value('uses');
    $orderId = creditApprovalOrder($this, $admin, $org, 'wallet', 'start', 'ONHOST10')->assertCreated()->json('order_id');
    expect((int) PromoCode::query()->where('code', 'ONHOST10')->value('uses'))->toBe($uses + 1);

    creditApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'reject'])->assertStatus(422)->assertJsonPath('error', 'reason_required');
    $decided = creditApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'reject', 'reason' => 'Tenhle tarif nepotřebujeme'])->assertOk();
    expect($decided->json('state'))->toBe(OrderStateMachine::CANCELLED)->and($decided->json('approval'))->toBe('rejected');
    $order = Order::query()->findOrFail($orderId);
    expect($order->meta['approval'])->toMatchArray(['state' => 'rejected', 'decided_by' => $owner->id, 'reason' => 'Tenhle tarif nepotřebujeme'])
        ->and($order->invoice_id)->toBeNull()->and($order->wallet_hold_id)->toBeNull()
        ->and((int) PromoCode::query()->where('code', 'ONHOST10')->value('uses'))->toBe($uses);

    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'order.approval.rejected')->where('user_id', $admin->id)->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'order-approval-rejected')->where('to', 'admin@firma.test')->exists())->toBeTrue();
});

it('approval without enough credit fails and the order keeps waiting', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org, '250'); // covers the waiting order (107,69 Kč) or the owner's (228,69 Kč), not both
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');
    // the owner spends the credit on something else before anybody approved
    creditApprovalOrder($this, $owner, $org, 'wallet', 'standard')->assertCreated()->assertJsonPath('state', OrderStateMachine::PAID);

    creditApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(402)->assertJsonPath('error', 'insufficient_funds');
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::NEW)->and($order->meta['approval']['state'])->toBe('pending')->and($order->wallet_hold_id)->toBeNull();
});

it('refuses a credit order the organization cannot cover at placement', function () {
    [, $org] = $this->customerWithOrganization();
    $admin = creditApprovalMember($org, 'org_admin');

    creditApprovalOrder($this, $admin, $org)->assertStatus(402)->assertJsonPath('error', 'insufficient_funds');
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(0);
});

it('card and bank orders of the same member are unaffected', function () {
    [, $org] = $this->customerWithOrganization();
    $admin = creditApprovalMember($org, 'org_admin');

    $placed = creditApprovalOrder($this, $admin, $org, 'bank')->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and($order->invoice_id)->not->toBeNull()->and($order->meta['approval'] ?? null)->toBeNull()
        ->and($placed->json('approval'))->toBeNull();
});

it('switch off: an org_admin credit order is paid at once as before', function () {
    config(['onhost.orders.credit_approval.enabled' => false]);
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');

    $placed = creditApprovalOrder($this, $admin, $org)->assertCreated();
    expect($placed->json('state'))->toBe(OrderStateMachine::PAID);
    expect(Order::query()->findOrFail($placed->json('order_id'))->meta['approval'] ?? null)->toBeNull();
});

it('staff assisted and system auto-upgrade orders are not held', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $staff = $this->staff('billing_finance_admin');
    $checkout = app(CheckoutService::class);

    $assisted = $checkout->placeOrder(creditApprovalQuote($org), $org, null, creditApprovalConsents(), ['mode' => 'wallet'], 'staff-order:ca-1', $this->contextFor($staff, $org), 'staff')['order'];
    expect($assisted->refresh()->state)->toBe(OrderStateMachine::PAID);
    $system = $checkout->placeOrder(creditApprovalQuote($org, 'standard'), $org, null, creditApprovalConsents(), ['mode' => 'wallet'], 'auto-upgrade:ca-2', CommandContext::system('usage-watch')->withScope($org->id), 'auto')['order'];
    expect($system->refresh()->state)->toBe(OrderStateMachine::PAID);
});

it('an approved order the risk check held still waits for staff', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin', ['email' => 'nekdo@mailinator.com']); // disposable address + a new account: held by the intake pre-check
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');

    creditApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertOk()->assertJsonPath('state', OrderStateMachine::PAID);
    expect(OutboxMessage::query()->where('name', 'order.review.required')->where('aggregate_id', $orderId)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('name', 'order.paid')->where('aggregate_id', $orderId)->exists())->toBeFalse();
});

it('an order nobody approves expires', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');

    $this->travel((int) config('onhost.orders.credit_approval.expire_days', 7) - 1)->days();
    expect(app(CommerceHousekeeping::class)->expireUnpaid())->toBe(0);
    $this->travel(2)->days();
    expect(app(CommerceHousekeeping::class)->expireUnpaid())->toBe(1);
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::CANCELLED)->and($order->meta['approval']['state'])->toBe('expired')
        ->and(OutboxMessage::query()->where('name', 'order.approval.expired')->where('aggregate_id', $orderId)->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'order.approval.expired')->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('another organization\'s owner cannot see or decide a pending approval', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');
    [$stranger] = $this->customerWithOrganization();

    $this->actingAs($stranger, 'sanctum');
    expect($this->getJson('/v1/orders?approval=pending')->assertOk()->json('data'))->toBe([]);
    $status = creditApprovalPost($this, $stranger, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->status();
    expect($status)->toBeIn([403, 404]);
    expect(Order::query()->findOrFail($orderId)->meta['approval']['state'])->toBe('pending');
});

it('a service-account or AI actor without the permission is held, not waved through', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $checkout = app(CheckoutService::class);

    $onBehalf = new CommandContext('ai', 'run_ca1', $org->id, onBehalfOfUserId: $admin->id);
    $held = $checkout->placeOrder(creditApprovalQuote($org), $org, null, creditApprovalConsents(), ['mode' => 'wallet'], 'ai:ca-1', $onBehalf, 'api')['order'];
    expect($held->refresh()->state)->toBe(OrderStateMachine::NEW)->and($held->meta['approval']['state'])->toBe('pending');

    $nobody = CommandContext::ai('run_ca2', $org->id);
    $held = $checkout->placeOrder(creditApprovalQuote($org, 'standard'), $org, null, creditApprovalConsents(), ['mode' => 'wallet'], 'ai:ca-2', $nobody, 'api')['order'];
    expect($held->refresh()->state)->toBe(OrderStateMachine::NEW)->and($held->meta['approval']['state'])->toBe('pending');
});

it('refuses a decision on an order that is not a credit order waiting for approval', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org, 'bank')->assertCreated()->json('order_id');

    creditApprovalPost($this, $owner, "/v1/orders/{$orderId}/approval", ['decision' => 'approve'])->assertStatus(409)->assertJsonPath('error', 'order_not_awaiting_approval');
});

it('shows the operator, read only, whose credit orders will wait before the switch is turned on', function () {
    config(['onhost.orders.credit_approval.enabled' => false]);
    [, $org] = $this->customerWithOrganization();
    $admin = creditApprovalMember($org, 'org_admin', ['email' => 'admin@report.test']);
    $billing = creditApprovalMember($org, 'billing_admin', ['email' => 'ucetni@report.test']);
    $viewer = creditApprovalMember($org, 'viewer', ['email' => 'divak@report.test']);
    $orders = Order::query()->count();

    $this->artisan('onhost:orders:credit-approval-report', ['--organization' => $org->id])
        ->expectsOutputToContain('Switch onhost.orders.credit_approval.enabled: off')
        ->expectsOutputToContain('admin@report.test')
        ->doesntExpectOutputToContain('ucetni@report.test')
        ->doesntExpectOutputToContain($viewer->email)
        ->assertSuccessful();
    expect(Order::query()->count())->toBe($orders)->and($billing->refresh()->state)->toBe('active')->and($admin->refresh()->state)->toBe('active');
});

it('puts the waiting orders in front of the approvers in the panel billing tab', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin', ['name' => 'Adam Správce']);
    $number = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('number');

    $this->actingAs($owner);
    $seam = $this->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    $payload = json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
    expect($payload['billing']['approvals'])->toHaveCount(1)
        ->and($payload['billing']['approvals'][0])->toMatchArray(['number' => $number, 'requester' => 'Adam Správce', 'total' => 107.69])
        ->and($payload['billing']['approval_expire_days'])->toBe(7);

    // the billing card decides through the approval endpoint; the order screens say the order waits instead of "paid from credit"
    $billing = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-billing.api.js'));
    expect($billing)->toContain("'/orders/' + encodeURIComponent(o.id) + '/approval'")->toContain('var approvals = approvalsLedger(cmp);');
    foreach (['onhost-panel-shop.api.js' => "d.approval === 'pending'", 'onhost-panel-order.api.js' => "x.result.approval === 'pending'", 'onhost-panel-tools.api.js' => "x.r.approval === 'pending'"] as $file => $check) {
        expect((string) file_get_contents(base_path('apps/surfaces/api/'.$file)))->toContain($check);
    }
});

it('a service account actor without the permission is held, not waved through', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $machine = ServiceAccount::query()->create(['organization_id' => $org->id, 'name' => 'Terraform', 'state' => 'active']);

    $context = new CommandContext('service_account', $machine->id, $org->id);
    $held = app(CheckoutService::class)->placeOrder(creditApprovalQuote($org), $org, null, creditApprovalConsents(), ['mode' => 'wallet'], 'sa:ca-1', $context, 'api')['order'];
    expect($held->refresh()->state)->toBe(OrderStateMachine::NEW)->and($held->meta['approval'])->toMatchArray(['state' => 'pending', 'requester_type' => 'service_account'])
        ->and($held->wallet_hold_id)->toBeNull();
});

it('tells only current approvers: not a billing admin whose access expired or whose membership ended', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'majitel@stary.test']);
    creditApprovalTopup($org);
    $temporary = User::factory()->create(['email' => 'docasny@stary.test']);
    app(OrganizationService::class)->attachMember($org, $temporary, 'billing_admin', CommandContext::system('test'), true, now()->addDay());
    $former = creditApprovalMember($org, 'billing_admin', ['email' => 'byvaly@stary.test']);
    OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $former->id)->delete(); // the membership ended, a stale binding stayed behind
    $admin = creditApprovalMember($org, 'org_admin');
    $this->travel(2)->days();

    creditApprovalOrder($this, $admin, $org)->assertCreated()->assertJsonPath('approval', 'pending');
    app(OutboxPublisher::class)->relayPending();

    expect(CreditOrderPolicy::approvers($org->id)->pluck('id')->all())->toBe([$owner->id])
        ->and(Notification::query()->where('event', 'order.approval.required')->whereNotNull('user_id')->pluck('user_id')->all())->toBe([$owner->id])
        ->and(MailOutbox::query()->where('template_key', 'order-approval-required')->pluck('to')->all())->toBe(['majitel@stary.test']);
});

it('an order approved while the expiry runs stays paid and approved', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');
    $this->travel((int) config('onhost.orders.credit_approval.expire_days', 7) + 1)->days();
    $ownerContext = $this->contextFor($owner, $org);

    // the owner's approval commits right after the expiry read its list of candidates and before it acted on this order
    $approvedMeanwhile = false;
    DB::listen(function (QueryExecuted $query) use (&$approvedMeanwhile, $orderId, $owner, $ownerContext) {
        if ($approvedMeanwhile || ! str_contains($query->sql, 'placed_at') || ! str_contains($query->sql, 'approval')) {
            return;
        }
        $approvedMeanwhile = true;
        app(CreditOrderApprovals::class)->decide(Order::query()->findOrFail($orderId), $owner, 'approve', null, $ownerContext);
    });

    expect(app(CreditOrderApprovals::class)->expirePending())->toBe(0)->and($approvedMeanwhile)->toBeTrue();
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::PAID)->and($order->meta['approval'])->toMatchArray(['state' => 'approved', 'decided_by' => $owner->id])
        ->and(WalletHold::query()->findOrFail($order->wallet_hold_id)->state)->toBe('active')
        ->and(OutboxMessage::query()->where('name', 'order.approval.expired')->where('aggregate_id', $orderId)->exists())->toBeFalse();
});

it('a replayed approval request with the same Idempotency-Key returns the first result, not a second decision', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');

    $this->actingAs($owner, 'sanctum');
    $key = (string) Str::ulid();
    $first = $this->postJson("/v1/orders/{$orderId}/approval", ['decision' => 'approve'], ['Idempotency-Key' => $key])->assertOk();
    $again = $this->postJson("/v1/orders/{$orderId}/approval", ['decision' => 'approve'], ['Idempotency-Key' => $key])->assertOk();
    expect($again->json())->toBe($first->json())
        ->and(OutboxMessage::query()->where('name', 'order.approval.approved')->where('aggregate_id', $orderId)->count())->toBe(1)
        ->and(WalletHold::query()->where('reference_id', $orderId)->count())->toBe(1);
});

it('a personal API token cannot approve a held credit order', function () {
    [$owner, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org);
    $admin = creditApprovalMember($org, 'org_admin');
    $orderId = creditApprovalOrder($this, $admin, $org)->assertCreated()->json('order_id');
    $pat = $owner->createToken('ci', ['services:read', 'invoices:read', 'wallet:read']);
    $pat->accessToken->forceFill(['organization_id' => $org->id])->save();
    $token = $pat->plainTextToken;
    app('auth')->forgetGuards();

    $this->withToken($token)->postJson("/v1/orders/{$orderId}/approval", ['decision' => 'approve'], ['Idempotency-Key' => (string) Str::ulid()])->assertForbidden();
    $order = Order::query()->findOrFail($orderId);
    expect($order->state)->toBe(OrderStateMachine::NEW)->and($order->meta['approval']['state'])->toBe('pending')->and($order->wallet_hold_id)->toBeNull();
});

it('refuses at placement a held order that only the domain renewal reserve could cover', function () {
    [, $org] = $this->customerWithOrganization();
    creditApprovalTopup($org, '150'); // covers the order (107,69 Kč) — but not once the domain reserve is kept aside
    $org->forceFill(['settings' => array_merge((array) $org->settings, ['domain_reserve' => ['CZK' => 10000]])])->save();
    $admin = creditApprovalMember($org, 'org_admin');

    creditApprovalOrder($this, $admin, $org)->assertStatus(402)->assertJsonPath('error', 'insufficient_funds');
    expect(Order::query()->where('organization_id', $org->id)->count())->toBe(0);
});
