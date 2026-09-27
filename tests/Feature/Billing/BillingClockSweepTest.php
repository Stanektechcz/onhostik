<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

require_once __DIR__.'/../../Support/ClockSweep.php';

/*
 * TASK-0047: billing works at every hour of the day. The documents print their dates in the seller's day (Europe/Prague,
 * AccountingClock) — "Splatnost 12.10.2026" — but the jobs that act on those dates compared UTC instants and counted UTC
 * days. Each scheduled path runs here at every quarter of an hour of a day, in UTC and in Prague winter time, at the hour
 * production runs it (overdue 01:15 UTC, dunning 06:00 UTC), and must act on the printed day, whatever hour the document
 * was issued.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** An issued postpaid invoice as InvoiceService::issue() leaves it at `$issuedAt`: due `$dueDays` days later, to the second. */
function billingSweepInvoice(Organization $org, CarbonImmutable $issuedAt, int $dueDays = 14, string $state = Invoice::ISSUED): Invoice
{
    return Invoice::query()->create(['legal_entity' => 'onhost-cz', 'series' => 'FV', 'type' => 'invoice', 'number' => 'FV-2026-S'.random_int(10000, 99999), 'organization_id' => $org->id, 'currency' => 'CZK', 'state' => $state,
        'subtotal_minor' => 10000, 'discount_minor' => 0, 'tax_minor' => 2100, 'total_minor' => 12100, 'paid_minor' => 0, 'issued_at' => $issuedAt, 'supply_date' => AccountingClock::date($issuedAt),
        'due_at' => $issuedAt->addDays($dueDays), 'meta' => ['postpaid' => true]]);
}

/** A production run of a daily job: `$hhmm` UTC on the day `$date` (Y-m-d). */
function billingSweepRun(string $date, string $hhmm): CarbonImmutable
{
    return CarbonImmutable::parse("{$date} {$hhmm}:00", 'UTC');
}

it('marks an invoice overdue on the first overdue run after its printed due date, whatever hour it was issued', function (string $tz, ?string $day) {
    $scenario = function (CarbonImmutable $issuedAt) {
        [, $org] = $this->customerWithOrganization();
        $invoice = billingSweepInvoice($org, $issuedAt->utc());
        $printed = AccountingClock::date($invoice->due_at); // what the PDF says: "Splatnost / Due"
        $invoices = app(InvoiceService::class);

        // the run of the printed due day itself (01:15 UTC is 02:15/03:15 in Prague): the customer still has the whole day
        $this->travelTo(billingSweepRun($printed, '01:15'));
        $invoices->overdueSweep();
        expect($invoice->refresh()->state)->toBe(Invoice::ISSUED, "overdue on its own due date {$printed}");

        // the next run: the due date has passed
        $this->travelTo(billingSweepRun(CarbonImmutable::parse($printed)->addDay()->toDateString(), '01:15'));
        $invoices->overdueSweep();
        expect($invoice->refresh()->state)->toBe(Invoice::OVERDUE, 'still not overdue the day after its due date');
    };

    $failures = clockSweep($scenario, 15, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures));
})->with([
    'UTC, tomorrow' => ['UTC', null],
    'Prague, winter' => ['Europe/Prague', clockSweepNextDay('01-15', 'Europe/Prague')],
]);

it('counts the days a case is overdue in the days the invoice printed, whatever hour it fell due', function (string $tz, ?string $day) {
    $scenario = function (CarbonImmutable $issuedAt) {
        [, $org] = $this->customerWithOrganization();
        $invoice = billingSweepInvoice($org, $issuedAt->utc(), 14, Invoice::OVERDUE);
        $case = app(DunningService::class)->open($org->id, $invoice->id, null, $invoice->due_at);
        $printed = CarbonImmutable::parse(AccountingClock::date($invoice->due_at));
        $dunning = app(DunningService::class);
        $noticeDays = fn () => $case->refresh()->actions()->where('action', 'notice')->get()->map(fn ($a) => [(int) $a->meta['day'], (int) $a->meta['days_overdue']])->all();

        // the daily run (06:00 UTC) on the printed due day and the two days after: nothing is three days overdue yet
        foreach ([0, 1, 2] as $after) {
            $this->travelTo(billingSweepRun($printed->addDays($after)->toDateString(), '06:00'));
            $dunning->tick();
        }
        expect($noticeDays())->toBe([], "a reminder before the third day after the printed due date {$printed->toDateString()}");

        // the third day after it: the first reminder, stating three days
        $this->travelTo(billingSweepRun($printed->addDays(3)->toDateString(), '06:00'));
        $dunning->tick();
        expect($noticeDays())->toBe([[3, 3]], 'the three-day reminder on the third day')
            ->and($case->refresh()->state)->toBe(DunningCase::OVERDUE_NOTICE);
    };

    $failures = clockSweep($scenario, 15, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures));
})->with([
    'UTC, tomorrow' => ['UTC', null],
    'Prague, winter' => ['Europe/Prague', clockSweepNextDay('01-15', 'Europe/Prague')],
]);

it('suspends on the thirtieth printed day and not a day earlier, whatever hour the invoice fell due', function () {
    $scenario = function (CarbonImmutable $issuedAt) {
        [, $org] = $this->customerWithOrganization();
        $invoice = billingSweepInvoice($org, $issuedAt->utc(), 14, Invoice::OVERDUE);
        $case = app(DunningService::class)->open($org->id, $invoice->id, null, $invoice->due_at); // an organization-wide case: a limit, no panel
        $printed = CarbonImmutable::parse(AccountingClock::date($invoice->due_at));
        $dunning = app(DunningService::class);

        $this->travelTo(billingSweepRun($printed->addDays(29)->toDateString(), '06:00'));
        $dunning->tick();
        expect($case->refresh()->state)->not->toBe(DunningCase::SUSPENDED, "limited on day 29 after {$printed->toDateString()}");

        $this->travelTo(billingSweepRun($printed->addDays(30)->toDateString(), '06:00'));
        $dunning->tick();
        expect($case->refresh()->state)->toBe(DunningCase::SUSPENDED, 'not limited on day 30');
    };

    $failures = clockSweep($scenario, 30);

    expect($failures)->toBe([], clockSweepWindows($failures, 30));
});

/** A running web hosting bought for a month at the list price, activated now, with its subscription. */
function billingSweepRenewingService(Organization $org, CommandContext $context): Subscription
{
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('key', 'start'))->firstOrFail();
    $order = Order::query()->create(['number' => 'OH-S-'.random_int(10000, 99999), 'organization_id' => $org->id, 'state' => 'ACTIVE', 'currency' => 'CZK', 'subtotal_minor' => 18900, 'discount_minor' => 0, 'tax_minor' => 3969, 'total_minor' => 22869, 'payment_mode' => 'wallet', 'source' => 'web', 'commit_months' => 1, 'idempotency_key' => 'sweep-'.uniqid(), 'placed_at' => now()]);
    $item = OrderItem::query()->create(['order_id' => $order->id, 'sku' => 'web-hosting-start', 'product_key' => 'web-hosting', 'plan_version_id' => $version->id, 'price_id' => $version->prices()->where('currency', 'CZK')->where('period', 'month')->value('id'), 'name' => 'Webhosting Start', 'qty' => 1, 'unit_net_minor' => 18900, 'discount_minor' => 0, 'tax_rate' => 21, 'tax_minor' => 3969, 'total_minor' => 22869, 'period' => 'month', 'config' => ['renewal_net_minor' => 18900, 'periods_billed' => 1, 'currency' => 'CZK', 'family' => 'web'], 'state' => 'active']);
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_version_id' => $version->id, 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'sweep-'.random_int(1000, 9999).'.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => ['executor' => 'ispconfig'], 'sla_class' => 'standard', 'activated_at' => now(), 'order_item_id' => $item->id]);

    return app(SubscriptionService::class)->ensureForService($service, $item, $context);
}

/** The hourly renewal run (hh:20 UTC) that first finds `$due` due. */
function billingSweepRenewalRun(CarbonImmutable $due): CarbonImmutable
{
    $run = $due->utc()->startOfHour()->addMinutes(20);

    return $run->lessThan($due) ? $run->addHour() : $run;
}

it('renews a month at its price, once, with periods that follow each other day by day, whatever hour the service started', function (string $tz, ?string $day) {
    $scenario = function () {
        [$user, $org] = $this->customerWithOrganization();
        $context = $this->contextFor($user, $org);
        app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'sweep-'.uniqid(), $context);
        $subscription = billingSweepRenewingService($org, $context);
        $subscriptions = app(SubscriptionService::class);
        $lines = [];
        foreach ([1, 2] as $renewal) {
            $run = billingSweepRenewalRun(CarbonImmutable::instance($subscription->refresh()->next_renewal_at));
            $this->travelTo($run->subHour());
            expect($subscriptions->tick()['renewed'])->toBe(0, "renewal {$renewal} ran an hour early");
            $this->travelTo($run);
            expect($subscriptions->tick()['renewed'])->toBe(1, "renewal {$renewal} did not run when due")
                ->and($subscriptions->tick()['renewed'])->toBe(0, "renewal {$renewal} ran twice in one run");
            $statement = Invoice::query()->where('type', 'statement')->where('meta->subscription_id', $subscription->id)->orderByDesc('issued_at')->firstOrFail();
            expect($statement->total_minor)->toBe(18900 + 3969, "renewal {$renewal} charged another amount");
            $lines[] = InvoiceLine::query()->where('invoice_id', $statement->id)->sole();
        }
        // what the documents say: the second period starts the accounting day after the first one ends, no day twice, none lost
        expect($lines[1]->period_from->toDateString())->toBe($lines[0]->period_to->copy()->addDay()->toDateString(), 'the periods do not follow each other')
            ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(200000 - 2 * 22869);
    };

    $failures = clockSweep($scenario, 60, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures, 60));
})->with([
    'UTC, tomorrow' => ['UTC', null],
    'Prague, the 31st of a winter month' => ['Europe/Prague', clockSweepNextDay('01-31', 'Europe/Prague')],
]);
