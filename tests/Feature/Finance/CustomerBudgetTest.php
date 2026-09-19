<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * The approved budget is not exceeded (Brain card H30). The budget engine existed, but nobody could set a budget, it
 * never started a new month, and only orders respected it — renewals and metered usage went straight past it.
 */

beforeEach(function () {
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('lets the customer set a monthly budget that warns, stops orders and direct charges, and starts again with the month', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('10000', 'CZK'), 'bank', 'budget-seed', $ctx);
    $put = fn (array $body) => $this->withHeader('Idempotency-Key', (string) Str::ulid())->putJson('/v1/wallet/budget', $body);

    $this->actingAs($owner, 'sanctum');
    expect($this->getJson('/v1/wallet/budget')->assertOk()->json('data'))->toBeNull();
    $put(['limit' => '1000', 'hard' => true, 'alert_thresholds' => [50, 300]])->assertStatus(422);
    $set = $put(['limit' => '1000', 'hard' => true, 'alert_thresholds' => [50, 90]])->assertOk()->json('data');
    expect($set['limit']['minor'])->toBe(100000)->and($set['hard'])->toBeTrue()->and($set['used_pct'])->toBe(0)->and($set['resets_on'])->toBe(now()->startOfMonth()->addMonth()->toDateString());

    // a member who may only read the wallet sees the budget and cannot change it
    $viewer = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $viewer, 'viewer', CommandContext::system('test'), true);
    $this->actingAs($viewer, 'sanctum');
    expect($this->getJson('/v1/wallet/budget')->assertOk()->json('data.limit.minor'))->toBe(100000);
    $put(['limit' => '9000'])->assertForbidden();
    $this->actingAs($owner, 'sanctum');

    // a direct charge (metered usage, a renewal) spends the budget like an order does — and warns at the chosen share
    $wallets->charge($org, Money::decimal('600', 'CZK'), 'cloud', 'budget-usage-1', $ctx, 'rated_usage', 'ru_1');
    $wallets->charge($org, Money::decimal('600', 'CZK'), 'cloud', 'budget-usage-1', $ctx, 'rated_usage', 'ru_1'); // the same charge again is one charge
    expect(Budget::query()->where('organization_id', $org->id)->sole()->spent_minor)->toBe(60000);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'budget.threshold')->count())->toBe(1);

    // what would go over it is refused: a direct charge, and an order's hold — with money already held counted in
    expect(fn () => $wallets->charge($org, Money::decimal('500', 'CZK'), 'cloud', 'budget-usage-2', $ctx))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('budget_exceeded'));
    $hold = $wallets->hold($org, Money::decimal('300', 'CZK'), 'order', 'budget-hold-1', $ctx);
    expect(fn () => $wallets->hold($org, Money::decimal('200', 'CZK'), 'order', 'budget-hold-2', $ctx))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('budget_exceeded')); // 600 spent + 300 held + 200
    $wallets->release($hold, 'order cancelled', $ctx);
    // a debt that already exists is paid whatever the budget says
    $wallets->charge($org, Money::decimal('900', 'CZK'), 'services', 'budget-invoice-1', $ctx, 'invoice', 'inv_1', null, false, false);
    expect(DB::table('ledger_transactions')->where('idempotency_key', 'ledger:budget-invoice-1')->exists())->toBeTrue();
    // … and it is still money spent this month: the 90 % warning comes, truthfully
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'budget.threshold')->orderBy('created_at')->get()->map(fn (OutboxMessage $m) => $m->payload['threshold'])->all())->toBe([50, 90]);

    // the first of the next month: the budget starts from zero and its warnings are armed again
    $this->travelTo(now()->startOfMonth()->addMonth()->addHours(2));
    $fresh = $this->getJson('/v1/wallet/budget')->assertOk()->json('data');
    expect($fresh['spent']['minor'])->toBe(0)->and($fresh['period_start'])->toBe(now()->startOfMonth()->toDateString());
    $wallets->charge($org, Money::decimal('500', 'CZK'), 'cloud', 'budget-usage-3', $ctx);
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('name', 'budget.threshold')->orderBy('created_at')->get()->map(fn (OutboxMessage $m) => $m->payload['threshold'])->all())->toBe([50, 90, 50]);

    // limit 0 removes it
    expect($put(['limit' => '0'])->assertOk()->json('data'))->toBeNull();
    expect(Budget::query()->where('organization_id', $org->id)->exists())->toBeFalse();
});

it('treats a renewal the budget refuses like one the credit does not cover: past due, reminders, the real cause said', function () {
    [$owner, $org] = $this->customerWithOrganization(['email' => 'student@skola.test']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'budget-renew-seed', $ctx);
    Budget::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 30000, 'hard' => true, 'alert_thresholds' => [100], 'period_start' => now()->startOfMonth()->toDateString()]);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'desired_spec' => [], 'entitlements' => [], 'sla_class' => 'standard']);
    $subscription = Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 44900, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subMonth(), 'current_period_end' => now(), 'next_renewal_at' => now()]);
    $before = app(WalletService::class)->spendable($org, 'CZK')->minor;

    expect(app(SubscriptionService::class)->renew($subscription, $service, CommandContext::system('test')))->toBe('failed'); // 449 Kč + VAT does not fit into 300 Kč
    expect($subscription->fresh()->state)->toBe(Subscription::PAST_DUE)->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe($before)
        ->and(DunningCase::query()->where('service_id', $service->id)->exists())->toBeTrue()
        ->and(OutboxMessage::query()->where('name', 'subscription.renewal_failed')->sole()->payload['cause'])->toBe('budget');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('event', 'subscription.renewal_failed')->sole()->body)->toContain('Měsíční rozpočet je vyčerpaný');
});
