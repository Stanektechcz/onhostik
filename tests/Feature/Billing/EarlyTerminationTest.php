<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\EarlyTerminationPolicy;
use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Owner decision E3 (2026-10-15): a year costs ten months; ending a yearly subscription early gives back the paid price of the
 * whole months not started yet (the running month counts as used), as a credit note with its VAT. The consumer's statutory
 * withdrawal (fourteen days, by days) is not touched.
 */

function e3Months(string $from, string $to, string $today): array
{
    return EarlyTerminationPolicy::months(CarbonImmutable::parse($from), CarbonImmutable::parse($to), CarbonImmutable::parse($today));
}

it('counts only the whole months that have not started yet', function () {
    expect(e3Months('2026-01-15', '2027-01-14', '2026-01-14'))->toBe(['months' => 12, 'unused' => 12]) // not started: all of it
        ->and(e3Months('2026-01-15', '2027-01-14', '2026-01-15'))->toBe(['months' => 12, 'unused' => 11]) // the first day uses the first month
        ->and(e3Months('2026-01-15', '2027-01-14', '2026-02-14'))->toBe(['months' => 12, 'unused' => 11])
        ->and(e3Months('2026-01-15', '2027-01-14', '2026-02-15'))->toBe(['months' => 12, 'unused' => 10])
        ->and(e3Months('2026-01-15', '2027-01-14', '2027-01-14'))->toBe(['months' => 12, 'unused' => 0])
        ->and(e3Months('2026-01-31', '2027-01-30', '2026-02-27'))->toBe(['months' => 12, 'unused' => 11]) // month ends do not overflow
        ->and(e3Months('2026-03-01', '2026-03-31', '2026-03-10'))->toBe(['months' => 1, 'unused' => 0]); // a part of a month never comes back
});

it('returns months pro rata and never more than is left of the line', function () {
    expect(EarlyTerminationPolicy::refundOf(120000, 120000, 12, 10))->toBe(100000)
        ->and(EarlyTerminationPolicy::refundOf(120000, 50000, 12, 10))->toBe(50000)
        ->and(EarlyTerminationPolicy::refundOf(120000, 120000, 12, 0))->toBe(0)
        ->and(EarlyTerminationPolicy::refundOf(120000, 120000, 0, 0))->toBe(0);
});

describe('settlement', function () {
    beforeEach(function () {
        $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
        Http::preventStrayRequests();
    });

    it('credits the whole unused months of a terminated yearly service once, with the VAT on the credit note', function () {
        [, $org] = $this->customerWithOrganization();
        $service = featureGameService($org);
        chargebackPaidStatement($org, $service, 435600, 75600, -40, 324);
        $day = AccountingClock::now()->startOfDay();
        $expected = EarlyTerminationPolicy::months(CarbonImmutable::parse($day->subDays(40)->toDateString()), CarbonImmutable::parse($day->addDays(324)->toDateString()), CarbonImmutable::parse($day->toDateString()));
        $policy = app(EarlyTerminationPolicy::class);
        $ctx = CommandContext::system('test')->withScope($org->id);

        expect(fn () => $policy->settle($service, $ctx))->toThrow(DomainError::class); // a running service is ended first

        $service->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();
        $result = $policy->settle($service, $ctx);
        $refund = intdiv(435600 * $expected['unused'], $expected['months']);
        $note = Invoice::query()->where('type', 'credit_note')->where('organization_id', $org->id)->sole();

        expect($expected['months'])->toBe(12)->and($result['refund']->minor)->toBe($refund)
            ->and(-(int) $note->total_minor)->toBe($refund)->and((int) $note->tax_minor)->toBeLessThan(0) // VAT goes back on the credit note
            ->and($result['credit_notes'])->toBe([(string) $note->id])
            ->and(fn () => $policy->settle($service->refresh(), $ctx))->toThrow(DomainError::class); // never twice

        app(OutboxPublisher::class)->relayPending();
        expect(OutboxMessage::query()->where('name', 'billing.early_termination.refunded')->exists())->toBeTrue();
    });

    it('leaves the statutory withdrawal prorated by days', function () {
        [, $org] = $this->customerWithOrganization();
        $service = featureGameService($org);
        chargebackPaidStatement($org, $service, 435600, 75600, -5, 359);
        $now = CarbonImmutable::now();

        expect(app(WithdrawalService::class)->estimate($service, $now)['refund']->minor)->toBe(app(ChargebackService::class)->estimate($service, 100, $now)['refund_minor'])
            ->and(app(WithdrawalService::class)->estimate($service, $now)['refund']->minor)->toBeGreaterThan(app(EarlyTerminationPolicy::class)->estimate($service, $now)['refund']->minor);
    });
});

describe('catalogue revision', function () {
    beforeEach(function () {
        $this->seed([CatalogSeeder::class]);
    });

    it('prices a year at ten months in a new version and leaves the held version its price', function () {
        $revisions = app(CatalogRevisions::class);
        expect($revisions->pending('2026-10-yearly-ten-months'))->toBe([]); // the launch catalogue already sells a year as ten months

        $plan = Plan::query()->where('product_id', Product::query()->where('key', 'web-hosting')->value('id'))->orderBy('sort')->firstOrFail();
        $version = $plan->currentVersion();
        $month = Price::query()->where('plan_version_id', $version->id)->where('currency', 'CZK')->where('period', 'month')->where('state', 'active')->sole();
        $year = Price::query()->where('plan_version_id', $version->id)->where('currency', 'CZK')->where('period', 'year')->where('state', 'active')->sole();
        $year->forceFill(['amount_minor' => $month->amount_minor * 12, 'renewal_amount_minor' => $month->amount_minor * 12])->save();

        expect($revisions->pending('2026-10-yearly-ten-months')['2026-10-yearly-ten-months']['yearly'])->toHaveKey('web-hosting/'.$plan->key);

        $done = $revisions->apply('2026-10-yearly-ten-months', CommandContext::system('test:e3'));
        $new = $plan->refresh()->currentVersion();

        expect(collect($done)->where('kind', 'yearly')->whereNull('error')->count())->toBe(1)
            ->and($new->version)->toBeGreaterThan($version->version)
            ->and((int) Price::query()->where('plan_version_id', $new->id)->where('currency', 'CZK')->where('period', 'year')->where('state', 'active')->value('amount_minor'))->toBe($month->amount_minor * 10)
            ->and((int) $year->refresh()->amount_minor)->toBe($month->amount_minor * 12) // who holds the old version keeps its price
            ->and($revisions->pending('2026-10-yearly-ten-months'))->toBe([]);
    });

    it('never derives the yearly price of a product sold by the year', function () {
        $ssl = Product::query()->where('key', 'ssl')->firstOrFail();
        expect(data_get($ssl->meta, 'billing_unit'))->toBe('year');
        expect(array_keys(app(CatalogRevisions::class)->pending('2026-10-yearly-ten-months')['2026-10-yearly-ten-months']['yearly'] ?? []))
            ->each->not->toStartWith('ssl/');
    });
});
