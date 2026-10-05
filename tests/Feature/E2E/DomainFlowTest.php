<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Catalog\Models\DomainPrice;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\WalletLedger\Models\WalletHold;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

require_once __DIR__.'/../../Support/E2E/E2EHelpers.php';

/*
 * E2 — a customer registers a .cz domain, edits its DNS, changes the holder's contact, renews it, and moves another domain in,
 * touching only the real HTTP routes.
 *
 * Registration: domain search (POST /v1/domains/check) → cart → quote → order with the card gateway → Comgate callback to the real
 * webhook route → the order settles, the outbox relays and the registration saga runs against a WEDOS double (contact, NSSET,
 * domain-create, domain-info) and a PowerDNS double (the canonical zone) → the domain is ACTIVE and listed in /v1/domains.
 * DNS: read the zone, stage a record, preview, commit (PowerDNS PATCH), read it back, version history.
 * Holder and renewal: POST /v1/domains/{id}/holder (step-up) and POST /v1/domains/{id}/renew from credit at the list price.
 * Transfer-in: a paid transfer line waits for the code; the code goes in through POST /v1/domains/transfer-in and the transfer starts.
 *
 * Only the edges are doubles (registrar, DNS server, card gateway via Http::fake, mail via Notification::fake); everything between is
 * the product. The registry double and the PowerDNS double are the shared ones from tests/Pest.php. Asserts do not depend on row
 * order (SQLite and PostgreSQL alike). Customer answers must never name a vendor (WEDOS, Subreg, PowerDNS).
 */

const E2E_DOMAIN_VENDORS = ['wedos', 'subreg', 'powerdns', 'pdns'];

beforeEach(function () {
    e2eSeedPlatform();
    e2eComgateEnvironment();
    $_ENV['WEDOS_MAIN_LOGIN'] = 'onhost@onhost.cz';
    $_ENV['WEDOS_MAIN_WAPI_PASSWORD'] = 'wapi-secret';
    ProviderInstance::query()->firstOrCreate(['key' => 'wedos-main'], ['provider' => 'wedos', 'name' => 'WEDOS WAPI', 'base_url' => 'https://api.wedos.com', 'secret_ref' => 'env://WEDOS_MAIN', 'state' => 'active', 'capabilities' => ['registrar' => true], 'adapter_version' => '1.0.0']);
    pdnsLab();
    config()->set('onhost.dns.parking_ipv4', '89.187.160.1');
    Http::preventStrayRequests();
});

/** The registrant block of a domain line (the holder of the name). */
function e2eRegistrant(): array
{
    return ['name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'country' => 'CZ'];
}

/** The consents a domain order needs: the common set plus the registrar's and the registry's terms, signed by the holder. */
function e2eDomainConsents(): array
{
    return e2eConsents() + ['registrar_terms' => ['person' => 'Jana Nováková'], 'registry_terms_cz' => ['person' => 'Jana Nováková']];
}

/** @return array{0:Order,1:TestResponse} an order for one domain line, waiting for the card payment */
function e2eOrderDomain(object $test, array &$gate, string $fqdn, string $action, string $label): array
{
    $line = ['product_key' => 'domain', 'qty' => 1, 'config' => ['fqdn' => $fqdn, 'period_years' => 1, 'action' => $action, 'registrant' => e2eRegistrant()]];
    $test->withHeaders(e2eHeaders($label.'-cart'))->putJson('/v1/cart', ['items' => [$line], 'commit_months' => 1, 'currency' => 'CZK'])->assertOk()->assertJsonCount(1, 'data.items');
    $quote = $test->withHeaders(e2eHeaders($label.'-quote'))->postJson('/v1/cart/quote')->assertOk();
    $gate['trans_id'] = 'E2E-'.strtoupper($label).'-'.bin2hex(random_bytes(3)); // the gateway answers the order with this id
    $placed = $test->withHeaders(e2eHeaders($label.'-order'))->postJson('/v1/orders', [
        'quote_id' => $quote->json('data.quote_id'), 'consents' => e2eDomainConsents(), 'payment' => ['mode' => 'gateway', 'provider' => 'comgate', 'method' => 'card'],
    ])->assertCreated();
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $gate['total'] = $order->total_minor;

    return [$order, $placed];
}

/** @param  array<string,mixed>  $body */
function e2eNoVendorNames(array $body): void
{
    $text = strtolower((string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    foreach (E2E_DOMAIN_VENDORS as $vendor) {
        expect($text)->not->toContain($vendor);
    }
}

it('registers a .cz domain from search to an active, listed domain with a served zone, and keeps vendor names out of every answer', function () {
    LaravelNotification::fake();
    $registry = ['registered' => false, 'nsset' => false, 'expiration' => now()->addYear()->toDateString(), 'created' => now()->toDateString()];
    $gate = [];
    registryFake($registry);
    pdnsZoneFake('skladomat-e2e.cz');
    e2eComgateFake($gate);
    [$user, $org] = e2eSignUp($this, 'domeny.e2e@example.cz');

    // 1. search: a free name with its list price, a taken one as not available
    $search = $this->withHeaders(e2eHeaders('check'))->postJson('/v1/domains/check', ['names' => ['skladomat-e2e.cz'], 'currency' => 'CZK'])->assertOk();
    $hit = $search->json('data.0');
    expect($hit['fqdn'])->toBe('skladomat-e2e.cz')->and($hit['available'])->toBeTrue()->and($hit['price_register'])->toBeGreaterThan(0);
    e2eNoVendorNames($search->json());

    // 2. order → pay: nothing is registered before the money
    [$order, $placed] = e2eOrderDomain($this, $gate, 'skladomat-e2e.cz', 'register', 'reg');
    expect($order->state)->toBe(OrderStateMachine::PENDING_PAYMENT)->and((string) $placed->json('redirect_url'))->toContain('comgate.cz')
        ->and(Domain::query()->where('organization_id', $org->id)->count())->toBe(0)->and($registry['commands'] ?? [])->not->toContain('domain-create');
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    expect($order->refresh()->state)->toBe(OrderStateMachine::PAID);

    // 3. the outbox relays, the registration saga runs against the registry and the DNS server
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE)->and(array_count_values($registry['commands'])['domain-create'])->toBe(1);
    $domain = Domain::query()->where('organization_id', $org->id)->where('fqdn_ascii', 'skladomat-e2e.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($domain->dns_zone_id)->not->toBeNull()
        ->and(OrderItem::query()->where('order_id', $order->id)->value('domain_id'))->toBe($domain->id);

    // 4. what the customer sees: the list, the detail, the invoice
    $list = $this->withHeaders(e2eHeaders('domains'))->getJson('/v1/domains')->assertOk();
    expect(collect($list->json('data'))->pluck('fqdn')->all())->toBe(['skladomat-e2e.cz'])->and($list->json('data.0.state'))->toBe(DomainStateMachine::ACTIVE)->and($list->json('data.0.dns_provider'))->toBe('onhost');
    $detail = $this->withHeaders(e2eHeaders('domain'))->getJson("/v1/domains/{$domain->id}")->assertOk();
    expect($detail->json('data.fqdn'))->toBe('skladomat-e2e.cz')->and($detail->json('data.expires_at'))->not->toBeNull();
    e2eNoVendorNames($list->json());
    e2eNoVendorNames($detail->json());
    $statement = Invoice::query()->where('order_id', $order->id)->where('type', 'statement')->firstOrFail();
    expect($statement->subtotal_minor)->toBe($hit['price_register'])->and((int) $statement->discount_minor)->toBe(0); // the list price shown in the search, nothing implicit off
    expect($statement->state)->toBe(Invoice::PAID)->and($statement->total_minor)->toBe($order->total_minor);

    // 5. events: said once, to this organization
    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events)->toHaveKeys(['order.paid', 'domain.registered'])->and($events['order.paid'])->toBe(1)->and($events['domain.registered'])->toBe(1)
        ->and(OutboxMessage::query()->whereIn('name', ['order.paid', 'domain.registered'])->whereNull('published_at')->count())->toBe(0);
    expect(DnsZone::query()->where('organization_id', $org->id)->where('name', 'skladomat-e2e.cz')->exists())->toBeTrue()
        ->and(RegistrarContact::query()->where('organization_id', $org->id)->count())->toBeGreaterThan(0)->and(WalletHold::query()->where('organization_id', $org->id)->count())->toBeGreaterThanOrEqual(0);
});

/** The whole registration over the real routes, without the asserts of the first flow: pay by card, relay, run the saga. */
function e2eRegisterDomain(object $test, array &$gate, string $fqdn, string $label): Domain
{
    [$order] = e2eOrderDomain($test, $gate, $fqdn, 'register', $label);
    e2eComgateCallback($test, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE);

    return Domain::query()->where('fqdn_ascii', $fqdn)->firstOrFail();
}

/** The WEDOS commands the shared registry double does not know (holder contact update). Registered FIRST: the first fake that answers wins. */
function e2eRegistryExtras(array &$sent): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$sent) {
        $payload = json_decode((string) $request['request'], true)['request'] ?? [];
        if (($payload['command'] ?? '') !== 'contact-update') {
            return null;
        }
        $sent[] = (array) ($payload['data'] ?? []);

        return Http::response(['response' => ['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'] ?? null, 'svTRID' => 'sv-e2e', 'command' => 'contact-update', 'data' => []]]);
    }]);
}

it('edits the zone of a registered domain in two phases, changes the holder contact and renews at the list price', function () {
    LaravelNotification::fake();
    $holderCalls = [];
    e2eRegistryExtras($holderCalls);
    $registry = ['registered' => false, 'nsset' => false, 'expiration' => now()->addYear()->toDateString(), 'created' => now()->toDateString()];
    $gate = [];
    registryFake($registry);
    pdnsZoneFake('zona-e2e.cz');
    e2eComgateFake($gate);
    [$user, $org, $password] = e2eSignUp($this, 'zona.e2e@example.cz');
    $domain = e2eRegisterDomain($this, $gate, 'zona-e2e.cz', 'reg');

    // ── DNS: read, stage, preview, commit, read back ─────────────────────────────────────────────────────────────
    $zones = $this->withHeaders(e2eHeaders('zones'))->getJson('/v1/dns/zones')->assertOk();
    expect(collect($zones->json('data'))->pluck('name')->all())->toBe(['zona-e2e.cz']);
    $zoneId = $zones->json('data.0.id');
    $zone = $this->withHeaders(e2eHeaders('zone'))->getJson('/v1/domains/zona-e2e.cz/zone')->assertOk(); // the handoff alias by domain name
    expect($zone->json('data.id'))->toBe($zoneId)->and($zone->json('data.nameservers'))->toBe(['ns1.onhost.cz', 'ns2.onhost.cz']);
    $before = collect($zone->json('data.records'));
    expect($before->pluck('type')->all())->toContain('A')->and($before->where('name', 'api')->count())->toBe(0);

    $this->withHeaders(e2eHeaders('stage'))->postJson("/v1/dns/zones/{$zoneId}/changes", ['change' => 'add', 'record' => ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.6', 'ttl' => 300], 'reason' => 'api host'])->assertCreated();
    $this->withHeaders(e2eHeaders('stage-bad'))->postJson("/v1/dns/zones/{$zoneId}/changes", ['change' => 'add', 'record' => ['name' => 'bad', 'type' => 'A', 'content' => '999.1.1.1']])->assertStatus(422); // refused before it waits
    $preview = $this->withHeaders(e2eHeaders('preview'))->getJson("/v1/dns/zones/{$zoneId}/preview")->assertOk();
    expect($preview->json('data.changes'))->toHaveCount(1)->and(collect($preview->json('data.records'))->firstWhere('name', 'api')['content'])->toBe('89.187.160.6');
    expect(collect($this->getJson("/v1/dns/zones/{$zoneId}")->json('data.records'))->where('name', 'api')->count())->toBe(0); // staged is not live

    $patchesBefore = collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'PATCH')->count();
    $commit = $this->withHeaders(e2eHeaders('commit'))->postJson("/v1/dns/zones/{$zoneId}/commit", ['reason' => 'api host'])->assertOk();
    $patches = collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'PATCH' && str_contains($p[0]->url(), 'pdns.mgmt.test'));
    expect($patches->count())->toBe($patchesBefore + 1)
        ->and(collect($patches->last()[0]['rrsets'])->contains(fn (array $set) => $set['name'] === 'api.zona-e2e.cz.' && $set['type'] === 'A' && $set['records'][0]['content'] === '89.187.160.6'))->toBeTrue();
    $after = $this->withHeaders(e2eHeaders('zone-after'))->getJson("/v1/dns/zones/{$zoneId}")->assertOk();
    $api = collect($after->json('data.records'))->firstWhere('name', 'api');
    expect($api)->not->toBeNull()->and($api['content'])->toBe('89.187.160.6')->and($api['ttl'])->toBe(300)->and($after->json('data.changes'))->toBe([]);
    $versions = $this->withHeaders(e2eHeaders('versions'))->getJson("/v1/dns/zones/{$zoneId}/versions")->assertOk();
    expect(collect($versions->json('data'))->pluck('version')->sort()->values()->all())->toBe([1, 2]);
    foreach ([$zones, $zone, $preview, $commit, $after, $versions] as $answer) {
        e2eNoVendorNames($answer->json());
    }

    // ── holder: HIGH, a fresh step-up first; the registrar is asked, the platform copy follows ───────────────────
    $this->withHeaders(e2eHeaders('holder-nostepup'))->postJson("/v1/domains/{$domain->id}/holder", ['email' => 'nova@example.cz'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect($holderCalls)->toBe([]);
    e2eStepUp($this, $password);
    $holder = $this->withHeaders(e2eHeaders('holder'))->postJson("/v1/domains/{$domain->id}/holder", ['email' => 'nova@example.cz', 'city' => 'Brno', 'postal_code' => '60200'])->assertOk();
    expect($holder->json('changed'))->toBe(['email', 'city', 'postal_code'])->and($holder->json('registrar_updated'))->toBeTrue()
        ->and($holderCalls)->toHaveCount(1)->and($holderCalls[0])->toMatchArray(['email' => 'nova@example.cz', 'addr_city' => 'Brno', 'addr_zip' => '60200']);
    $this->withHeaders(e2eHeaders('holder-name'))->postJson("/v1/domains/{$domain->id}/holder", ['name' => 'Petr Nový'])->assertStatus(422)->assertJsonPath('error', 'domain_holder_identity_change'); // the holder himself is a transfer
    expect(RegistrarContact::query()->findOrFail($domain->refresh()->registrant_contact_id)->email)->toBe('nova@example.cz');
    $contacts = $this->withHeaders(e2eHeaders('contacts'))->getJson('/v1/domains/contacts')->assertOk();
    expect(collect($contacts->json('data'))->pluck('email')->all())->toContain('nova@example.cz');
    e2eNoVendorNames($holder->json());

    // ── renewal: from credit, one year or more, at the list price (no implicit discount) ─────────────────────────
    $this->withHeaders(e2eHeaders('renew-zero'))->postJson("/v1/domains/{$domain->id}/renew", ['years' => 0])->assertStatus(422); // never less than a year
    $nocredit = $this->withHeaders(e2eHeaders('renew-nocredit'))->postJson("/v1/domains/{$domain->id}/renew", ['years' => 1]);
    expect($nocredit->status())->toBeGreaterThanOrEqual(400)->and(array_count_values($registry['commands'])['domain-renew'] ?? 0)->toBe(0); // nothing to pay with, nothing sent
    e2eTopUp($this, $gate, 5000);
    $expiresBefore = $domain->refresh()->expires_at->copy();
    $this->withHeaders(e2eHeaders('renew'))->postJson("/v1/domains/{$domain->id}/renew", ['years' => 1])->assertStatus(202);
    driveOperations();
    $list = DomainPrice::query()->where('tld', 'cz')->where('currency', 'CZK')->firstOrFail()->renew()->minor;
    $renewal = Invoice::query()->where('organization_id', $org->id)->where('type', 'statement')->get()->first(fn (Invoice $i) => data_get($i->meta, 'domain_id') === $domain->id);
    expect($renewal)->not->toBeNull()->and($renewal->state)->toBe(Invoice::PAID)->and($renewal->subtotal_minor)->toBe($list)->and((int) $renewal->discount_minor)->toBe(0); // the line is the list price for one year: no discount nobody approved
    $domain->refresh();
    expect($domain->expires_at->toDateString())->toBe($expiresBefore->copy()->addYear()->toDateString())->and($domain->state)->toBe(DomainStateMachine::ACTIVE);
    $hold = WalletHold::query()->where('organization_id', $org->id)->where('reference_type', 'domain')->where('reference_id', $domain->id)->firstOrFail();
    expect($hold->state)->toBe('captured')->and($hold->purpose)->toBe('domain_renewal')->and($hold->amount_minor)->toBe($renewal->total_minor)->and($renewal->total_minor)->toBe($list + $renewal->tax_minor);
    app(OutboxPublisher::class)->relayPending();
    expect(OutboxMessage::query()->where('organization_id', $org->id)->where('name', 'domain.renewed')->count())->toBe(1)
        ->and(array_count_values($registry['commands'])['domain-renew'])->toBe(1);
    $detail = $this->withHeaders(e2eHeaders('domain-after'))->getJson("/v1/domains/{$domain->id}")->assertOk();
    expect(substr((string) $detail->json('data.expires_at'), 0, 10))->toBe($domain->expires_at->toDateString());
    e2eNoVendorNames($detail->json());
});

const E2E_TRANSFER_CODE = 'Kod-Prevodu-7731-xQ';

/** A registry that knows transfers: `transferred` flips when domain-transfer is accepted; every command is recorded in `commands`. */
function e2eTransferRegistry(array &$state): void
{
    $state += ['commands' => [], 'transferred' => false, 'expiration' => now()->addYear()->toDateString()];
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$state) {
        $payload = json_decode((string) $request['request'], true)['request'];
        $command = $payload['command'];
        $data = (array) ($payload['data'] ?? []);
        $state['commands'][] = $command;
        $overrides = match ($command) {
            'contact-create' => ['data' => ['contact' => ['cname' => $data['cname'] ?? 'ONH-T']]],
            'nsset-info' => ['data' => ['nsset' => ['nsset' => $data['nsset'] ?? 'NSSET-ONHOST']]],
            'nsset-create', 'ping', 'domain-transfer-check' => [],
            'domain-check' => ['code' => 3201, 'result' => 'Domain is registered'],
            'domain-transfer' => (function () use (&$state, $data) {
                $state['transfer_data'] = $data;
                $state['transferred'] = true;

                return [];
            })(),
            'domain-info' => $state['transferred']
                ? ['data' => ['domain' => ['name' => $data['name'], 'status' => 'active', 'expiration' => $state['expiration'], 'created' => '2020-01-01', 'dns' => [['name' => 'ns1.example.net'], ['name' => 'ns2.example.net']], 'owner_c' => 'ONH-T', 'nsset' => 'NSSET-ONHOST']]]
                : ['code' => 3222, 'result' => 'Domain not found'],
            default => ['code' => 2100, 'result' => "unexpected {$command}"],
        };

        return Http::response(['response' => array_merge(['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'] ?? null, 'svTRID' => 'sv-'.uniqid(), 'command' => $command, 'data' => []], $overrides)]);
    }]);
}

it('moves a domain in: the paid transfer line waits for the code, the code starts the transfer, and nothing is registered', function () {
    LaravelNotification::fake();
    $registry = [];
    $gate = [];
    e2eTransferRegistry($registry);
    e2eComgateFake($gate);
    [$user, $org, $password] = e2eSignUp($this, 'prevod.e2e@example.cz');

    // the quote shows what a transfer costs: the list renewal price for the year it brings (whole years, list price)
    $search = $this->withHeaders(e2eHeaders('check'))->postJson('/v1/domains/check', ['names' => ['prevod-e2e.cz']])->assertOk();
    $renew = DomainPrice::query()->where('tld', 'cz')->where('currency', 'CZK')->firstOrFail()->renew()->minor;
    expect($search->json('data.0.price_transfer'))->toBe($renew)->and($search->json('data.0.transfer_years'))->toBe(1);
    e2eNoVendorNames($search->json());

    // order and pay by card: the line is paid, but the registrar is asked nothing until the holder hands over the code
    [$order] = e2eOrderDomain($this, $gate, 'prevod-e2e.cz', 'transfer', 'xfer');
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
    expect($item->sku)->toBe('domain-cz-transfer')->and($item->unit_net_minor)->toBe($renew);
    e2eComgateCallback($this, $gate['trans_id'], $order->total_minor)->assertOk()->assertJsonPath('result', 'settled');
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    expect($order->refresh()->state)->toBe(OrderStateMachine::PROVISIONING)->and($item->refresh()->state)->toBe('provisioning')
        ->and($registry['commands'])->not->toContain('domain-transfer')->not->toContain('domain-create')
        ->and(Domain::query()->where('fqdn_ascii', 'prevod-e2e.cz')->exists())->toBeFalse();

    // the code: HIGH (step-up), tied to the paid line of this organization, accepted once
    $body = ['fqdn' => 'prevod-e2e.cz', 'order_item_id' => $item->id, 'auth_info' => E2E_TRANSFER_CODE, 'registrant' => e2eRegistrant(), 'consent' => ['person' => 'Jana Nováková']];
    $this->withHeaders(e2eHeaders('xfer-nostepup'))->postJson('/v1/domains/transfer-in', $body)->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    $direct = ['fqdn' => 'bez-objednavky.cz'] + $body;
    unset($direct['order_item_id']);
    e2eStepUp($this, $password);
    $this->withHeaders(e2eHeaders('xfer-noorder'))->postJson('/v1/domains/transfer-in', $direct)->assertStatus(422)->assertJsonPath('error', 'transfer_needs_order'); // a transfer renews the name: it is bought in the cart
    expect($registry['commands'])->not->toContain('domain-transfer');

    $started = $this->withHeaders(e2eHeaders('xfer'))->postJson('/v1/domains/transfer-in', $body)->assertStatus(202);
    driveOperations();
    $this->withHeaders(e2eHeaders('xfer-again'))->postJson('/v1/domains/transfer-in', $body)->assertStatus(409)->assertJsonPath('error', 'transfer_already_submitted');

    // the transfer reached the registrar once, with the code and the year that was paid; nothing was registered
    expect($registry['commands'])->toContain('domain-transfer')->not->toContain('domain-create')->and(array_count_values($registry['commands'])['domain-transfer'])->toBe(1)
        ->and($registry['transfer_data']['auth_info'])->toBe(E2E_TRANSFER_CODE)->and((int) $registry['transfer_data']['period'])->toBe(1);
    $domain = Domain::query()->where('organization_id', $org->id)->where('fqdn_ascii', 'prevod-e2e.cz')->firstOrFail();
    expect($domain->state)->toBe(DomainStateMachine::ACTIVE)->and($item->refresh()->state)->toBe('active')->and($order->refresh()->state)->toBe(OrderStateMachine::ACTIVE);

    // the customer sees the domain; the code is in no answer
    $list = $this->withHeaders(e2eHeaders('domains'))->getJson('/v1/domains')->assertOk();
    expect(collect($list->json('data'))->pluck('fqdn')->all())->toBe(['prevod-e2e.cz']);
    $detail = $this->withHeaders(e2eHeaders('domain'))->getJson("/v1/domains/{$domain->id}")->assertOk();
    foreach ([$started->json(), $list->json(), $detail->json()] as $answer) {
        e2eNoVendorNames($answer);
        expect(json_encode($answer))->not->toContain(E2E_TRANSFER_CODE);
    }
    app(OutboxPublisher::class)->relayPending();
    $events = OutboxMessage::query()->where('organization_id', $org->id)->pluck('name')->countBy()->all();
    expect($events)->toHaveKey('order.paid')->and($events['order.paid'])->toBe(1)
        ->and(OutboxMessage::query()->where('organization_id', $org->id)->where('payload', 'like', '%'.E2E_TRANSFER_CODE.'%')->count())->toBe(0);
});
