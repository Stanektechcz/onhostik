<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/*
 * A premium name costs at the registry what the registry says — hundreds or thousands of euros — and the registrar takes
 * it from OUR credit the moment the create is accepted. The search offered such a name at the ordinary price of its TLD,
 * and the registration sent the create without looking: one order of "pay.cz for 199 Kč" could empty the registrar account.
 * A premium name is not sold by the shop, not registered by the saga, and not renewed by the scheduler at list price.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['SUBREG_MAIN_LOGIN'] = 'onhost_api';
    $_ENV['SUBREG_MAIN_PASSWORD'] = 'subreg-secret';
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    ProviderInstance::query()->firstOrCreate(['key' => 'subreg-main'], ['provider' => 'subreg', 'name' => 'Subreg', 'base_url' => 'https://demoreg.net', 'secret_ref' => 'env://SUBREG_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'options' => ['test_mode' => true]]);
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'capabilities' => ['dns' => true], 'options' => []]);
    RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => 'subreg', 'tld' => 'cz'], ['currency' => 'CZK', 'register_minor' => 14000, 'renew_minor' => 14000, 'transfer_minor' => 14000, 'source' => 'manual', 'fetched_at' => now()]);
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    config()->set('onhost.domains.registrar.preference', ['subreg']);
    cache()->forget('onhost:subreg:ssid:subreg-main');
    Http::preventStrayRequests();
});

it('does not offer a premium name at the ordinary price of its TLD', function () {
    $subreg = ['premium' => true, 'prices' => ['cz' => ['register' => '48000.00']]];
    subregRegistryFake($subreg);

    $result = app(DomainService::class)->search(['pay.cz'], 'CZK')[0];

    // it used to be: available, 'free', at the list price
    expect($result['available'])->toBeFalse()->and($result['reason'])->toBe('premium')->and($result['premium'] ?? null)->toBeTrue();
});

it('does not send a create for a premium name even when an order for it was paid', function () {
    $subreg = ['premium' => true];
    subregRegistryFake($subreg);
    pdnsZoneFake('pay.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'phone' => '+420777123456', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];

    $operation = app(DomainService::class)->register($org, 'pay.cz', ['period' => 1, 'registrant' => $registrant, 'dns_template' => 'parking', 'consent' => ['person' => 'Jana Nováková', 'document_version' => '2026-01', 'ip' => '10.0.0.1']], $ctx, 'reg-premium-1');
    $operation = driveOperation($operation);

    expect($operation->state)->toBeIn([Operation::FAILED, Operation::COMPENSATED])->and((string) ($operation->error['message'] ?? ''))->toContain('premium');
    expect(collect(subregParams('Make_Order'))->where('order.type', 'Create_Domain')->count())->toBe(0); // the registrar was never asked to create it
    expect(Domain::query()->where('fqdn_ascii', 'pay.cz')->value('state'))->toBe(DomainStateMachine::FAILED);
});

it('does not renew a premium name at the list price', function () {
    $subreg = ['registered' => true, 'premium' => true, 'expiration' => now()->addDays(20)->toDateString()];
    subregRegistryFake($subreg);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::minor(100000, 'CZK'), 'bank', 'topup-premium', $ctx);
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'registrar_provider' => 'subreg', 'kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'G-000001']);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'pay.cz', 'fqdn_unicode' => 'pay.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'subreg', 'registered_at' => now()->subYear(),
        'expires_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);

    $operation = driveOperation(app(DomainService::class)->renew($domain, 1, $ctx, 'renew-premium-1'));

    expect($operation->state)->toBeIn([Operation::FAILED, Operation::COMPENSATED])->and((string) ($operation->error['message'] ?? ''))->toContain('premium');
    expect(collect(subregParams('Make_Order'))->where('order.type', 'Renew_Domain')->count())->toBe(0)
        ->and(app(WalletService::class)->balances($org, 'CZK')['available']->minor)->toBe(100000); // nothing held, nothing taken
});
