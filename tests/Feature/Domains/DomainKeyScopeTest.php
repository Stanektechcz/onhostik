<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;

/*
 * Red-team round on the integrated Phase-0 chain (IF-12 / audit SE-5 for domains, TASK-0036 R1-3): DomainService looked its
 * operations up by the caller's raw key across the whole platform. Organization B sending organization A's Idempotency-Key got
 * A's operation back — A's domain name, A's registrar run — and its own registration silently never happened. A person's key
 * is now theirs for one organization, domain (or name) and operation, with the request's fingerprint, exactly as a service
 * action's (OperationKey, shared with ServiceService).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    Http::preventStrayRequests();
    Queue::fake(); // what the registrar does with it is not the subject: which operation answers the key is
});

/** An active domain of the organization, registered at WEDOS. */
function domainKeyScopeDomain(Organization $org, string $fqdn): Domain
{
    return Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => $fqdn, 'tld' => 'cz', 'registrar_provider' => 'wedos', 'state' => DomainStateMachine::ACTIVE, 'renewal_period' => 1, 'auto_renew' => true, 'dns_provider' => 'external', 'expires_at' => now()->addMonths(6)]);
}

$registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
$consent = ['person' => 'Jana Nováková', 'document_version' => '2026-01', 'ip' => '10.0.0.1'];

it('gives another organization its own registration for the same Idempotency-Key, and refuses the key for another name', function () use ($registrant, $consent) {
    [$alice, $a] = $this->customerWithOrganization();
    [$bob, $b] = $this->customerWithOrganization();
    $domains = app(DomainService::class);

    $first = $domains->register($a, 'alice-shop.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $this->contextFor($alice, $a), 'domain.register:shared-key');
    $second = $domains->register($b, 'bob-shop.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $this->contextFor($bob, $b), 'domain.register:shared-key');

    expect($second->id)->not->toBe($first->id)
        ->and($second->organization_id)->toBe($b->id)
        ->and(Domain::query()->where('fqdn_ascii', 'bob-shop.cz')->value('organization_id'))->toBe($b->id)
        ->and($second->desired['fqdn'])->toBe('bob-shop.cz');

    // a true retry is still the same operation; the same key for another name is a mistake, not a replay
    expect($domains->register($a, 'alice-shop.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $this->contextFor($alice, $a), 'domain.register:shared-key')->id)->toBe($first->id);
    expect(fn () => $domains->register($a, 'alice-other.cz', ['period' => 1, 'registrant' => $registrant, 'consent' => $consent], $this->contextFor($alice, $a), 'domain.register:shared-key'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('idempotency_key_reused'));
    expect(Domain::query()->where('fqdn_ascii', 'alice-other.cz')->exists())->toBeFalse();
});

it('gives each organization its own nameserver change for the same key', function () {
    [$alice, $a] = $this->customerWithOrganization();
    [$bob, $b] = $this->customerWithOrganization();
    $mine = domainKeyScopeDomain($a, 'alice-web.cz');
    $theirs = domainKeyScopeDomain($b, 'bob-web.cz');
    $domains = app(DomainService::class);

    $first = $domains->updateNameservers($mine, ['ns1.alice.cz', 'ns2.alice.cz'], $this->contextFor($alice, $a), 'domain.nameservers:k1');
    $second = $domains->updateNameservers($theirs, ['ns1.bob.cz', 'ns2.bob.cz'], $this->contextFor($bob, $b), 'domain.nameservers:k1');

    expect($second->id)->not->toBe($first->id)->and($second->domain_id)->toBe($theirs->id)->and($second->desired['nameservers'])->toBe(['ns1.bob.cz', 'ns2.bob.cz'])
        ->and(Operation::query()->where('domain_id', $theirs->id)->count())->toBe(1);
});
