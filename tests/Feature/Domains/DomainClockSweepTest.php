<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainRenewalScheduler;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Outbox\OutboxMessage;

require_once __DIR__.'/../../Support/ClockSweep.php';

/*
 * TASK-0047 review: the days until a domain expires. A registrar gives the expiry as a DATE (WEDOS `expiration`, Subreg
 * `exDate` cut to Y-m-d), stored as midnight of that date; the panel prints that date and "expiruje za N dní". N was counted
 * from the UTC day, so from midnight to 01:00/02:00 in Prague the panel, the API and the notices said one day more than the
 * calendar next to it, and the seven-day reminder waited for 02:00. N is now counted from the accounting day (Prague) to the
 * printed date. Whether a renewal is retried or given up, and whether the customer is told the domain has already expired,
 * still go by the registry's expiry instant — pinned here at every hour.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'options' => []]);
    Http::preventStrayRequests();
});

/** A domain whose registry expiry date is `$days` accounting days from today, stored as the registrar adapters store it. */
function domainSweepExpiring(Organization $org, int $days): Domain
{
    $date = CarbonImmutable::parse(AccountingClock::date())->addDays($days)->toDateString();

    return graceDomain($org, 'sweep-'.$days.'-'.random_int(1000, 9999).'.cz', DomainStateMachine::ACTIVE, new DateTimeImmutable($date));
}

dataset('domainSweepDays', function () {
    return [
        'UTC, tomorrow' => ['UTC', null],
        'Prague, winter' => ['Europe/Prague', clockSweepNextDay('01-15', 'Europe/Prague')],
        'Prague, the 23-hour day' => ['Europe/Prague', clockSweepNextLastSunday(3)],
        'Prague, the 25-hour day' => ['Europe/Prague', clockSweepNextLastSunday(10)],
    ];
});

it('counts the days to a domain\'s expiry in the calendar the panel prints its date in, at every hour', function (string $tz, ?string $day) {
    $failures = clockSweep(function () {
        [, $org] = $this->customerWithOrganization();
        foreach ([0 => 'RENEW_DUE_7', 1 => 'RENEW_DUE_7', 7 => 'RENEW_DUE_7', 8 => 'RENEW_DUE_30', 30 => 'RENEW_DUE_30', 31 => 'RENEW_DUE_60'] as $days => $bucket) {
            $domain = domainSweepExpiring($org, $days);
            expect($domain->daysToExpiry())->toBe($days, "expires {$domain->expires_at->toDateString()}, today ".AccountingClock::date())
                ->and($domain->renewalBucket())->toBe($bucket);
        }
    }, 15, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures));
})->with('domainSweepDays');

it('sends the seven-day renewal notice on the seventh day before the printed expiry, at every hour the job runs', function (string $tz, ?string $day) {
    $failures = clockSweep(function () {
        [, $org] = $this->customerWithOrganization();
        $seven = domainSweepExpiring($org, 7);
        $eight = domainSweepExpiring($org, 8);
        $scheduler = app(DomainRenewalScheduler::class);
        $scheduler->schedule();
        $scheduler->sendNotices();
        $notice = fn (Domain $d) => (array) OutboxMessage::query()->where('name', 'domain.renewal_notice')->where('aggregate_id', $d->id)->sole()->payload;

        expect(array_intersect_key($notice($seven), ['days' => 0, 'days_left' => 0]))->toBe(['days' => 7, 'days_left' => 7], 'the seven-day notice')
            ->and(array_intersect_key($notice($eight), ['days' => 0, 'days_left' => 0]))->toBe(['days' => 14, 'days_left' => 8], 'eight days ahead');
    }, 60, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures, 60));
})->with([
    'UTC, tomorrow' => ['UTC', null],
    'Prague, winter' => ['Europe/Prague', clockSweepNextDay('01-15', 'Europe/Prague')],
]);

it('retries a renewal that cannot be paid through the expiry day, and says "expired" only once the registry expiry has passed', function (string $tz, ?string $day) {
    $failures = clockSweep(function () {
        [$user, $org] = $this->customerWithOrganization();
        $context = $this->contextFor($user, $org);
        $domain = domainSweepExpiring($org, 0); // the printed expiry day is today; no credit
        $state = ['registered' => true, 'nsset' => true, 'expiration' => $domain->expires_at->toDateString(), 'created' => now()->subYear()->toDateString(), 'listing' => []];
        registryFake($state);
        $scheduler = app(DomainRenewalScheduler::class);
        $scheduler->schedule();
        [$started, $retried, $failed] = $scheduler->execute($context);
        $payload = (array) OutboxMessage::query()->where('name', 'domain.renewal_payment_failed')->where('aggregate_id', $domain->id)->sole()->payload;

        expect([$started, $retried, $failed])->toBe([0, 1, 0], 'not retried')
            ->and(DomainRenewalJob::query()->where('domain_id', $domain->id)->sole()->state)->toBe(DomainRenewalJob::SCHEDULED)
            ->and($payload['in_grace'])->toBe(! $domain->expires_at->isFuture(), 'told "already expired" against the registry instant '.$domain->expires_at->toIso8601String())
            ->and($payload['days_left'])->toBe(0);
    }, 60, $tz, $day);

    expect($failures)->toBe([], clockSweepWindows($failures, 60));
})->with([
    'UTC, tomorrow' => ['UTC', null],
    'Prague, winter' => ['Europe/Prague', clockSweepNextDay('01-15', 'Europe/Prague')],
]);
