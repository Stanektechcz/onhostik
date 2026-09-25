<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * Owner decision 20 (TASK-0021), review round 3: a renewal that runs by itself is paid from the organization's credit, so it
 * needs the consent of somebody who may spend that credit. The organization's auto-renew default set by a holder (or accepted
 * by the owner at sign-up) is that standing consent: only a holder switches it on, and what a non-holder orders — by card or
 * bank too — renews only under it, never under an auto_renew=true the non-holder asked for. Behind the credit-approval switch.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    config(['onhost.orders.credit_approval.enabled' => true]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0']);
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

function renewalConsentMember(Organization $org, string $role): User
{
    $member = User::factory()->create();
    app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), true);

    return $member;
}

function renewalConsentPatch(TestCase $test, User $as, Organization $org, array $data): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->patchJson("/v1/organizations/{$org->id}", $data, ['Idempotency-Key' => (string) Str::ulid()]);
}

/** A bank order for one domain, placed by the given person (nothing is paid from credit, so nothing is held). */
function renewalConsentDomainOrder(Organization $org, User $by, string $fqdn): Order
{
    $quote = app(QuoteService::class)->quote([['product_key' => 'domain', 'config' => ['fqdn' => $fqdn, 'period_years' => 1, 'registrant' => ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ']]]], 'CZK', ['country' => $org->country, 'customer_class' => $org->customer_class, 'vat_status' => $org->vat_status], 1, null, $org);
    $consents = ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Jana Nováková'], 'registry_terms_cz' => ['person' => 'Jana Nováková'], 'sla' => []];

    return app(CheckoutService::class)->placeOrder($quote, $org, $by, $consents, ['mode' => 'bank'], 'renewal-consent:'.Str::ulid(), new CommandContext('user', $by->id, $org->id))['order'];
}

it('refuses an org_admin switching the organization\'s auto-renew default on; switching it off and a holder switching it on stay open', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $admin = renewalConsentMember($org, 'org_admin');

    $refused = renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => true, 'name' => 'Jiný název'])->assertForbidden();
    expect($refused->json('error'))->toBe('credit_spend_not_allowed')->and($refused->json('message'))->toContain('automatického prodloužení')
        ->and((bool) $org->refresh()->auto_renew_default)->toBeFalse()->and($org->name)->toBe('Test s.r.o.'); // nothing of the request was applied
    renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => false, 'name' => 'Jiný název'])->assertOk(); // unchanged value: nothing new is committed
    expect($org->refresh()->name)->toBe('Jiný název');

    renewalConsentPatch($this, $owner, $org, ['auto_renew_default' => true])->assertOk(); // the owner holds the right (a billing admin cannot edit the organization at all)
    expect((bool) $org->refresh()->auto_renew_default)->toBeTrue();
    renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => true])->assertOk(); // already on: nothing new is committed
    renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => false])->assertOk(); // switching off commits nothing
    expect((bool) $org->refresh()->auto_renew_default)->toBeFalse();
    renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => true])->assertForbidden(); // and not on again
});

it('leaves the auto-renew default open to an org_admin while the switch is off', function () {
    config(['onhost.orders.credit_approval.enabled' => false]);
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $admin = renewalConsentMember($org, 'org_admin');

    renewalConsentPatch($this, $admin, $org, ['auto_renew_default' => true])->assertOk();
    expect((bool) $org->refresh()->auto_renew_default)->toBeTrue();
});

it('registers a domain an org_admin ordered by bank transfer under the organization default, not with a renewal nobody who may spend the credit agreed to', function () {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('clenska.cz');
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $admin = renewalConsentMember($org, 'org_admin');

    $order = renewalConsentDomainOrder($org, $admin, 'clenska.cz');
    expect(data_get($order->meta, 'renewal_consent'))->toBe('organization_default');
    $item = $order->items()->firstOrFail();
    app(DomainService::class)->createFromOrderItem($item, $order, CommandContext::system('order fulfilment'));

    $domain = Domain::query()->where('fqdn_ascii', 'clenska.cz')->firstOrFail();
    expect((bool) $domain->auto_renew)->toBeFalse();
});

it('keeps registering a domain the owner ordered with auto-renewal on, as before', function () {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('majitelova.cz');
    [$owner, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();

    $order = renewalConsentDomainOrder($org, $owner, 'majitelova.cz');
    expect(data_get($order->meta, 'renewal_consent'))->toBeNull();
    app(DomainService::class)->createFromOrderItem($order->items()->firstOrFail(), $order, CommandContext::system('order fulfilment'));

    expect((bool) Domain::query()->where('fqdn_ascii', 'majitelova.cz')->firstOrFail()->auto_renew)->toBeTrue();
});

it('ignores an explicit auto_renew=true of a member without the right to spend the credit when a domain is registered directly', function () {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    pdnsZoneFake('primo.cz');
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $manager = renewalConsentMember($org, 'domain_manager');
    $registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];

    app(DomainService::class)->register($org, 'primo.cz', ['period' => 1, 'registrant' => $registrant, 'auto_renew' => true, 'consent' => ['person' => 'Jana Nováková']], $this->contextFor($manager, $org), 'reg-primo-1');

    expect((bool) Domain::query()->where('fqdn_ascii', 'primo.cz')->firstOrFail()->auto_renew)->toBeFalse();
});

it('brings a domain a member without the right transfers in under the organization default', function () {
    $state = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($state);
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $manager = renewalConsentMember($org, 'domain_manager');
    $registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];

    try {
        app(DomainService::class)->transferIn($org, 'prevod.cz', 'AUTH-123', ['registrant' => $registrant, 'consent' => ['person' => 'Jana Nováková']], $this->contextFor($manager, $org, 'totp'), 'transfer-prevod-1');
    } catch (Throwable) {
        // the registry double does not know transfers; only the domain row the service wrote before the saga matters here
    }

    expect((bool) Domain::query()->where('fqdn_ascii', 'prevod.cz')->firstOrFail()->auto_renew)->toBeFalse();
});

it('keeps an order placed while the switch is off unmarked, so its domain renews as it always did', function () {
    config(['onhost.orders.credit_approval.enabled' => false]);
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $admin = renewalConsentMember($org, 'org_admin');

    expect(data_get(renewalConsentDomainOrder($org, $admin, 'vypnuto.cz')->meta, 'renewal_consent'))->toBeNull();
});

it('creates a service subscription under the organization default whoever ordered it', function () {
    [, $org] = $this->customerWithOrganization();
    $org->forceFill(['auto_renew_default' => false])->save();
    $service = featureWebService($org, 'ispconfig');

    $subscription = app(SubscriptionService::class)->ensureForService($service, null, CommandContext::system('provisioning'));
    expect((bool) $subscription->auto_renew)->toBeFalse();
});
