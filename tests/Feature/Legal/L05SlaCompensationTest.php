<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Carbon;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Incidents\Models\Maintenance;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;

/*
 * L-05 (docs/legal/LEGAL_REVIEW_2026-10.md): the SLA in force (2026-09, resources/legal/sla.md) promises per month:
 *   Standard 99.9 %, 5 % of the monthly price for every started hour over the limit, at most 50 %;
 *   Business 99.95 %, 10 % for every started hour over the limit, at most 100 %;
 * and planned maintenance counts out only when announced 48 hours ahead, at most 4 hours a month, outside working hours.
 * The code paid Standard nothing, measured Business against 99.9 % in bands capped at 50 %, judged each incident alone and took
 * any announced maintenance out whatever its length. These tests hold the code to the promise.
 *
 * November 2026 has 30 days (2 592 000 s): Business may be down 1 296 s, Standard 2 592 s.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    $this->travelTo(Carbon::parse('2026-11-25 12:00:00', 'UTC'));
});

function l05Service(Organization $org, string $class): Service
{
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'VPS '.$class.' '.uniqid(), 'hostname' => uniqid().'.onhost.cloud', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => $class, 'activated_at' => now()->subMonths(2)]);
    Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 100000, 'state' => 'active', 'current_period_start' => now()->startOfMonth(), 'current_period_end' => now()->startOfMonth()->addMonth(), 'next_renewal_at' => now()->startOfMonth()->addMonth()->subDays(7)]);

    return $service;
}

function l05Incident(Service $service, string $from, string $to): Incident
{
    return Incident::query()->create(['number' => 'INC-2026-'.random_int(1000, 9999), 'title' => 'Výpadek', 'severity' => 'p1', 'state' => 'RESOLVED', 'components' => ['cloud-cz1'], 'affected_services' => [$service->id],
        'started_at' => Carbon::parse($from, 'UTC'), 'detected_at' => Carbon::parse($from, 'UTC'), 'resolved_at' => Carbon::parse($to, 'UTC'), 'sla_relevant' => true]);
}

/** @return array{percent:int, amount:int} */
function l05Credit(Incident $incident): array
{
    $credit = app(SlaService::class)->creditCandidates($incident, CommandContext::system('test'))->first();

    return ['percent' => (int) ($credit->credit_percent ?? 0), 'amount' => (int) ($credit->amount_minor ?? 0)];
}

function l05Maintenance(string $from, string $to): Maintenance
{
    return Maintenance::query()->create(['number' => 'MNT-2026-'.random_int(1000, 9999), 'title' => 'Údržba', 'components' => ['cloud-cz1'], 'starts_at' => Carbon::parse($from, 'UTC'), 'ends_at' => Carbon::parse($to, 'UTC'),
        'sla_treatment' => 'excluded', 'state' => 'completed', 'announced_at' => Carbon::parse($from, 'UTC')->subDays(3)]);
}

it('pays Business 10 % for every started hour over 99.95 %', function () {
    [, $org] = $this->customerWithOrganization();
    $service = l05Service($org, 'business');

    // 2 h down, 21.6 min allowed: 98.4 min over = 2 started hours
    expect(l05Credit(l05Incident($service, '2026-11-03 10:00', '2026-11-03 12:00')))->toBe(['percent' => 20, 'amount' => 20000]);
});

it('pays Standard 5 % for every started hour over 99.9 % — Standard has a contractual SLA', function () {
    [, $org] = $this->customerWithOrganization();
    $service = l05Service($org, 'standard');

    // 3 h down, 43.2 min allowed: 136.8 min over = 3 started hours
    expect(config('onhost.sla.classes.standard.contractual'))->toBe(99.9)
        ->and(l05Credit(l05Incident($service, '2026-11-03 10:00', '2026-11-03 13:00')))->toBe(['percent' => 15, 'amount' => 15000]);
});

it('caps Business at 100 % and Standard at 50 % of the monthly price', function () {
    [, $org] = $this->customerWithOrganization();
    expect(l05Credit(l05Incident(l05Service($org, 'business'), '2026-11-04 00:00', '2026-11-04 15:00')))->toBe(['percent' => 100, 'amount' => 100000])
        ->and(l05Credit(l05Incident(l05Service($org, 'standard'), '2026-11-05 00:00', '2026-11-05 15:00')))->toBe(['percent' => 50, 'amount' => 50000]);
});

it('measures the month, not each incident alone: two short outages together go over the limit', function () {
    [, $org] = $this->customerWithOrganization();
    $service = l05Service($org, 'business');

    $first = l05Incident($service, '2026-11-06 10:00', '2026-11-06 10:20'); // 20 min: within the 21.6 min of the month
    expect(l05Credit($first))->toBe(['percent' => 0, 'amount' => 0]);
    $second = l05Incident($service, '2026-11-12 10:00', '2026-11-12 10:20'); // 40 min in the month: 18.4 min over = 1 started hour
    expect(l05Credit($second))->toBe(['percent' => 10, 'amount' => 10000]);
    // and asked again it gives nothing twice
    expect(app(SlaService::class)->creditCandidates($second, CommandContext::system('test'))->sum('amount_minor'))->toBe(10000);
});

it('takes announced maintenance out of the downtime for at most 4 hours a month and only outside working hours', function () {
    [, $org] = $this->customerWithOrganization();
    $service = l05Service($org, 'business');

    // Saturday 14 Nov, 01:00–07:00 Prague: six hours of announced maintenance, of which four count out → 2 h down → 20 %
    l05Maintenance('2026-11-14 00:00', '2026-11-14 06:00');
    expect(l05Credit(l05Incident($service, '2026-11-14 00:00', '2026-11-14 06:00')))->toBe(['percent' => 20, 'amount' => 20000]);

    // Tuesday 17 Nov, 09:00–11:00 Prague: maintenance in working hours counts as downtime (2 h more in the month)
    $other = l05Service($org, 'business');
    l05Maintenance('2026-11-17 08:00', '2026-11-17 10:00');
    expect(l05Credit(l05Incident($other, '2026-11-17 08:00', '2026-11-17 10:00')))->toBe(['percent' => 20, 'amount' => 20000]);
});
