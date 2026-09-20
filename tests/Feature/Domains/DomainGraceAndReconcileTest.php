<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainRenewalScheduler;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A domain is the one thing a customer cannot get back. Two ways it was lost or misreported:
 *  · a registrar LISTING that carries no status (Subreg's does not) was read as "active": the nightly reconciliation revived
 *    a domain in redemption or on its way out to another registrar as ACTIVE;
 *  · a renewal that could not be paid was abandoned the day before expiry, and nothing ever tried again — although the
 *    registry still renews at the ordinary price for weeks after the expiry. A customer who topped up the next morning
 *    lost the domain anyway.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'options' => []]);
    Http::preventStrayRequests();
});

it('does not read a listing without a status as "active": a domain in redemption stays in redemption', function () {
    [$user, $org] = $this->customerWithOrganization();
    $state = ['registered' => true, 'nsset' => true, 'expiration' => now()->addDays(300)->toDateString(), 'info_status' => 'redemptionPeriod',
        'listing' => [['name' => 'karantena.cz', 'status' => '', 'expiration' => now()->addDays(300)->toDateString()], ['name' => 'zdrava.cz', 'status' => '', 'expiration' => now()->addDays(200)->toDateString()]]];
    registryFake($state);
    $quarantined = graceDomain($org, 'karantena.cz', DomainStateMachine::REDEMPTION, now()->subDays(35));
    $healthy = graceDomain($org, 'zdrava.cz', DomainStateMachine::ACTIVE, now()->addDays(100));

    app(DomainService::class)->reconcile($this->contextFor($user, $org));

    // it used to come back ACTIVE: the listing has no status, '' meant "active", and the date in the listing is in the future
    expect($quarantined->fresh()->state)->toBe(DomainStateMachine::REDEMPTION);
    // an ordinary domain takes its date from the listing and costs no extra call
    expect($healthy->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($healthy->fresh()->expires_at->toDateString())->toBe(now()->addDays(200)->toDateString());
    expect(array_count_values($state['commands'])['domain-info'] ?? 0)->toBe(1); // asked about the one domain the listing could not explain
});

it('keeps trying to renew through the protective period, and renews the morning the customer tops up', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $expires = now()->addDays(2);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => $expires->toDateString(), 'created' => now()->subYear()->toDateString(),
        'listing' => [['name' => 'pozde.cz', 'status' => 'active', 'expiration' => $expires->toDateString()]]];
    registryFake($state);
    $domain = graceDomain($org, 'pozde.cz', DomainStateMachine::ACTIVE, $expires);
    $scheduler = app(DomainRenewalScheduler::class);

    // no money: every day an attempt, up to the expiry
    foreach (range(1, 3) as $day) {
        $scheduler->tick($ctx);
        $this->travel(1)->days();
    }
    app(DomainService::class)->reconcile($ctx); // the day after the expiry
    expect($domain->fresh()->state)->toBe(DomainStateMachine::EXPIRED)->and($domain->fresh()->expires_at->isPast())->toBeTrue();
    expect(array_count_values($state['commands'])['domain-renew'] ?? 0)->toBe(0);

    // the customer tops up the morning after: the registry still renews at the ordinary price — and so do we
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'topup-grace', $ctx);
    app(OutboxPublisher::class)->relayPending();
    $scheduler->tick($ctx);

    expect(array_count_values($state['commands'])['domain-renew'] ?? 0)->toBe(1)
        ->and($domain->fresh()->expires_at->toDateString())->toBe($expires->copy()->addYear()->toDateString())->and($domain->fresh()->state)->toBe(DomainStateMachine::ACTIVE)
        ->and(DomainRenewalJob::query()->where('domain_id', $domain->id)->where('state', DomainRenewalJob::SUCCEEDED)->count())->toBe(1);
});

it('gives a domain up only when the protective period is over', function () {
    config(['onhost.domains.grace_retry_days' => 5]);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $expires = now()->subDays(7);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => $expires->toDateString(), 'listing' => []];
    registryFake($state);
    $domain = graceDomain($org, 'pryc.cz', DomainStateMachine::EXPIRED, $expires);

    $stats = app(DomainRenewalScheduler::class)->tick($ctx);

    expect($stats['scheduled'])->toBe(0)->and(DomainRenewalJob::query()->where('domain_id', $domain->id)->count())->toBe(0)->and($state['commands'] ?? [])->not->toContain('domain-renew');
});
