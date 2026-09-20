<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\MeteringService;
use Onhost\Domain\Billing\Models\BillingPeriod;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\RatedUsage;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\UsageEvent;
use Onhost\Domain\Billing\RatingService;
use Onhost\Domain\Billing\ReportService;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

function webService(Organization $org, array $overrides = []): array
{
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'start'))->firstOrFail();
    $order = Order::query()->create(['number' => 'OH-2026-9001', 'organization_id' => $org->id, 'state' => 'ACTIVE', 'currency' => 'CZK', 'subtotal_minor' => 18900, 'discount_minor' => 0, 'tax_minor' => 3969, 'total_minor' => 22869, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'bill-'.uniqid(), 'placed_at' => now()]);
    $item = OrderItem::query()->create(['order_id' => $order->id, 'sku' => 'web-hosting-start', 'product_key' => 'web-hosting', 'plan_version_id' => $version->id, 'price_id' => $version->prices()->where('currency', 'CZK')->where('period', 'month')->value('id'), 'name' => 'Webhosting Start', 'qty' => 1, 'unit_net_minor' => 18900, 'discount_minor' => 0, 'tax_rate' => 21, 'tax_minor' => 3969, 'total_minor' => 22869, 'period' => 'month', 'config' => ['renewal_net_minor' => 18900, 'periods_billed' => 1, 'currency' => 'CZK', 'family' => 'web'], 'state' => 'active']);
    $service = Service::query()->create(array_merge(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_version_id' => $version->id, 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'shop.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['executor' => 'ispconfig'], 'sla_class' => 'standard', 'activated_at' => now()->subMonth()->addDays(3), 'order_item_id' => $item->id], $overrides));

    return [$service, $item];
}

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('creates a monthly subscription on activation and renews it from the wallet with a credit statement', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $ctx);
    [$service, $item] = webService($org);
    $subscriptions = app(SubscriptionService::class);

    $sub = $subscriptions->ensureForService($service, $item, $ctx);
    expect($sub->period)->toBe('month')->and($sub->amount_minor)->toBe(18900)->and($sub->current_period_end->toDateString())->toBe($service->activated_at->copy()->addMonth()->toDateString())->and($sub->next_renewal_at->toDateString())->toBe($service->activated_at->copy()->addMonth()->subDays(7)->toDateString());
    expect($subscriptions->ensureForService($service, $item, $ctx)->id)->toBe($sub->id);

    expect($subscriptions->tick())->toMatchArray(['renewed' => 1, 'failed' => 0]);
    $sub->refresh();
    expect($sub->state)->toBe(Subscription::ACTIVE)->and($sub->current_period_start->toDateString())->toBe($service->activated_at->copy()->addMonth()->toDateString())->and($sub->last_renewed_at)->not->toBeNull();
    $statement = Invoice::query()->where('type', 'statement')->where('meta->subscription_id', $sub->id)->firstOrFail();
    expect($statement->state)->toBe(Invoice::PAID)->and($statement->total_minor)->toBe(18900 + 3969);
    expect(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(100000 - 22869);
    expect(OutboxMessage::query()->where('name', 'subscription.renewed')->exists())->toBeTrue();
    expect($subscriptions->tick())->toMatchArray(['renewed' => 0]); // not due again
});

it('marks a renewal past due without money, opens dunning and recovers automatically after a top-up', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    [$service, $item] = webService($org);
    $subscriptions = app(SubscriptionService::class);
    $sub = $subscriptions->ensureForService($service, $item, $ctx);

    expect($subscriptions->tick())->toMatchArray(['renewed' => 0, 'failed' => 1]);
    $sub->refresh();
    expect($sub->state)->toBe(Subscription::PAST_DUE)->and($sub->renewal_failures)->toBe(1);
    $case = DunningCase::query()->where('service_id', $service->id)->firstOrFail();
    expect($case->state)->toBe(DunningCase::DUE)->and($case->due_at->toDateString())->toBe($sub->current_period_end->toDateString());
    expect(OutboxMessage::query()->where('name', 'subscription.renewal_failed')->exists())->toBeTrue();

    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed-2', $ctx);
    app(OutboxPublisher::class)->relayPending(); // wallet.topup.completed → SettleBillingAfterPayment
    expect($sub->fresh()->state)->toBe(Subscription::ACTIVE)->and($case->fresh()->state)->toBe(DunningCase::RESOLVED);
});

it('meters VPS hours, rates them with the plan hourly price and stops at the monthly cap', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'seed', $ctx);
    $instance = pveLab();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'compute-4'))->firstOrFail();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'plan_version_id' => $version->id, 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => ['vcpu' => 4, 'ipv4' => 1], 'desired_spec' => ['executor' => 'proxmox'], 'sla_class' => 'standard', 'activated_at' => now()->startOfMonth()->addHours(3)->addMinutes(5)]);
    $rating = app(RatingService::class);
    $hourly = $rating->unitPrice($service, 'vm_hours', 'CZK');
    $cap = $rating->monthlyCap($service, 'CZK');
    expect($hourly->minor)->toBe((int) ceil(44900 / 720))->and($cap->minor)->toBe(44900);

    $stats = app(MeteringService::class)->collect(now()->startOfMonth()->addHours(6)->addMinutes(30));
    expect($stats['events'])->toBe(6)->and(UsageEvent::query()->where('metric', 'vm_hours')->count())->toBe(3)->and(UsageEvent::query()->where('metric', 'ipv4_hours')->count())->toBe(3);
    expect(app(MeteringService::class)->collect(now()->startOfMonth()->addHours(6)->addMinutes(30))['events'])->toBe(0); // idempotent

    $rated = $rating->rate();
    expect($rated)->toMatchArray(['rated' => 6, 'charged' => 6, 'deferred' => 0]);
    $period = BillingPeriod::query()->where('organization_id', $org->id)->firstOrFail();
    expect($period->total_minor)->toBe(3 * $hourly->minor + 3 * (int) round(4900 / 720));
    expect(RatedUsage::query()->whereNull('charged_transaction_id')->count())->toBe(0);

    // cap: pretend the service already consumed almost the whole month (one big rated vm_hours event earlier in the month)
    $synthetic = UsageEvent::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'metric' => 'vm_hours', 'quantity' => '700', 'unit' => 'hour', 'period_start' => now()->startOfMonth()->subMonth(), 'period_end' => now()->startOfMonth()->addHours(3), 'source' => 'test', 'dedupe_key' => 'synthetic', 'rated' => true]);
    RatedUsage::query()->create(['usage_event_id' => $synthetic->id, 'organization_id' => $org->id, 'service_id' => $service->id, 'unit_price_minor' => $hourly->minor, 'amount_minor' => 44900 - 3 * $hourly->minor - 10, 'currency' => 'CZK', 'billing_period_id' => $period->id, 'charged_transaction_id' => 'ledger:synthetic']);
    app(MeteringService::class)->collect(now()->startOfMonth()->addHours(8));
    $capped = $rating->rate();
    expect($capped['capped'])->toBeGreaterThan(0);
    $lastVm = RatedUsage::query()->whereIn('usage_event_id', UsageEvent::query()->where('metric', 'vm_hours')->orderByDesc('period_start')->limit(1)->select('id'))->firstOrFail();
    expect($lastVm->amount_minor)->toBe(0)->and($period->fresh()->cap_applied)->toHaveKey($service->id);
    expect((int) RatedUsage::query()->whereIn('usage_event_id', UsageEvent::query()->where('metric', 'vm_hours')->select('id'))->sum('amount_minor'))->toBeLessThanOrEqual(44900);
});

it('drives dunning to suspension and back to resume when the invoice is paid', function () {
    Http::fake([
        PVE.'/nodes/prg1-n2/qemu/1042/status/current' => Http::response(['data' => ['status' => 'running']]),
        PVE.'/nodes/prg1-n2/qemu/1042/config' => fn (Request $r) => $r->method() === 'GET' ? Http::response(pveVmConfig()) : Http::response(['data' => null]),
        PVE.'/nodes/prg1-n2/qemu/1042/status/shutdown' => Http::response(['data' => 'UPID:prg1-n2:1:1:1:qmshutdown:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/qemu/1042/status/start' => Http::response(['data' => 'UPID:prg1-n2:2:2:2:qmstart:1042:onhost@pve!cp:']),
        PVE.'/nodes/prg1-n2/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
    ]);
    [$user, $org] = $this->customerWithOrganization();
    $instance = pveLab();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [], 'desired_spec' => ['executor' => 'proxmox'], 'sla_class' => 'standard', 'activated_at' => now()->subMonths(2)]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'qemu', 'remote_id' => '1042', 'remote_node' => 'prg1-n2', 'meta' => [], 'ownership' => [], 'idempotency_key' => 'b-dun', 'adapter_version' => '1.0.0']);
    $invoice = Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-0100', 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE, 'subtotal_minor' => 44900, 'discount_minor' => 0, 'tax_minor' => 9429, 'total_minor' => 54329, 'paid_minor' => 0, 'buyer' => [], 'seller' => [], 'tax_summary' => [], 'issued_at' => now()->subDays(45), 'due_at' => now()->subDays(31), 'meta' => ['postpaid' => true]]);
    $dunning = app(DunningService::class);
    $case = $dunning->open($org->id, $invoice->id, $service->id, $invoice->due_at);
    expect($dunning->open($org->id, $invoice->id, $service->id, $invoice->due_at)->id)->toBe($case->id);

    $stats = $dunning->tick();
    expect($stats)->toMatchArray(['cases' => 1, 'notices' => 3, 'suspended' => 1]);
    $case->refresh();
    expect($case->state)->toBe(DunningCase::SUSPENDED)->and($case->notices_sent)->toBe([3, 7, 14])->and($case->actions()->pluck('action')->all())->toContain('notice', 'suspend');
    driveOperations();
    expect($service->fresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($service->fresh()->suspended_reason)->toBe('dunning');
    expect(OutboxMessage::query()->where('name', 'dunning.notice')->count())->toBe(3)->and(OutboxMessage::query()->where('name', 'dunning.suspended')->exists())->toBeTrue();

    $invoice->forceFill(['state' => Invoice::PAID, 'paid_minor' => 54329, 'paid_at' => now()])->save();
    OutboxMessage::query()->create(['aggregate_type' => 'invoice', 'aggregate_id' => $invoice->id, 'organization_id' => $org->id, 'name' => 'invoice.paid', 'payload' => ['number' => $invoice->number], 'correlation_id' => 'c', 'available_at' => now(), 'attempts' => 0]);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect($case->fresh()->state)->toBe(DunningCase::RESOLVED)->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);
    expect($case->actions()->pluck('action')->all())->toContain('resume');
});

it('reports MRR, collections and churn from the ledger-backed records', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'seed', $ctx);
    [$service, $item] = webService($org);
    $sub = app(SubscriptionService::class)->ensureForService($service, $item, $ctx);
    app(SubscriptionService::class)->tick();
    $reports = app(ReportService::class);
    expect($reports->mrr()['CZK']['mrr']->minor)->toBe(18900)->and($reports->mrr()['CZK']['arr']->minor)->toBe(18900 * 12)->and($reports->mrr()['CZK']['subscriptions'])->toBe(1);
    $collections = $reports->collections();
    expect($collections['CZK']['collected']->minor)->toBe(22869)->and($collections['dunning']['open_cases'])->toBe(0);
    expect($reports->churn(2))->toHaveCount(2)->and($reports->revenueByMonth(1)[0]['net']->minor ?? null)->toBe(18900);

    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->getJson('/v1/staff/reports/mrr')->assertOk()->assertJsonPath('data.CZK.subscriptions', 1);
    $this->actingAs($user, 'sanctum');
    $this->getJson('/v1/subscriptions')->assertOk()->assertHeader('X-Total-Count', '1')->assertJsonPath('data.0.amount.minor', 18900);
    $this->postJson("/v1/subscriptions/{$sub->id}/cancel", ['cancel' => true])->assertOk()->assertJsonPath('data.cancel_at_period_end', true)->assertJsonPath('data.auto_renew', false);
    $this->getJson('/v1/usage')->assertOk()->assertJsonPath('data.currency', 'CZK');
});

it('does not close an unpaid invoice\'s dunning case because some service of the organization renewed', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    [$service] = webService($org);
    $dunning = app(DunningService::class);
    $invoice = fn (string $number) => Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => $number, 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => Invoice::OVERDUE, 'subtotal_minor' => 4500000, 'discount_minor' => 0, 'tax_minor' => 945000, 'total_minor' => 5445000, 'paid_minor' => 0, 'issued_at' => now()->subDays(40), 'due_at' => now()->subDays(26), 'seller' => [], 'buyer' => [], 'tax_summary' => [], 'meta' => []]);
    $work = $invoice('FV-2026-0200');     // a work-offer invoice: no service behind it
    $ofService = $invoice('FV-2026-0201'); // an invoice of this very service
    $workCase = $dunning->open($org->id, $work->id, null, $work->due_at);
    $invoiceCase = $dunning->open($org->id, $ofService->id, $service->id, $ofService->due_at);
    $renewalCase = $dunning->open($org->id, null, $service->id, now()->subDay()); // a renewal that could not be charged

    // the renewal goes through: exactly the case it caused is closed
    expect($dunning->resolve($org->id, null, $service->id, $ctx))->toBe(1);
    expect($renewalCase->fresh()->state)->toBe(DunningCase::RESOLVED)
        ->and($workCase->fresh()->state)->not->toBe(DunningCase::RESOLVED)
        ->and($invoiceCase->fresh()->state)->not->toBe(DunningCase::RESOLVED);
    // an invoice's case is closed by that invoice being paid
    expect($dunning->resolve($org->id, $work->id, null, $ctx))->toBe(1)->and($workCase->fresh()->state)->toBe(DunningCase::RESOLVED)->and($invoiceCase->fresh()->state)->not->toBe(DunningCase::RESOLVED);
});

it('ends a period on the anchor day where the month has one and on its last day where it has not', function () {
    $end = fn (string $start, string $period, int $count = 1, ?int $anchor = null) => Onhost\Domain\Billing\BillingPeriod::end(Carbon::parse($start), $period, $count, $anchor)->toDateString();
    // addMonth() overflows: 31 January plus a month was 3 March — three free days, and the renewal day drifted for good
    expect($end('2026-01-31 10:00', 'month'))->toBe('2026-02-28')
        ->and($end('2026-02-28 10:00', 'month', 1, 31))->toBe('2026-03-31')   // the anchor brings the day back after February
        ->and($end('2026-03-31 10:00', 'month', 1, 31))->toBe('2026-04-30')
        ->and($end('2026-05-15 10:00', 'month', 3))->toBe('2026-08-15')
        ->and($end('2028-02-29 10:00', 'year'))->toBe('2029-02-28')           // a leap day plus a year is not 1 March
        ->and($end('2029-02-28 10:00', 'year', 3, 29))->toBe('2032-02-29')
        ->and($end('2026-12-31 23:30', 'month'))->toBe('2027-01-31');
});
