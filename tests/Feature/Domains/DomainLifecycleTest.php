<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainRenewalScheduler;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\DomainConsent;
use Onhost\Domain\Domains\Models\DomainRenewalJob;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarNotification;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\RegistrarCreditMonitor;
use Onhost\Domain\Domains\RegistrarPollWorker;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0']);
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

$registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
$consent = ['person' => 'Jana Nováková', 'document_version' => '2026-01', 'ip' => '10.0.0.1'];

it('registers a domain end-to-end: contacts, NSSET, canonical zone, domain-create, activation and subscription', function () use ($registrant, $consent) {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('example.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $service = app(DomainService::class);

    $operation = $service->register($org, 'Example.cz', ['period' => 1, 'registrant' => $registrant, 'dns_template' => 'parking', 'consent' => $consent], $ctx, 'reg-example-1');
    $operation->refresh();
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->kind)->toBe('domain.register')->and($operation->step)->toBe(5);

    $domain = Domain::query()->where('fqdn_ascii', 'example.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->expires_at->toDateString())->toBe('2027-09-06')->and($domain->nsset_id)->not->toBeNull()->and($domain->dns_zone_id)->not->toBeNull()->and($domain->nameservers)->toBe(['ns1.onhost.cz', 'ns2.onhost.cz']);
    expect(RegistrarContact::query()->find($domain->registrant_contact_id)->state)->toBe('synced');
    expect(RegistrarOperation::query()->where('domain_id', $domain->id)->pluck('state', 'command')->all())->toMatchArray(['contact-create' => 'SUCCEEDED', 'nsset-create' => 'SUCCEEDED', 'domain-create' => 'SUCCEEDED']);
    expect(DomainConsent::query()->where('domain_id', $domain->id)->count())->toBe(1);
    expect(DnsZone::query()->find($domain->dns_zone_id)->records()->count())->toBe(4);

    $subscription = Subscription::query()->where('domain_id', $domain->id)->firstOrFail();
    expect($subscription->renewal_priority)->toBe('domain')->and($subscription->period)->toBe('year')->and($subscription->next_renewal_at->toDateString())->toBe('2027-08-23')->and($subscription->amount_minor)->toBe(17900);
    expect(AuditEvent::query()->where('action', 'domain.registered')->exists())->toBeTrue();
    expect(array_count_values(wapiCommands())['domain-create'])->toBe(1);
    Http::assertSent(function (Request $r) {
        if (! $r->isForm() || ! isset($r->data()['request'])) {
            return false;
        }
        $p = json_decode((string) $r['request'], true)['request'];

        return $p['command'] === 'domain-create' && $p['data']['nsset'] === 'NSSET-ONHOST' && $p['data']['rules']['person'] === 'Jana Nováková' && $p['data']['period'] === 1;
    });

    expect($service->register($org, 'example.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $ctx, 'reg-example-1')->id)->toBe($operation->id);
    expect(fn () => $service->register($org, 'example.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $ctx, 'reg-example-2'))->toThrow(DomainError::class, 'already active');
});

it('waits on an async registry (1001) and finishes when domain-info turns active without resending create', function () use ($registrant, $consent) {
    $state = ['registered' => false, 'nsset' => true, 'expiration' => '2027-09-06', 'async' => true];
    registryFake($state);
    pdnsZoneFake('async.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);

    $operation = app(DomainService::class)->register($org, 'async.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $ctx, 'reg-async-1');
    $operation->refresh();
    expect($operation->state)->toBe(Operation::WAITING)->and($operation->external_handle['kind'])->toBe('wapi_async');
    expect(Domain::query()->where('fqdn_ascii', 'async.cz')->value('state'))->toBe(DomainStateMachine::PENDING_REGISTRY);
    expect(RegistrarOperation::query()->where('command', 'domain-create')->value('state'))->toBe(RegistrarOperation::PENDING_REGISTRY);

    $this->travel(16)->minutes();
    app(OperationService::class)->dispatchDue();
    expect($operation->fresh()->state)->toBe(Operation::WAITING); // first domain-info still pending
    $this->travel(16)->minutes();
    app(OperationService::class)->dispatchDue();
    expect($operation->fresh()->state)->toBe(Operation::SUCCEEDED);
    expect(Domain::query()->where('fqdn_ascii', 'async.cz')->value('state'))->toBe(DomainStateMachine::ACTIVE);
    expect(RegistrarOperation::query()->where('command', 'domain-create')->value('state'))->toBe(RegistrarOperation::SUCCEEDED);
    expect(array_count_values(wapiCommands())['domain-create'])->toBe(1);
});

it('schedules renewal notices, holds wallet money with domain priority and settles only after the registry confirms', function () {
    $expires = now()->addDays(10);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => $expires->toDateString(), 'created' => now()->subYear()->addDays(10)->toDateString()];
    registryFake($state);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'topup-dom', $ctx);
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-X']);
    $domain = Domain::query()->create([
        'organization_id' => $org->id, 'fqdn_ascii' => 'renew.cz', 'fqdn_unicode' => 'renew.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registered_at' => now()->subYear()->addDays(10), 'expires_at' => $expires,
        'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id,
    ]);
    $price = app(DomainService::class)->renewalPrice($domain, 1, Organization::query()->findOrFail($org->id));
    expect($price['net']->minor)->toBe(17900)->and($price['gross']->minor)->toBe(17900 + $price['tax']->minor)->and($price['tax']->isPositive())->toBeTrue();

    $stats = app(DomainRenewalScheduler::class)->tick($ctx);
    expect($stats)->toMatchArray(['scheduled' => 1, 'notices' => 1, 'started' => 1, 'retried' => 0, 'failed' => 0]);

    $job = DomainRenewalJob::query()->where('domain_id', $domain->id)->firstOrFail();
    expect($job->state)->toBe(DomainRenewalJob::SUCCEEDED)->and($job->notices_sent)->toBe([14, 30, 60]);
    $domain->refresh();
    expect($domain->expires_at->toDateString())->toBe($expires->copy()->addYear()->toDateString())->and($domain->state)->toBe(DomainStateMachine::ACTIVE);
    $hold = WalletHold::query()->where('reference_type', 'domain')->where('reference_id', $domain->id)->firstOrFail();
    expect($hold->state)->toBe('captured')->and($hold->priority)->toBe('domain')->and($hold->purpose)->toBe('domain_renewal')->and($hold->amount_minor)->toBe($price['gross']->minor);
    $statement = Invoice::query()->where('type', 'statement')->where('meta->domain_id', $domain->id)->firstOrFail();
    expect($statement->total_minor)->toBe($price['gross']->minor)->and($statement->state)->toBe(Invoice::PAID);
    expect(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(200000 - $price['gross']->minor);
    expect(wapiCommands())->toBe(['domain-info', 'domain-renew', 'domain-info']);

    expect(app(DomainRenewalScheduler::class)->tick($ctx))->toMatchArray(['scheduled' => 0, 'started' => 0]); // nothing left to do
});

it('enforces step-up for AUTH-ID requests and two-person approval on critical domains', function () {
    $state = ['registered' => true, 'nsset' => true, 'expiration' => '2027-09-06'];
    registryFake($state);
    [$user, $org] = $this->customerWithOrganization();
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Ops', 'email' => 'ops@onhost.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-OPS']);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'onhost.cz', 'fqdn_unicode' => 'onhost.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'expires_at' => now()->addYear(), 'critical' => true, 'transfer_lock' => false, 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);
    $service = app(DomainService::class);

    expect(fn () => $service->requestAuthInfo($domain, $this->contextFor($user, $org)))->toThrow(DomainError::class, 'step-up');
    expect(fn () => $service->requestAuthInfo($domain, $this->contextFor($user, $org, 'webauthn')))->toThrow(DomainError::class, 'two-person');
    expect(fn () => $service->requestAuthInfo($domain, CommandContext::ai('run_1', $org->id)))->toThrow(DomainError::class, 'AI actors');
    Http::assertNothingSent();

    $approved = new CommandContext('user', $user->id, $org->id, stepUpMethod: 'webauthn', approvalIds: ['apr_second_person']);
    $service->requestAuthInfo($domain, $approved);
    expect(wapiCommands())->toBe(['domain-send-auth-info']);
    expect(RegistrarOperation::query()->where('command', 'domain-send-auth-info')->value('state'))->toBe(RegistrarOperation::SUCCEEDED);
    $audit = AuditEvent::query()->where('action', 'domain.auth_info_request')->firstOrFail();
    expect(json_encode($audit->getAttributes()))->toContain('webauthn')->toContain('apr_second_person');
});

it('reconciles with the registrar listing, samples credit runway and consumes the notification queue idempotently', function () {
    $state = [
        'registered' => true, 'nsset' => true, 'expiration' => now()->addDays(400)->toDateString(), 'credit' => '25000.00',
        'listing' => [['name' => 'renew.cz', 'status' => 'active', 'expiration' => now()->addDays(400)->toDateString()], ['name' => 'ghost.cz', 'status' => 'active', 'expiration' => '2027-01-01']],
        'queue' => [['id' => 'n-1', 'type' => 'domain_transfer_out', 'name' => 'renew.cz']],
    ];
    registryFake($state);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Jana', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-X']);
    $base = ['organization_id' => $org->id, 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id, 'dns_provider' => 'external'];
    $renew = Domain::query()->create($base + ['fqdn_ascii' => 'renew.cz', 'fqdn_unicode' => 'renew.cz', 'expires_at' => now()->addDays(10)]);
    $gone = Domain::query()->create($base + ['fqdn_ascii' => 'gone.cz', 'fqdn_unicode' => 'gone.cz', 'expires_at' => now()->subDays(2)]);

    $report = app(DomainService::class)->reconcile($ctx);
    expect($report['checked'])->toBe(2)->and($report['updated'])->toBe(1)->and($report['missing_remote'])->toBe(['gone.cz'])->and($report['unknown_remote'])->toBe(['ghost.cz'])->and($report['expired'])->toBe(1);
    expect($renew->fresh()->expires_at->toDateString())->toBe(now()->addDays(400)->toDateString())->and($gone->fresh()->state)->toBe(DomainStateMachine::EXPIRED);

    $snapshot = app(RegistrarCreditMonitor::class)->sample();
    expect($snapshot->balance_minor)->toBe(2500000)->and($snapshot->currency)->toBe('CZK')->and($snapshot->below_minimum)->toBeFalse()->and($snapshot->renewals_30d_count)->toBe(0);

    $stats = app(RegistrarPollWorker::class)->drain(10, $ctx);
    expect($stats)->toMatchArray(['received' => 1, 'processed' => 1, 'acked' => 1, 'dead' => 0]);
    expect($renew->fresh()->state)->toBe(DomainStateMachine::TRANSFERRED_OUT);
    expect(RegistrarNotification::query()->where('remote_id', 'wedos:n-1')->value('state'))->toBe('acked');

    $state['queue'] = [['id' => 'n-1', 'type' => 'domain_transfer_out', 'name' => 'renew.cz']]; // lost ack => redelivery
    $again = app(RegistrarPollWorker::class)->drain(10, $ctx);
    expect($again)->toMatchArray(['received' => 1, 'processed' => 0, 'acked' => 1]);
    expect(RegistrarNotification::query()->count())->toBe(1);
});

/*
 * A renewal the registry refuses (Brain card H23). A domain that silently fails to renew is a domain that expires: the
 * money goes back at once, the customer and operations both hear about it the same day, every later pass tries again
 * while the expiry comes closer — and when the registry accepts, the domain is extended and paid for exactly once.
 */
it('frees the money, tells the customer and operations, and keeps trying when the registry refuses a renewal', function () {
    $expires = now()->addDays(10);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => $expires->toDateString(), 'created' => now()->subYear()->addDays(10)->toDateString(), 'renew_refused' => true];
    registryFake($state);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'topup-h23', $ctx);
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-X']);
    $domain = Domain::query()->create([
        'organization_id' => $org->id, 'fqdn_ascii' => 'odmitnuta.cz', 'fqdn_unicode' => 'odmitnuta.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registered_at' => now()->subYear()->addDays(10), 'expires_at' => $expires,
        'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id,
    ]);
    $scheduler = app(DomainRenewalScheduler::class);

    // day one: refused
    $scheduler->tick($ctx);
    $job = DomainRenewalJob::query()->where('domain_id', $domain->id)->firstOrFail();
    expect($job->state)->toBe(DomainRenewalJob::FAILED)->and((string) $job->last_error)->not->toBe('')
        ->and($domain->fresh()->expires_at->toDateString())->toBe($expires->toDateString())           // nothing was extended
        ->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(200000)                 // and nothing is held or taken
        ->and(Invoice::query()->where('meta->domain_id', $domain->id)->count())->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    $notes = Notification::query()->where('title', 'like', '%odmitnuta.cz%')->get();
    expect($notes->where('organization_id', $org->id)->where('title', 'Prodloužení domény odmitnuta.cz se nezdařilo')->count())->toBe(1) // the customer knows …
        ->and($notes->where('audience', 'internal')->where('title', 'Prodloužení odmitnuta.cz selhalo')->count())->toBe(1); // … and so do operations, the same day
    // the advice fits the cause: the registry refused, so the customer is not told to top up
    expect((string) $notes->where('organization_id', $org->id)->where('title', 'Prodloužení domény odmitnuta.cz se nezdařilo')->first()->body)->toContain('Registr prodloužení nepřijal')->not->toContain('Dobijte kredit');

    // day two: a new attempt by itself, still refused, said again — the expiry is one day closer
    $this->travel(1)->days();
    $scheduler->tick($ctx);
    expect(DomainRenewalJob::query()->where('domain_id', $domain->id)->where('state', DomainRenewalJob::FAILED)->count())->toBe(2);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'like', 'Prodloužení odmitnuta.cz selhalo%')->count())->toBe(2);

    // day three: the registry accepts — extended once, paid once
    $state['renew_refused'] = false;
    $this->travel(1)->days();
    $scheduler->tick($ctx);
    expect($domain->fresh()->expires_at->toDateString())->toBe($expires->copy()->addYear()->toDateString())
        ->and(DomainRenewalJob::query()->where('domain_id', $domain->id)->where('state', DomainRenewalJob::SUCCEEDED)->count())->toBe(1)
        ->and(WalletHold::query()->where('reference_type', 'domain')->where('reference_id', $domain->id)->where('state', 'captured')->count())->toBe(1)
        ->and(Invoice::query()->where('type', 'statement')->where('meta->domain_id', $domain->id)->count())->toBe(1);
    expect(array_count_values($state['commands'])['domain-renew'])->toBe(3);
});

it('renews a domain once when the customer and the scheduler get there together, and reserves nothing when the registrar cannot be reached', function () {
    $expires = now()->addDays(10);
    $state = ['registered' => true, 'nsset' => true, 'expiration' => $expires->toDateString(), 'created' => now()->subYear()->toDateString()];
    registryFake($state);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::decimal('2000', 'CZK'), 'bank', 'topup-once', $ctx);
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-Y']);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'jednou.cz', 'fqdn_unicode' => 'jednou.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registered_at' => now()->subYear(), 'expires_at' => $expires, 'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);
    $domains = app(DomainService::class);

    // the registrar's instance is switched off: nothing is reserved, and the scheduler's job goes back into the queue
    // (it used to stay in HOLD_PLACED — a state nothing picks up again — with the money held for a week)
    ProviderInstance::query()->where('key', 'wedos-main')->update(['state' => 'disabled']);
    Queue::fake();
    $stats = app(DomainRenewalScheduler::class)->tick($ctx);
    $job = DomainRenewalJob::query()->where('domain_id', $domain->id)->firstOrFail();
    expect($stats['started'])->toBe(0)->and($stats['retried'])->toBe(1)->and($job->state)->toBe(DomainRenewalJob::SCHEDULED)->and($job->attempts)->toBe(1)->and($job->wallet_hold_id)->toBeNull();
    expect(WalletHold::query()->where('reference_id', $domain->id)->count())->toBe(0)->and(app(WalletService::class)->balances($org, 'CZK')['reserved']->minor)->toBe(0);

    // the registrar is back; the customer clicks „Prodloužit“ in the hour the scheduler runs
    ProviderInstance::query()->where('key', 'wedos-main')->update(['state' => 'active']);
    $first = $domains->renew($domain, 1, $ctx, 'domain.renew:customer-click');
    expect(fn () => $domains->renew($domain, 1, $ctx, 'domain.renew:second-click'))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('domain_renewal_in_progress')->and($e->status)->toBe(409));
    $job->forceFill(['scheduled_for' => now()->subMinute()])->save();
    [$started] = app(DomainRenewalScheduler::class)->execute($ctx);
    expect($started)->toBe(0)->and($job->fresh()->state)->toBe(DomainRenewalJob::SCHEDULED)->and((string) $job->fresh()->last_error)->toContain('already in progress');
    expect(WalletHold::query()->where('reference_id', $domain->id)->where('state', 'active')->count())->toBe(1)->and(Operation::query()->where('domain_id', $domain->id)->count())->toBe(1);
    expect($domains->renew($domain, 1, $ctx, 'domain.renew:customer-click')->id)->toBe($first->id); // the same request again is the same renewal
});
