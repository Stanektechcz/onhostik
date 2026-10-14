<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\ReconciliationItem;
use Onhost\Domain\Payments\Models\ReconciliationRun;
use Onhost\Domain\Payments\Models\Settlement;
use Onhost\Domain\Payments\Models\SettlementItem;
use Onhost\Domain\Payments\PaymentService;
use Onhost\Domain\Payments\ReconciliationHealth;
use Onhost\Domain\Payments\SettlementReconciliation;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * TASK-0235: `PaymentService::reconcile()` compared provider settlements with intents and the ledger, but nothing called it. The
 * daily `onhost:payments:reconcile` reconciles yesterday (a day at the seller's seat, Europe/Prague) for every configured provider
 * with credentials — the bank included — once per provider and day. A provider without credentials is skipped, one that fails does
 * not stop the others, and the job exits 1 only when every provider it tried failed.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([TaxRuleSeeder::class, LegalEntitySeeder::class]);
    config([
        'onhost.payments.reconcile.providers' => ['comgate', 'gopay', 'stripe', 'bank'],
        // no lag unless a test sets one: the scheduled run then looks at yesterday only
        'onhost.payments.reconcile.settlement_lag_days' => ['comgate' => 0, 'gopay' => 0, 'stripe' => 0, 'bank' => 0],
        'onhost.payments.gopay.secret_ref' => 'env://T235_RECON_GOPAY_ABSENT',
        'onhost.payments.stripe.secret_ref' => 'env://T235_RECON_STRIPE_ABSENT',
    ]);
    t235ComgateCredentials('123456', 't235-recon-secret');
    // 23:30 UTC on 14 October is 01:30 on 15 October in Prague: "yesterday" is the 14th there, the 13th in UTC
    $this->travelTo(Carbon::parse('2026-10-14 23:30:00', 'UTC'));
});

function t235ComgateCredentials(?string $merchant, ?string $secret): void
{
    foreach (['T235RECONCG_MERCHANT' => $merchant, 'T235RECONCG_SECRET' => $secret] as $key => $value) {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        } else {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
    config(['onhost.payments.comgate.merchant' => $merchant, 'onhost.payments.comgate.secret_ref' => 'env://T235RECONCG', 'onhost.payments.comgate.test' => true]);
}

function t235Intent(Organization $org, string $provider, string $providerId, string $state, int $amount = 50000): PaymentIntent
{
    return PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => $provider, 'provider_id' => $providerId, 'purpose' => 'topup',
        'amount_minor' => $amount, 'currency' => 'CZK', 'state' => $state, 'method' => 'card', 'idempotency_key' => 't235-'.uniqid('', true)]);
}

/**
 * Comgate over Http::fake: the transfer list of a day, the detail of its one transfer (`$items` transId => price) and the payment
 * status (`$statuses` transId => status). `$transferDays` collects the `date` asked of the transfer list; `$fail` answers it with an
 * HTTP 500 and no JSON.
 *
 * @param  array<string,int>  $items
 * @param  array<string,string>  $statuses
 * @param  list<string>  $transferDays
 */
function t235ComgateFake(array $items, array $statuses, array &$transferDays, bool $fail = false): void
{
    Http::fake([
        'payments.comgate.cz/v2.0/transfer*' => function (Request $request) use ($items, &$transferDays, $fail) {
            if (str_starts_with((string) parse_url($request->url(), PHP_URL_PATH), '/v2.0/transfer/')) {
                return Http::response(['code' => 0, 'message' => 'OK', 'items' => array_map(
                    fn (string $id, int $price) => ['transId' => $id, 'price' => $price, 'curr' => 'CZK', 'fee' => 300, 'refId' => 'ref-'.$id], array_keys($items), $items)]);
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $transferDays[] = (string) ($query['date'] ?? '');

            return $fail ? Http::response('Service Unavailable', 500) : Http::response(['code' => 0, 'message' => 'OK', 'transfers' => [['transferId' => 'T235-TR-1', 'transferDate' => (string) ($query['date'] ?? ''), 'curr' => 'CZK']]]);
        },
        'payments.comgate.cz/v2.0/payment/transId/*' => function (Request $request) use ($statuses, $items) {
            $transId = rawurldecode((string) substr((string) parse_url($request->url(), PHP_URL_PATH), strlen('/v2.0/payment/transId/')));

            return Http::response(['code' => 0, 'message' => 'OK', 'transId' => $transId, 'status' => $statuses[$transId] ?? 'PENDING', 'price' => $items[$transId] ?? 50000, 'curr' => 'CZK', 'method' => 'CARD_CZ_CSOB_2']);
        },
    ]);
}

/** @return list<ScheduledEvent> */
function t235Scheduled(): array
{
    return collect(app(Schedule::class)->events())->filter(fn (ScheduledEvent $e) => str_contains((string) $e->command, 'onhost:payments:reconcile'))->values()->all();
}

it('reconciles yesterday in Prague once per provider and day, and a second run is a no-op', function () {
    [, $org] = $this->customerWithOrganization();
    t235Intent($org, 'comgate', 'T235-PAID-0001', 'SUCCEEDED');
    $days = [];
    t235ComgateFake(['T235-PAID-0001' => 50000], [], $days);

    $this->artisan('onhost:payments:reconcile')
        ->expectsOutputToContain('payments reconcile 2026-10-14: reconciled bank comgate, already -, skipped gopay stripe, failed -')
        ->assertExitCode(0);

    expect($days)->toBe(['2026-10-14']);
    $runs = ReconciliationRun::query()->where('kind', 'payments')->get();
    expect($runs)->toHaveCount(2)
        ->and($runs->pluck('summary.provider')->sort()->values()->all())->toBe(['bank', 'comgate']);
    $comgate = $runs->firstWhere('summary.provider', 'comgate');
    // the day's boundaries at the seller's seat, stored as instants in UTC (summer time: Prague is UTC+2)
    expect($comgate->period_start->toDateTimeString())->toBe('2026-10-13 22:00:00')
        ->and($comgate->period_end->toDateTimeString())->toBe('2026-10-14 22:00:00')
        ->and($comgate->state)->toBe('completed')->and($comgate->mismatches)->toBe(0);

    $this->artisan('onhost:payments:reconcile')
        ->expectsOutputToContain('payments reconcile 2026-10-14: reconciled -, already bank comgate, skipped gopay stripe, failed -')
        ->assertExitCode(0);
    expect(ReconciliationRun::query()->count())->toBe(2)->and($days)->toBe(['2026-10-14']);
});

it('settles an intent the provider settled but that is still pending here', function () {
    [, $org] = $this->customerWithOrganization();
    $intent = t235Intent($org, 'comgate', 'T235-PEND-0001', 'PENDING_CUSTOMER');
    $days = [];
    t235ComgateFake(['T235-PEND-0001' => 50000], ['T235-PEND-0001' => 'PAID'], $days);

    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])->assertExitCode(0);

    expect($intent->refresh()->state)->toBe('SUCCEEDED')
        ->and(SettlementItem::query()->where('provider_id', 'T235-PEND-0001')->value('state'))->toBe('matched')
        ->and(ReconciliationItem::query()->where('kind', 'settled_but_not_succeeded')->exists())->toBeFalse();
});

it('makes a mismatch visible to finance through the reconciliation item and its event', function () {
    $days = [];
    t235ComgateFake(['T235-STRANGER-1' => 12300], [], $days);

    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])
        ->expectsOutputToContain('reconciled comgate')->assertExitCode(0);

    $run = ReconciliationRun::query()->sole();
    expect($run->state)->toBe('mismatch')->and($run->mismatches)->toBe(1)
        ->and(ReconciliationItem::query()->where('run_id', $run->id)->value('kind'))->toBe('settlement_without_intent')
        ->and(OutboxMessage::query()->where('name', 'finance.reconciliation.mismatch')->where('aggregate_id', $run->id)->exists())->toBeTrue();
});

it('skips a provider without credentials with a warning and still exits 0', function () {
    t235ComgateCredentials(null, null);
    Log::spy();

    $this->artisan('onhost:payments:reconcile')
        ->expectsOutputToContain('reconciled bank, already -, skipped comgate gopay stripe, failed -')
        ->assertExitCode(0);

    Log::shouldHaveReceived('warning')->with('payments reconcile: provider skipped, no credentials', Mockery::on(fn (array $c) => $c['provider'] === 'comgate'));
    expect(ReconciliationRun::query()->pluck('summary')->pluck('provider')->all())->toBe(['bank']);

    // every provider skipped is not a failure either: staging has no gateway account
    ReconciliationRun::query()->delete();
    config(['onhost.payments.reconcile.providers' => ['comgate']]);
    $this->artisan('onhost:payments:reconcile')->expectsOutputToContain('reconciled -, already -, skipped comgate, failed -')->assertExitCode(0);
    expect(ReconciliationRun::query()->count())->toBe(0);
});

it('does not let one provider error stop the others, and exits 1 only when every provider it tried failed', function () {
    [, $org] = $this->customerWithOrganization();
    $paid = t235Intent($org, 'bank', 't235-bank-0001', 'SUCCEEDED', 12100);
    BankStatementLine::query()->create(['account' => 'CZ00', 'amount_minor' => 12100, 'currency' => 'CZK', 'variable_symbol' => '2026023501', 'counterparty' => 'Jana',
        'booked_at' => Carbon::parse('2026-10-14 08:00:00', 'UTC'), 'external_id' => 't235-line-1', 'matched_payment_intent_id' => $paid->id, 'state' => 'matched']);
    $days = [];
    t235ComgateFake([], [], $days, fail: true);
    Log::spy();

    $this->artisan('onhost:payments:reconcile')
        ->expectsOutputToContain('reconciled bank, already -, skipped gopay stripe, failed comgate')
        ->assertExitCode(0);

    Log::shouldHaveReceived('warning')->with('payments reconcile: provider failed', Mockery::on(fn (array $c) => $c['provider'] === 'comgate' && $c['day'] === '2026-10-14'
        && class_exists($c['error']) && ! array_key_exists('message', $c)));
    expect(ReconciliationRun::query()->pluck('summary')->pluck('provider')->all())->toBe(['bank'])
        ->and(SettlementItem::query()->where('provider_id', 't235-bank-0001')->value('state'))->toBe('matched');

    // the failed day wrote no run, so the same day can be run again; when every provider tried fails, the job fails
    config(['onhost.payments.reconcile.providers' => ['comgate']]);
    $this->artisan('onhost:payments:reconcile')->expectsOutputToContain('failed comgate')->assertExitCode(1);
});

it('reconciles a given past day and refuses today, a future day, a malformed day and an unknown provider', function () {
    $days = [];
    t235ComgateFake([], [], $days);

    $this->artisan('onhost:payments:reconcile', ['--date' => '2026-10-02', '--provider' => 'comgate'])
        ->expectsOutputToContain('payments reconcile 2026-10-02: reconciled comgate')->assertExitCode(0);
    expect($days)->toBe(['2026-10-02']);

    foreach (['2026-10-15', '2026-10-16', '2026-13-01', '15.10.2026', ''] as $bad) {
        $this->artisan('onhost:payments:reconcile', ['--date' => $bad])->assertExitCode(1);
    }
    $this->artisan('onhost:payments:reconcile', ['--provider' => 'paypal'])->assertExitCode(1);
    expect(ReconciliationRun::query()->count())->toBe(1)->and($days)->toBe(['2026-10-02']);
});

/**
 * Comgate's transfers per day over Http::fake: `$byDay` maps a day to the transIds (price 50000) paid out that day; a day not
 * listed has no transfer.
 *
 * @param  array<string,list<string>>  $byDay
 */
function t235ComgatePayouts(array $byDay): void
{
    Http::fake(['payments.comgate.cz/v2.0/transfer*' => function (Request $request) use ($byDay) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        if (str_starts_with($path, '/v2.0/transfer/')) {
            $day = substr($path, strlen('/v2.0/transfer/T235-'));

            return Http::response(['code' => 0, 'message' => 'OK', 'items' => array_map(fn (string $id) => ['transId' => $id, 'price' => 50000, 'curr' => 'CZK', 'fee' => 300], $byDay[$day] ?? [])]);
        }
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $day = (string) ($query['date'] ?? '');

        return Http::response(['code' => 0, 'message' => 'OK', 'transfers' => isset($byDay[$day]) ? [['transferId' => 'T235-'.$day, 'transferDate' => $day, 'curr' => 'CZK']] : []]);
    }]);
}

function t235PaidIntent(Organization $org, string $provider, string $providerId, string $paidAtUtc): PaymentIntent
{
    $intent = t235Intent($org, $provider, $providerId, 'SUCCEEDED');
    $intent->forceFill(['paid_at' => Carbon::parse($paidAtUtc, 'UTC')])->saveQuietly();

    return $intent;
}

it('does not report a payment whose payout comes days later, neither on its day nor on its payout day', function () {
    config(['onhost.payments.reconcile.settlement_lag_days.comgate' => 2]);
    [, $org] = $this->customerWithOrganization();
    t235PaidIntent($org, 'comgate', 'T235-LATE-0001', '2026-10-12 10:00:00'); // paid on the 12th, paid out on the 14th
    t235ComgatePayouts(['2026-10-14' => ['T235-LATE-0001']]);

    foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $day) {
        $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate', '--date' => $day])->expectsOutputToContain('mismatches 0')->assertExitCode(0);
    }

    expect(ReconciliationRun::query()->where('summary->provider', 'comgate')->count())->toBe(3)
        ->and(ReconciliationItem::query()->count())->toBe(0)
        ->and(SettlementItem::query()->where('provider_id', 'T235-LATE-0001')->value('state'))->toBe('matched');
});

it('reports a payment never paid out exactly once, when its lag has passed', function () {
    config(['onhost.payments.reconcile.settlement_lag_days.comgate' => 2]);
    [, $org] = $this->customerWithOrganization();
    $lost = t235PaidIntent($org, 'comgate', 'T235-LOST-0001', '2026-10-09 22:30:00'); // 00:30 on the 10th in Prague
    t235ComgatePayouts([]);

    foreach (['2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'] as $day) {
        $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate', '--date' => $day])->assertExitCode(0);
    }

    $items = ReconciliationItem::query()->where('kind', 'succeeded_without_settlement')->get();
    expect($items)->toHaveCount(1)->and($items[0]->reference)->toBe($lost->id);
    $run = ReconciliationRun::query()->findOrFail($items[0]->run_id);
    // the run of the 12th: the 10th (Prague) plus a lag of two days
    expect($run->period_start->toDateTimeString())->toBe('2026-10-11 22:00:00')
        ->and(OutboxMessage::query()->where('name', 'finance.reconciliation.mismatch')->count())->toBe(1);
});

it('counts a bank payment paid out by its statement line whatever day the line was booked', function () {
    [, $org] = $this->customerWithOrganization();
    // booked on the 13th, paired by finance on the 14th: the line of the 13th was not matched yet when the 13th was reconciled
    $paired = t235PaidIntent($org, 'bank', 't235-bank-late', '2026-10-14 09:00:00');
    BankStatementLine::query()->create(['account' => 'CZ00', 'amount_minor' => 50000, 'currency' => 'CZK', 'variable_symbol' => '2026023502', 'counterparty' => 'Jana',
        'booked_at' => Carbon::parse('2026-10-13 08:00:00', 'UTC'), 'external_id' => 't235-line-2', 'matched_payment_intent_id' => $paired->id, 'state' => 'matched']);
    $manual = t235PaidIntent($org, 'bank', 't235-bank-none', '2026-10-14 10:00:00'); // marked paid with no statement line

    $this->artisan('onhost:payments:reconcile', ['--provider' => 'bank'])->expectsOutputToContain('mismatches 1')->assertExitCode(0);

    expect(ReconciliationItem::query()->where('kind', 'succeeded_without_settlement')->pluck('reference')->all())->toBe([$manual->id]);
});

it('writes nothing when the run fails half-way, so the day is reconciled again', function () {
    [, $org] = $this->customerWithOrganization();
    t235Intent($org, 'comgate', 'T235-HALF-0001', 'SUCCEEDED');
    t235Intent($org, 'comgate', 'T235-HALF-0002', 'SUCCEEDED');
    $days = [];
    t235ComgateFake(['T235-HALF-0001' => 50000, 'T235-HALF-0002' => 50000, 'T235-HALF-STRANGER' => 100], [], $days);
    $fail = true;
    SettlementItem::creating(function (SettlementItem $item) use (&$fail) {
        if ($fail && $item->provider_id === 'T235-HALF-0002') {
            throw new RuntimeException('disk full');
        }
    });
    Log::spy();

    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])->expectsOutputToContain('failed comgate')->assertExitCode(1);

    Log::shouldHaveReceived('warning')->with('payments reconcile: provider failed', Mockery::on(fn (array $c) => $c['error'] === RuntimeException::class));
    expect(ReconciliationRun::query()->count())->toBe(0)->and(Settlement::query()->count())->toBe(0)->and(SettlementItem::query()->count())->toBe(0)
        ->and(ReconciliationItem::query()->count())->toBe(0)->and(OutboxMessage::query()->where('name', 'finance.reconciliation.mismatch')->count())->toBe(0);

    $fail = false;
    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])->expectsOutputToContain('reconciled comgate')->assertExitCode(0);
    expect(ReconciliationRun::query()->sole()->mismatches)->toBe(1)->and(SettlementItem::query()->count())->toBe(3)
        ->and(OutboxMessage::query()->where('name', 'finance.reconciliation.mismatch')->count())->toBe(1);
});

it('refuses a second run of one provider and day in the database itself', function () {
    $days = [];
    t235ComgateFake([], [], $days);
    [$start, $end] = SettlementReconciliation::bounds('2026-10-14');
    app(PaymentService::class)->reconcile('comgate', $start, $end, CommandContext::system('test'));

    expect(fn () => app(PaymentService::class)->reconcile('comgate', $start, $end, CommandContext::system('test')))->toThrow(UniqueConstraintViolationException::class);
    expect(ReconciliationRun::query()->count())->toBe(1)->and(Settlement::query()->count())->toBe(1);
});

it('catches up a missed day, so a payment never paid out is still reported once', function () {
    config(['onhost.payments.reconcile.settlement_lag_days.comgate' => 2]);
    [, $org] = $this->customerWithOrganization();
    $lost = t235PaidIntent($org, 'comgate', 'T235-MISS-0001', '2026-10-10 08:00:00');
    t235ComgatePayouts([]);
    foreach (['2026-10-10', '2026-10-11'] as $day) {
        $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate', '--date' => $day])->assertExitCode(0);
    }
    // the 12th — the day the lost payment is due — was never run (the scheduler was down); the next scheduled run on the 15th:

    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])
        ->expectsOutputToContain('payments reconcile 2026-10-12: reconciled comgate, already -, skipped -, failed -, mismatches 1')
        ->expectsOutputToContain('payments reconcile 2026-10-13: reconciled comgate')
        ->expectsOutputToContain('payments reconcile 2026-10-14: reconciled comgate')
        ->assertExitCode(0);
    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate'])->expectsOutputToContain('payments reconcile 2026-10-12: reconciled -, already comgate')->assertExitCode(0);

    expect(ReconciliationItem::query()->where('kind', 'succeeded_without_settlement')->pluck('reference')->all())->toBe([$lost->id])
        ->and(ReconciliationRun::query()->count())->toBe(5);
});

it('takes a day as [00:00, next 00:00) in Prague, the summer-time change included', function () {
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00', 'UTC'));
    [, $org] = $this->customerWithOrganization();
    // bank payments marked paid with no statement line: each is reported by the run of the day it was paid on
    $late = t235PaidIntent($org, 'bank', 't235-dst-a', '2026-10-24 21:59:59.5'); // 23:59:59.5 on the 24th (CEST)
    $midnight = t235PaidIntent($org, 'bank', 't235-dst-b', '2026-10-24 22:00:00'); // 00:00 on the 25th
    $lastSecond = t235PaidIntent($org, 'bank', 't235-dst-c', '2026-10-25 22:59:59'); // 23:59:59 on the 25th (CET since 03:00)
    $next = t235PaidIntent($org, 'bank', 't235-dst-d', '2026-10-25 23:00:00'); // 00:00 on the 26th
    foreach (['2026-10-24', '2026-10-25', '2026-10-26'] as $day) {
        $this->artisan('onhost:payments:reconcile', ['--provider' => 'bank', '--date' => $day])->assertExitCode(0);
    }

    $reported = fn (string $start) => ReconciliationItem::query()->where('run_id', ReconciliationRun::query()->where('period_start', Carbon::parse($start, 'UTC'))->value('id'))->pluck('reference')->sort()->values()->all();
    expect($reported('2026-10-23 22:00:00'))->toBe([$late->id])
        ->and($reported('2026-10-24 22:00:00'))->toBe(collect([$midnight->id, $lastSecond->id])->sort()->values()->all())
        ->and($reported('2026-10-25 23:00:00'))->toBe([$next->id]);
    $dst = ReconciliationRun::query()->where('period_start', Carbon::parse('2026-10-24 22:00:00', 'UTC'))->sole();
    expect($dst->period_end->toDateTimeString())->toBe('2026-10-25 23:00:00'); // a 25-hour day
});

it('names in the doctor a gateway with credentials that has no reconciliation for yesterday, and is OK without a gateway', function () {
    $row = fn () => collect(app(ReconciliationHealth::class)->checks())->firstWhere('check', ReconciliationHealth::CHECK);
    // 01:30 in Prague: the 05:15 run has not reconciled the 14th yet, the 13th is due
    expect($row()['ok'])->toBeFalse()->and($row()['detail'])->toBe('comgate has no reconciliation for 2026-10-13')->and($row()['blocking'])->toBeFalse();

    $days = [];
    t235ComgateFake([], [], $days);
    $this->artisan('onhost:payments:reconcile', ['--provider' => 'comgate', '--date' => '2026-10-13'])->assertExitCode(0);
    expect($row()['ok'])->toBeTrue()->and($row()['detail'])->toBe('comgate reconciled for 2026-10-13');

    // staging: no gateway holds credentials — nothing to reconcile, OK
    t235ComgateCredentials(null, null);
    $this->travelTo(Carbon::parse('2026-10-16 10:00:00', 'UTC'));
    expect($row()['ok'])->toBeTrue()->and($row()['detail'])->toContain('no payment gateway holds credentials');
});

it('is scheduled once a day at a quiet hour in Prague, on one server and without overlap', function () {
    $events = t235Scheduled();
    expect($events)->toHaveCount(1);
    $event = $events[0];
    expect($event->expression)->toBe('15 5 * * *')
        ->and($event->timezone)->toBe('Europe/Prague')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('keeps the runbook in step: reconciliation is scheduled', function () {
    $runbook = (string) file_get_contents(base_path('docs/runbooks/comgate.md'));
    expect(str_contains($runbook, '`onhost:payments:reconcile` runs every day at 05:15 Europe/Prague'))->toBeTrue()
        ->and(str_contains($runbook, 'is not scheduled'))->toBeFalse();
});
