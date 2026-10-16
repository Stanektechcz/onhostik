<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * W5-6 (docs/compliance/dpia-funnel-telemetry.md, "Report"): onhost:funnel-report is a read-only platform aggregate over
 * funnel_daily and orders — counts per day/week and currency, cells under 5 suppressed, no identifiers, nothing written.
 */

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'UTC'));
    config(['onhost.funnel_metrics' => true]);
    fnrSeed();
});

afterEach(function () {
    Carbon::setTestNow();
});

function fnrOrder(string $organization, string $currency, string $placedAt, ?string $paidAt = null, ?string $cancelledAt = null): void
{
    $id = 'ord_fnr'.Str::lower(Str::random(16));
    DB::table('orders')->insert([
        'id' => $id, 'number' => 'FNR-'.Str::upper(Str::random(10)), 'organization_id' => $organization,
        'user_id' => 'usr_fnr_'.$organization, 'state' => $cancelledAt !== null ? 'CANCELLED' : ($paidAt !== null ? 'PAID' : 'NEW'),
        'currency' => $currency, 'subtotal_minor' => 10000, 'tax_minor' => 2100, 'total_minor' => 12100, 'payment_mode' => 'gateway',
        'idempotency_key' => 'fnr-'.$id, 'placed_at' => $placedAt, 'paid_at' => $paidAt, 'cancelled_at' => $cancelledAt,
        'meta' => json_encode(['email' => 'fnr-'.$organization.'@example.test']), 'created_at' => $placedAt, 'updated_at' => $placedAt,
    ]);
}

/** @return list<string> */
function fnrOrderIds(): array
{
    return DB::table('orders')->where('id', 'like', 'ord_fnr%')->pluck('id')->map(fn ($id) => (string) $id)->all();
}

function fnrSeed(): void
{
    // CZK, Monday 12 Oct: 6 placed (4 org A, 2 org B), 5 paid, 1 paid and then cancelled.
    foreach (range(1, 4) as $i) {
        fnrOrder('org_fnr_alpha', 'CZK', '2026-10-12 08:00:00', '2026-10-12 09:00:00');
    }
    fnrOrder('org_fnr_beta', 'CZK', '2026-10-12 10:00:00', '2026-10-12 11:00:00');
    fnrOrder('org_fnr_beta', 'CZK', '2026-10-12 10:30:00', '2026-10-12 11:00:00', '2026-10-12 15:00:00');
    // CZK, Tuesday 13 Oct: 3 placed, nothing paid — a suppressed cell.
    foreach (range(1, 3) as $i) {
        fnrOrder('org_fnr_alpha', 'CZK', '2026-10-13 08:00:00');
    }
    // EUR, Monday 12 Oct: 5 placed, 5 paid (org B).
    foreach (range(1, 5) as $i) {
        fnrOrder('org_fnr_beta', 'EUR', '2026-10-12 12:00:00', '2026-10-12 13:00:00');
    }
    // Outside the 30-day window.
    fnrOrder('org_fnr_alpha', 'CZK', '2025-01-10 08:00:00', '2025-01-10 09:00:00');

    DB::table('funnel_daily')->insert([
        ['date' => '2026-10-12', 'plan_key' => 'start', 'period' => 'month', 'locale' => 'cs', 'count' => 10],
        ['date' => '2026-10-12', 'plan_key' => 'standard', 'period' => 'year', 'locale' => 'en', 'count' => 3],
        ['date' => '2026-10-13', 'plan_key' => 'start', 'period' => 'month', 'locale' => 'cs', 'count' => 3],
        ['date' => '2025-01-10', 'plan_key' => 'start', 'period' => 'month', 'locale' => 'cs', 'count' => 50],
    ]);
}

/** @return array<string, mixed> */
function fnrJson(array $options = []): array
{
    expect(Artisan::call('onhost:funnel-report', array_merge(['--json' => true], $options)))->toBe(0);

    return (array) json_decode(Artisan::output(), true);
}

/** @return array<string, mixed> */
function fnrRow(array $report, string $period, string $market): array
{
    foreach ($report['rows'] as $row) {
        if ($row['period'] === $period && $row['market'] === $market) {
            return $row;
        }
    }
    throw new RuntimeException("no row {$period} {$market}");
}

it('adds up per day and per ISO week of visible days, split by currency', function () {
    $day = fnrJson();
    expect(fnrRow($day, '2026-10-12', 'CZK')['placed'])->toBe(['value' => 6, 'suppressed' => false])
        ->and(fnrRow($day, '2026-10-12', 'CZK')['paid'])->toBe(['value' => 5, 'suppressed' => false])
        ->and(fnrRow($day, '2026-10-12', 'EUR')['placed'])->toBe(['value' => 5, 'suppressed' => false])
        ->and(fnrRow($day, '2026-10-12', 'EUR')['paid'])->toBe(['value' => 5, 'suppressed' => false])
        ->and($day['quotes'][0])->toMatchArray(['period' => '2026-10-12', 'quotes' => ['value' => 13, 'suppressed' => false]])
        ->and($day['note'])->toBe('součty jsou dolní mez')
        ->and($day['paid_label'])->toBe('paid (bez odečtu refundací)')
        ->and($day['suppression'])->toBe('buňky <5 (včetně 0) potlačené, týden = součet viditelných dnů');

    // W6-2: the suppressed Tuesday (3 CZK placed, 3 quotes) never enters the week
    $week = fnrJson(['--group' => 'week']);
    expect($week['rows'])->toHaveCount(2)
        ->and(fnrRow($week, '2026-10-12', 'CZK')['placed'])->toBe(['value' => 6, 'suppressed' => false])
        ->and(fnrRow($week, '2026-10-12', 'CZK')['paid'])->toBe(['value' => 5, 'suppressed' => false])
        ->and(fnrRow($week, '2026-10-12', 'EUR')['placed'])->toBe(['value' => 5, 'suppressed' => false])
        ->and($week['quotes'])->toHaveCount(1)
        ->and($week['quotes'][0]['quotes'])->toBe(['value' => 13, 'suppressed' => false])
        ->and($week['totals'])->toBe(['placed' => 11, 'paid' => 10, 'quotes' => 13]);
});

it('suppresses a cell under five and leaves it out of the totals', function () {
    $day = fnrJson();
    expect(fnrRow($day, '2026-10-13', 'CZK')['placed'])->toBe(['value' => null, 'suppressed' => true])
        ->and(fnrRow($day, '2026-10-13', 'CZK')['conversion'])->toBeNull()
        ->and($day['quotes'][1]['quotes'])->toBe(['value' => null, 'suppressed' => true])
        ->and($day['totals'])->toBe(['placed' => 11, 'paid' => 10, 'quotes' => 13]);

    Artisan::call('onhost:funnel-report');
    $text = Artisan::output();
    expect($text)->toContain('<5')->toContain('součty jsou dolní mez')->toContain('týden = součet viditelných dnů')->toContain('placed 11');
});

it('never prints an organization, user, e-mail or order identifier', function () {
    Artisan::call('onhost:funnel-report');
    $text = Artisan::output();
    Artisan::call('onhost:funnel-report', ['--json' => true, '--group' => 'week']);
    $output = $text.Artisan::output();

    foreach (array_merge(['org_fnr_alpha', 'org_fnr_beta', 'usr_fnr_', '@example.test', 'FNR-'], fnrOrderIds()) as $needle) {
        expect($output)->not->toContain($needle);
    }
});

it('does not count a cancelled order as paid', function () {
    $day = fnrJson();
    expect(fnrRow($day, '2026-10-12', 'CZK')['paid']['value'])->toBe(5)
        ->and(fnrRow($day, '2026-10-12', 'CZK')['conversion'])->toBe('83.3 %');
});

it('leaves quotes out with a warning while funnel_metrics is off', function () {
    config(['onhost.funnel_metrics' => false]);
    $day = fnrJson();
    expect($day['quotes'])->toBeNull()
        ->and($day['funnel_metrics'])->toBeFalse()
        ->and($day['totals']['quotes'])->toBeNull();

    Artisan::call('onhost:funnel-report');
    expect(Artisan::output())->toContain('funnel_metrics vypnuto, konverze se nepočítá')->not->toContain('poptávky');
});

it('writes nothing', function () {
    $tables = ['orders', 'funnel_daily', 'outbox_messages', 'audit_events'];
    $before = array_map(fn (string $t) => DB::table($t)->count(), $tables);

    fnrJson();
    fnrJson(['--group' => 'week']);
    Artisan::call('onhost:funnel-report');

    expect(array_map(fn (string $t) => DB::table($t)->count(), $tables))->toBe($before);
});

it('refuses a window outside 1-366 days', function () {
    expect(Artisan::call('onhost:funnel-report', ['--days' => '0']))->toBe(1)
        ->and(Artisan::output())->toContain('1-366')
        ->and(Artisan::call('onhost:funnel-report', ['--days' => '400']))->toBe(1)
        ->and(Artisan::call('onhost:funnel-report', ['--days' => '366']))->toBe(0);
});

/** W6-2: CZK orders in the ISO week of Monday 28 Sep, 5 a day except Thursday 1 Oct with 3 (a suppressed day). */
function fnrW62Week(): void
{
    foreach (['2026-09-29' => 5, '2026-09-30' => 5, '2026-10-01' => 3, '2026-10-02' => 5, '2026-10-03' => 5] as $date => $count) {
        foreach (range(1, $count) as $i) {
            fnrOrder('org_fnr_gamma', 'CZK', $date.' 08:00:00');
        }
    }
}

it('never shows a week from which a suppressed day can be subtracted (W6-2)', function () {
    fnrW62Week();

    $week = fnrRow(fnrJson(['--group' => 'week']), '2026-09-28', 'CZK');
    expect($week['placed'])->toBe(['value' => 20, 'suppressed' => false]);

    $visibleDays = 0;
    foreach (fnrJson()['rows'] as $row) {
        if ($row['market'] === 'CZK' && $row['period'] >= '2026-09-28' && $row['period'] <= '2026-10-04') {
            $visibleDays += (int) $row['placed']['value'];
        }
    }
    expect($visibleDays)->toBe(20)->and($week['placed']['value'] - $visibleDays)->toBe(0);
});

it('gives the same week for --days N and N+1 when the extra day is suppressed (W6-2)', function () {
    fnrW62Week();

    // 14 Oct is today: --days=13 starts on Friday 2 Oct, --days=14 adds the suppressed Thursday 1 Oct
    $without = fnrRow(fnrJson(['--group' => 'week', '--days' => '13']), '2026-09-28', 'CZK');
    $with = fnrRow(fnrJson(['--group' => 'week', '--days' => '14']), '2026-09-28', 'CZK');
    expect($without['placed'])->toBe(['value' => 10, 'suppressed' => false])
        ->and($with['placed'])->toBe($without['placed']);

    // a visible extra day does show: --days=15 adds Wednesday 30 Sep
    expect(fnrRow(fnrJson(['--group' => 'week', '--days' => '15']), '2026-09-28', 'CZK')['placed']['value'])->toBe(15);
});