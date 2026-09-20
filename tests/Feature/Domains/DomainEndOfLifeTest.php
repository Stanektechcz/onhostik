<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * A domain has an end. One that left the registrar's account — transferred to another registrar, or deleted by the registry
 * after it expired — stayed ACTIVE (or EXPIRED) here for ever: renewals kept being scheduled for it, the customer kept seeing a
 * domain they no longer have, and operations got the same "missing at the registrar" notice every single night. Nothing ever
 * set the state DELETED, and TRANSFERRED_OUT only when a registrar's poll queue happened to say so (Subreg has none).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    Http::preventStrayRequests();
});

it('closes a domain that left the registrar account — asked a second way, and only after it stayed away', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    // the registrar lists one of three domains; asked about the other two, it says it does not have them
    $state = ['registered' => false, 'nsset' => true, 'expiration' => now()->addDays(200)->toDateString(), 'listing' => [['name' => 'zustava.cz', 'status' => 'active', 'expiration' => now()->addDays(200)->toDateString()]]];
    registryFake($state);
    $stays = graceDomain($org, 'zustava.cz', DomainStateMachine::ACTIVE, now()->addDays(200));
    $moved = graceDomain($org, 'odesla.cz', DomainStateMachine::ACTIVE, now()->addDays(120));
    $lapsed = graceDomain($org, 'propadla.cz', DomainStateMachine::REDEMPTION, now()->subDays(70));
    $job = DomainRenewalJob::query()->create(['domain_id' => $moved->id, 'organization_id' => $org->id, 'state' => DomainRenewalJob::SCHEDULED, 'period_years' => 1, 'due_at' => now()->addDays(120), 'scheduled_for' => now()->addDays(90)]);
    $domains = app(DomainService::class);

    // the first night: noted and said once — one odd answer of a registrar closes nothing
    $domains->reconcile($ctx);
    expect($moved->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($moved->fresh()->meta['missing_since'] ?? null)->not->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    $notices = fn () => Notification::query()->where('audience', 'internal')->where('title', 'Doména chybí u registrátora: odesla.cz')->count();
    expect($notices())->toBe(1);

    // the next night it is still away, but not for long enough
    $this->travel(20)->hours();
    $domains->reconcile($ctx);
    app(OutboxPublisher::class)->relayPending();
    expect($moved->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($notices())->toBe(1); // it used to be said again every night, for ever

    // two days on: it is gone. A domain that had not expired went to another registrar; one long past its expiry was deleted by the registry
    $this->travel(30)->hours();
    $result = $domains->reconcile($ctx);
    expect($result['closed'])->toBe(2);
    expect($moved->fresh()->state)->toBe(DomainStateMachine::TRANSFERRED_OUT)->and($moved->fresh()->auto_renew)->toBeFalse()
        ->and($lapsed->fresh()->state)->toBe(DomainStateMachine::DELETED)->and($lapsed->fresh()->auto_renew)->toBeFalse()
        ->and($stays->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($stays->fresh()->auto_renew)->toBeTrue();
    expect($job->fresh()->state)->toBe(DomainRenewalJob::SKIPPED); // nothing is renewed (and paid for) that is not ours to renew
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('title', 'like', '%odesla.cz%')->count())->toBe(1)
        ->and(Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('title', 'like', '%propadla.cz%')->count())->toBe(1);

    // and it is over: closed domains are not asked about again
    $before = count($state['commands']);
    $domains->reconcile($ctx);
    expect(array_count_values(array_slice($state['commands'], $before))['domain-info'] ?? 0)->toBe(0);
});

it('does not close a domain the registrar knows after all, or one it cannot be asked about', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    // the listing is empty (an outage that answers with nothing), but asked about the domain itself the registrar has it
    $state = ['registered' => true, 'nsset' => true, 'expiration' => now()->addDays(150)->toDateString(), 'listing' => []];
    registryFake($state);
    $domain = graceDomain($org, 'porad-nase.cz', DomainStateMachine::ACTIVE, now()->addDays(100));
    $domains = app(DomainService::class);

    $domains->reconcile($ctx);
    $this->travel(3)->days();
    $result = $domains->reconcile($ctx);

    expect($result['closed'])->toBe(0)->and($domain->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->fresh()->auto_renew)->toBeTrue()
        ->and($domain->fresh()->meta['missing_since'] ?? null)->toBeNull()->and($domain->fresh()->expires_at->toDateString())->toBe($state['expiration']); // the registry's date was taken
});

it('takes a domain back when the registrar lists it again, and shows what waits in the doctor', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $state = ['registered' => false, 'nsset' => true, 'expiration' => now()->addDays(200)->toDateString(), 'listing' => []];
    registryFake($state);
    $domain = graceDomain($org, 'vratila-se.cz', DomainStateMachine::ACTIVE, now()->addDays(120));
    $domains = app(DomainService::class);
    $domains->reconcile($ctx);

    Artisan::call('onhost:doctor', ['--json' => true]);
    $check = collect(json_decode(trim(Artisan::output()), true)['checks'])->firstWhere('check', 'no domain is missing at its registrar');
    expect($check['status'])->toBe('WARN')->and($check['detail'])->toContain('vratila-se.cz');

    $this->travel(3)->days();
    $domains->reconcile($ctx);
    expect($domain->fresh()->state)->toBe(DomainStateMachine::TRANSFERRED_OUT);

    // the registrar lists it again (it was a fault on their side, or the customer moved it back): it is ours again, renewals and all
    $state['registered'] = true;
    $state['listing'] = [['name' => 'vratila-se.cz', 'status' => 'active', 'expiration' => now()->addDays(200)->toDateString()]];
    $domains->reconcile($ctx);
    expect($domain->fresh()->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->fresh()->meta['closed'] ?? null)->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'Doména je zpět u registrátora: vratila-se.cz')->count())->toBe(1);
});
