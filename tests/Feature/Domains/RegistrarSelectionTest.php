<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\DnsTemplateSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarNotification;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Domains\RegistrarCreditMonitor;
use Onhost\Domain\Domains\RegistrarPollWorker;
use Onhost\Domain\Domains\RegistrarSelector;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class, DnsTemplateSeeder::class]);
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    $_ENV['SUBREG_MAIN_LOGIN'] = 'onhost_api';
    $_ENV['SUBREG_MAIN_PASSWORD'] = 'subreg-secret';
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    ProviderInstance::query()->firstOrCreate(['key' => 'subreg-main'], ['provider' => 'subreg', 'name' => 'Subreg', 'base_url' => 'https://demoreg.net', 'secret_ref' => 'env://SUBREG_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0', 'options' => ['demo' => true]]);
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'capabilities' => ['dns' => true], 'adapter_version' => '1.0.0']);
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    cache()->forget('onhost:subreg:ssid:subreg-main');
    Http::preventStrayRequests();
});

function registrarCost(string $provider, string $tld, int $register, string $currency = 'CZK', string $source = 'manual'): RegistrarTldCost
{
    return RegistrarTldCost::query()->updateOrCreate(['registrar_provider' => $provider, 'tld' => $tld], ['currency' => $currency, 'register_minor' => $register, 'renew_minor' => $register, 'transfer_minor' => $register, 'source' => $source, 'fetched_at' => now()]);
}

$registrant = ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'phone' => '+420777123456', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
$consent = ['person' => 'Jana Nováková', 'document_version' => '2026-01', 'ip' => '10.0.0.1'];

it('registers .cz with the cheaper registrar: 165 Kč at the first registrar vs 140 Kč at Subreg → Subreg', function () use ($registrant, $consent) {
    registrarCost('wedos', 'cz', 16500);
    registrarCost('subreg', 'cz', 14000);
    $wapi = ['registered' => false, 'nsset' => false, 'expiration' => '2027-09-06'];
    registryFake($wapi);
    $subreg = [];
    subregRegistryFake($subreg);
    pdnsZoneFake('testujeme.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);

    $choice = app(RegistrarSelector::class)->choose('cz', 'register', true);
    expect($choice['provider'])->toBe('subreg')->and($choice['reason'])->toBe('cheapest')->and($choice['cost']['czk_minor'])->toBe(14000)
        ->and(collect($choice['candidates'])->pluck('cost.czk_minor', 'provider')->all())->toBe(['wedos' => 16500, 'subreg' => 14000]);

    $operation = app(DomainService::class)->register($org, 'testujeme.cz', ['period' => 1, 'registrant' => $registrant, 'dns_template' => 'parking', 'consent' => $consent], $ctx, 'reg-cheapest-1');
    $operation->refresh();
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->provider_instance_id)->toBe(ProviderInstance::query()->where('key', 'subreg-main')->value('id'));

    $domain = Domain::query()->where('fqdn_ascii', 'testujeme.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->registrar_provider)->toBe('subreg')->and($domain->expires_at->toDateString())->toBe('2027-09-06')
        ->and($domain->meta['registrar_selection']['provider'])->toBe('subreg')->and($domain->meta['registrar_selection']['cost']['czk_minor'])->toBe(14000)->and($domain->meta['registrar_selection']['reason'])->toBe('cheapest');
    $contact = RegistrarContact::query()->findOrFail($domain->registrant_contact_id);
    expect($contact->registrar_provider)->toBe('subreg')->and($contact->remote_id)->toBe('G-000001')->and($contact->state)->toBe('synced');
    expect(RegistrarOperation::query()->where('domain_id', $domain->id)->pluck('registrar_provider')->unique()->all())->toBe(['subreg']);
    expect(RegistrarOperation::query()->where('domain_id', $domain->id)->pluck('state', 'command')->all())->toMatchArray(['contact-create' => 'SUCCEEDED', 'nsset-create' => 'SUCCEEDED', 'domain-create' => 'SUCCEEDED']);
    expect(wapiCommands())->toBe([])->and(array_count_values(subregCalls()))->toMatchArray(['Make_Order' => 2, 'Create_Contact' => 1]);
    $create = collect(subregParams('Make_Order'))->firstWhere('order.type', 'Create_Domain');
    expect($create['order']['domain'])->toBe('testujeme.cz')->and($create['order']['params']['ns'])->toBe(['nsset' => 'NSSET-ONHOST'])->and($create['order']['params']['registrant'])->toBe(['id' => 'G-000001']);
    expect(Http::recorded(fn ($r) => str_contains($r->url(), 'demoreg.net'))->count())->toBeGreaterThan(0);
});

it('honours a TLD pinned to one registrar and falls back to the preference order without price data', function () {
    registrarCost('wedos', 'cz', 16500);
    registrarCost('subreg', 'cz', 14000);
    TldPolicy::query()->whereKey('cz')->update(['registrar_provider' => 'wedos']);
    expect(app(RegistrarSelector::class)->choose('cz'))->toMatchArray(['provider' => 'wedos', 'reason' => 'pinned']);

    TldPolicy::query()->whereKey('cz')->update(['registrar_provider' => 'auto']);
    RegistrarTldCost::query()->delete();
    $choice = app(RegistrarSelector::class)->choose('cz');
    expect($choice['provider'])->toBe('wedos')->and($choice['reason'])->toBe('no_cost_data');

    registrarCost('wedos', 'cz', 14000);
    registrarCost('subreg', 'cz', 560, 'EUR'); // 5.60 EUR × 25 = 140 Kč → tie → preference order
    $choice = app(RegistrarSelector::class)->choose('cz');
    expect($choice['provider'])->toBe('wedos')->and($choice['reason'])->toBe('tie_preference');
    config()->set('onhost.domains.registrar.preference', ['subreg', 'wedos']);
    expect(app(RegistrarSelector::class)->choose('cz')['provider'])->toBe('subreg');

    // without a sandbox the Subreg instance cannot honour test mode, so a test-mode registration goes to the registrar that can
    ProviderInstance::query()->where('key', 'subreg-main')->update(['options' => json_encode([]), 'base_url' => 'https://subreg.cz']);
    app(ProviderRegistry::class)->forget(ProviderInstance::query()->where('key', 'subreg-main')->firstOrFail());
    registrarCost('subreg', 'cz', 100);
    $choice = app(RegistrarSelector::class)->choose('cz', 'register', true);
    expect($choice['provider'])->toBe('wedos')->and(collect($choice['candidates'])->firstWhere('provider', 'subreg')['skipped'])->toBe('no_test_mode');
});

it('renews at the registrar holding the domain and reuses per-registrar contact copies', function () {
    $wapi = ['registered' => true, 'nsset' => true, 'expiration' => '2027-01-31'];
    registryFake($wapi);
    $subreg = ['registered' => true, 'expiration' => '2027-03-31'];
    subregRegistryFake($subreg);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    app(WalletService::class)->topup($org, Money::minor(100000, 'CZK'), 'bank', 'topup-1', $ctx);
    $wedosContact = RegistrarContact::query()->create(['organization_id' => $org->id, 'registrar_provider' => 'wedos', 'kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-JANA']);
    $atSubreg = Domain::query()->create([
        'organization_id' => $org->id, 'fqdn_ascii' => 'drzena.cz', 'fqdn_unicode' => 'drzena.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'subreg',
        'registered_at' => now()->subYear(), 'expires_at' => now()->addDays(20), 'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrant_contact_id' => $wedosContact->id, 'admin_contact_id' => $wedosContact->id,
    ]);
    $subreg['expiration'] = $atSubreg->expires_at->toDateString();
    $operation = app(DomainService::class)->renew($atSubreg, 1, $ctx, 'renew-subreg-1');
    $operation = driveOperation($operation);
    expect($operation->state)->toBe(Operation::SUCCEEDED)->and($operation->provider_instance_id)->toBe(ProviderInstance::query()->where('key', 'subreg-main')->value('id'));
    expect(wapiCommands())->toBe([])->and(subregCalls())->toContain('Make_Order');
    expect(collect(subregParams('Make_Order'))->firstWhere('order.type', 'Renew_Domain')['order']['params'])->toMatchArray(['period' => '1', 'curExpDate' => $atSubreg->expires_at->toDateString()]);
    expect($atSubreg->fresh()->expires_at->toDateString())->toBe($atSubreg->expires_at->copy()->addYear()->toDateString());

    // contacts synced at one registrar get a sibling copy at the other one on first use
    $sibling = $wedosContact->siblingFor('subreg');
    expect($sibling->id)->not->toBe($wedosContact->id)->and($sibling->registrar_provider)->toBe('subreg')->and($sibling->source_contact_id)->toBe($wedosContact->id)->and($sibling->state)->toBe('draft')->and($sibling->name)->toBe('Jana Nováková');
    expect($wedosContact->siblingFor('subreg')->id)->toBe($sibling->id)->and($sibling->siblingFor('wedos')->id)->toBe($wedosContact->id);
});

it('reconciles, polls and samples credit across every registrar', function () {
    $wapi = ['registered' => true, 'nsset' => true, 'expiration' => '2027-09-06', 'listing' => [['name' => 'u-wedos.cz', 'status' => 'active', 'expiration' => '2027-09-06']], 'queue' => [['id' => 'n-1', 'type' => 'domain-transfer-out', 'name' => 'u-wedos.cz', 'result' => 'transferred_out']]];
    registryFake($wapi);
    $subreg = ['registered' => true, 'listing' => [['name' => 'u-subreg.cz', 'expire' => '2027-12-31', 'autorenew' => 0]], 'credit' => '3000.00', 'queue' => [['date' => '2026-09-07 10:00:00', 'id' => 9, 'orderid' => 100, 'orderstatus' => 'Completed', 'message' => '', 'errorcode' => 0]]];
    subregRegistryFake($subreg);
    $subreg['orders']['100'] = ['type' => 'Renew_Domain', 'domain' => 'u-subreg.cz', 'status' => 'Completed'];
    [$user, $org] = $this->customerWithOrganization();
    $contact = RegistrarContact::query()->create(['organization_id' => $org->id, 'kind' => 'registrant', 'name' => 'Jana', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced', 'remote_id' => 'ONH-1']);
    foreach ([['u-wedos.cz', 'wedos'], ['u-subreg.cz', 'wedos'], ['u-missing.cz', 'subreg']] as [$fqdn, $provider]) {
        Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => $fqdn, 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => $provider, 'expires_at' => now()->addMonths(6), 'dns_provider' => 'external', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);
    }
    $ctx = CommandContext::system('test');
    $report = app(DomainService::class)->reconcile($ctx);
    expect($report['checked'])->toBe(3)->and($report['missing_remote'])->toBe(['u-missing.cz'])->and($report['unknown_remote'])->toBe([]);
    expect(Domain::query()->where('fqdn_ascii', 'u-subreg.cz')->value('registrar_provider'))->toBe('subreg') // the listing says Subreg holds it
        ->and(Domain::query()->where('fqdn_ascii', 'u-subreg.cz')->first()->expires_at->toDateString())->toBe('2027-12-31');

    $stats = app(RegistrarPollWorker::class)->drain(10, $ctx);
    expect($stats['received'])->toBe(2)->and($stats['acked'])->toBe(2);
    expect(RegistrarNotification::query()->pluck('state', 'remote_id')->all())->toBe(['wedos:n-1' => 'acked', 'subreg:9' => 'acked']);
    expect(Domain::query()->where('fqdn_ascii', 'u-wedos.cz')->value('state'))->toBe(DomainStateMachine::TRANSFERRED_OUT);

    $snapshots = app(RegistrarCreditMonitor::class)->sampleAll();
    expect(collect($snapshots)->pluck('balance_minor', 'registrar_provider')->all())->toBe(['wedos' => 2500000, 'subreg' => 300000]);
});

it('gives staff the price book: matrix, API refresh, manual costs and TLD pins', function () {
    $subreg = ['prices' => ['cz' => ['register' => '140.00', 'renew' => '150.00', 'transfer' => '0', 'restore' => '700'], 'eu' => ['register' => '6.10', 'renew' => '6.10', 'transfer' => '6.10', 'restore' => '30']]];
    subregRegistryFake($subreg);
    $wapi = ['registered' => false];
    registryFake($wapi);
    $staff = $this->staff('platform_owner');
    $this->actingAs($staff, 'sanctum');

    $matrix = $this->getJson('/v1/staff/registrars')->assertOk()->json('data');
    expect(collect($matrix['registrars'])->pluck('provider')->all())->toBe(['wedos', 'subreg'])->and(collect($matrix['registrars'])->firstWhere('provider', 'subreg')['has_price_api'])->toBeTrue()->and($matrix['providers'])->toContain('wedos', 'subreg');
    $cz = collect($matrix['tlds'])->firstWhere('tld', 'cz');
    // a missing Subreg price is fetched from its API on first use, so the very first matrix already compares both registrars
    expect($cz['selling']['CZK']['register'])->toBe(17900)->and($cz['costs']['wedos']['register'])->toBe(14500)->and($cz['costs']['wedos']['source'])->toBe('seed')
        ->and($cz['costs']['subreg'])->toMatchArray(['currency' => 'CZK', 'register' => 14000, 'renew' => 15000, 'source' => 'api'])->and($cz['winner'])->toBe('subreg')->and($cz['margin_czk'])->toBe(17900 - 14000);
    expect(collect($matrix['tlds'])->firstWhere('tld', 'com')['costs']['subreg'])->toBeNull()->and(collect($matrix['tlds'])->firstWhere('tld', 'com')['winner'])->toBe('wedos');

    $refresh = $this->postJson('/v1/staff/registrars/costs/refresh', [], ['Idempotency-Key' => 'rc-1'])->assertOk()->json(); // command results are returned as-is
    expect($refresh['registrars']['subreg']['tlds'])->toBe(2)->and($refresh['registrars']['wedos']['error'])->toContain('no price API');
    $cz = collect($this->getJson('/v1/staff/registrars')->assertOk()->json('data.tlds'))->firstWhere('tld', 'cz');
    expect($cz['costs']['subreg']['fetched_at'])->not->toBeNull()->and($cz['winner'])->toBe('subreg');
    $eu = collect($this->getJson('/v1/staff/registrars')->assertOk()->json('data.tlds'))->firstWhere('tld', 'eu');
    expect($eu['costs']['subreg'])->toMatchArray(['currency' => 'CZK', 'register' => 610, 'register_czk' => 610]); // Prices quotes in the account currency (Get_Credit)

    $this->putJson('/v1/staff/registrars/costs', ['registrar_provider' => 'wedos', 'tld' => '.cz', 'currency' => 'CZK', 'register' => '120', 'renew' => '120', 'transfer' => '0'], ['Idempotency-Key' => 'rc-2'])->assertOk()->assertJsonPath('register', 12000)->assertJsonPath('source', 'manual');
    expect(collect($this->getJson('/v1/staff/registrars')->assertOk()->json('data.tlds'))->firstWhere('tld', 'cz')['winner'])->toBe('wedos');
    $this->putJson('/v1/staff/registrars/costs', ['registrar_provider' => 'nope', 'tld' => 'cz', 'register' => '1'], ['Idempotency-Key' => 'rc-3'])->assertUnprocessable()->assertJsonPath('error', 'registrar_unknown');
    $this->putJson('/v1/staff/registrars/costs', ['registrar_provider' => 'wedos', 'tld' => 'cz'], ['Idempotency-Key' => 'rc-4'])->assertUnprocessable()->assertJsonPath('error', 'price_missing');

    $this->putJson('/v1/staff/registrars/policy', ['tld' => 'cz', 'registrar_provider' => 'subreg'], ['Idempotency-Key' => 'rp-1'])->assertOk()->assertJsonPath('registrar_provider', 'subreg');
    expect(collect($this->getJson('/v1/staff/registrars')->assertOk()->json('data.tlds'))->firstWhere('tld', 'cz'))->toMatchArray(['pinned' => 'subreg', 'winner' => 'subreg', 'reason' => 'pinned']);
    $this->putJson('/v1/staff/registrars/policy', ['tld' => 'cz', 'registrar_provider' => 'nope'], ['Idempotency-Key' => 'rp-2'])->assertUnprocessable();

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->getJson('/v1/staff/registrars')->assertForbidden();
    $this->get('/sprava/nastaveni/integrace')->assertRedirect('/panel');
});
