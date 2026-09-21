<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\CertificateAutoIssuer;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Pairing a domain with a web hosting plan: the site learns the domain and www, the rows in the zone at the customer's
 * registrar point at the node (the old A/CNAME rows for @ and www go, the rest stays), the certificate follows once the
 * name resolves, and unpairing reverts the alias and the rows. A domain whose DNS runs elsewhere gets instructions.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('pairs a mirrored domain with a web hosting plan and unpairs it again', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel'); // the site shop.cz on aapanel-managed01
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '192.0.2.11'])]);
    $state = [
        'domains' => [['name' => 'eshop.cz', 'status' => 'active', 'expiration' => now()->addDays(300)->toDateString()], ['name' => 'blog.cz', 'status' => 'active', 'expiration' => now()->addDays(300)->toDateString()]],
        'zones' => ['eshop.cz' => [['ID' => '11', 'name' => '', 'ttl' => 1800, 'rdtype' => 'A', 'rdata' => '203.0.113.5'], ['ID' => '12', 'name' => 'www', 'ttl' => 1800, 'rdtype' => 'CNAME', 'rdata' => 'eshop.cz.'], ['ID' => '13', 'name' => '', 'ttl' => 1800, 'rdtype' => 'MX', 'rdata' => '10 mail.eshop.cz.']]],
        'credit' => '900.00', 'commands' => [], 'logins' => [], 'aliases' => [],
    ];
    connectionWapiFake($state); // the WAPI double; the panel double joins it below
    Http::fake(function (Request $request) use (&$state) {
        $url = $request->url();
        if (str_contains($url, 'api.wedos.com')) {
            return null; // handled by the WAPI double
        }
        $q = (string) parse_url($url, PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'action=AddDomain') => (function () use (&$state, $request) {
                $state['aliases'][] = ['id' => (string) (500 + count($state['aliases'])), 'pid' => 41, 'name' => (string) $request['domain'], 'port' => 80]; // pid = the site the alias belongs to

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'action=DelDomain') => (function () use (&$state, $request) {
                $state['aliases'] = array_values(array_filter($state['aliases'], fn ($a) => $a['name'] !== (string) $request['domain']));

                return Http::response(['status' => true, 'msg' => 'ok']);
            })(),
            str_contains($q, 'table=domain') => Http::response(['data' => $state['aliases'], 'page' => '']),
            str_contains($q, 'apply_cert_api') => Http::response(['cert' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----", 'private' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----"]),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $this->actingAs($user, 'sanctum');
    $this->postJson('/v1/auth/step-up', ['method' => 'password', 'code' => 'Correct-Horse-Battery-9'])->assertOk();
    $connection = RegistrarConnection::query()->findOrFail($this->postJson('/v1/registrar-connections', ['login' => 'ucet@firma.cz', 'password' => 'api-secret-1'])->assertCreated()->json('connection.id'));
    $eshop = Domain::query()->where('fqdn_ascii', 'eshop.cz')->firstOrFail();
    $blog = Domain::query()->where('fqdn_ascii', 'blog.cz')->firstOrFail();
    $zone = DnsZone::query()->where('name', 'eshop.cz')->firstOrFail();

    // pairing: aliases on the site, rows at the registrar, the service remembers the domain, the certificate is pending DNS
    $pairing = $this->postJson("/v1/domains/{$eshop->id}/pair", ['service_id' => $service->id])->assertStatus(202)->json('pairing');
    expect($pairing['dns'])->toBe('synced')->and($pairing['aliases'])->toBe(['eshop.cz', 'www.eshop.cz'])->and($pairing['records'][0])->toMatchArray(['name' => '@', 'type' => 'A', 'content' => '192.0.2.11']);
    expect(array_column($state['aliases'], 'name'))->toBe(['eshop.cz', 'www.eshop.cz']);
    $remote = collect($state['zones']['eshop.cz']);
    expect($remote->where('rdtype', 'A')->pluck('rdata')->all())->toBe(['192.0.2.11', '192.0.2.11'])->and($remote->where('rdtype', 'CNAME')->count())->toBe(0)->and($remote->where('rdtype', 'MX')->count())->toBe(1);
    expect(array_count_values($state['commands'])['dns-domain-commit'])->toBeGreaterThanOrEqual(2);
    $zone->refresh();
    expect($zone->records()->where('managed_by', 'system')->where('comment', 'service:'.$service->id)->count())->toBe(2)->and($zone->records()->where('content', '203.0.113.5')->exists())->toBeFalse()->and($zone->records()->where('type', 'MX')->exists())->toBeTrue();
    $service->refresh();
    expect($service->desired_spec['extra_domains'])->toBe(['eshop.cz'])->and($service->tags['access']['certificate'])->toBe('pending_dns');
    $shown = collect($this->getJson("/v1/registrar-connections/{$connection->id}")->assertOk()->json('data.domains'))->keyBy('fqdn');
    expect($shown['eshop.cz']['paired_service_id'])->toBe($service->id)->and($shown['eshop.cz']['pairing']['dns'])->toBe('synced');
    $this->postJson("/v1/domains/{$eshop->id}/pair", ['service_id' => $service->id])->assertStatus(202); // idempotent: pairing the same site again changes nothing
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'Doména eshop.cz spárována s webem shop.cz')->exists())->toBeTrue();

    // the certificate: once the paired name resolves to the node, ssl.issue runs for the resolving names only
    $issuer = app(CertificateAutoIssuer::class);
    $issuer->resolveWith(fn (string $hostname) => in_array($hostname, ['eshop.cz', 'www.eshop.cz'], true) ? ['192.0.2.11'] : []);
    expect($issuer->run())->toBe(['checked' => 1, 'resolved' => 1, 'requested' => 1]);
    $operation = Operation::query()->where('service_id', $service->id)->latest('created_at')->orderByDesc('id')->firstOrFail();
    expect(data_get($operation->desired, 'action'))->toBe('ssl.issue')->and(data_get($operation->desired, 'domains'))->toBe(['eshop.cz', 'www.eshop.cz']);
    expect(driveOperation($operation)->state)->toBe(Operation::SUCCEEDED)->and(Service::query()->findOrFail($service->id)->tags['access']['certificate'])->toBe('issued');

    // a domain whose DNS runs elsewhere: the site gets the alias, the customer gets the rows to set
    $manual = $this->postJson("/v1/domains/{$blog->id}/pair", ['service_id' => $service->id])->assertStatus(202)->json('pairing');
    expect($manual['dns'])->toBe('manual')->and($manual['records'])->toHaveCount(2)->and(array_column($state['aliases'], 'name'))->toContain('blog.cz');

    // unpairing: aliases and the rows the pairing added go, the customer's other rows stay
    $undone = $this->postJson("/v1/domains/{$eshop->id}/unpair")->assertOk()->json('pairing');
    expect($undone['aliases_removed'])->toBe(2)->and($undone['dns'])->toBe('removed')->and(array_column($state['aliases'], 'name'))->toBe(['blog.cz', 'www.blog.cz']);
    expect(collect($state['zones']['eshop.cz'])->where('rdtype', 'A')->count())->toBe(0)->and(collect($state['zones']['eshop.cz'])->where('rdtype', 'MX')->count())->toBe(1);
    expect($zone->records()->where('managed_by', 'system')->count())->toBe(0)->and(Domain::query()->findOrFail($eshop->id)->meta['paired_service_id'] ?? null)->toBeNull()
        ->and(Service::query()->findOrFail($service->id)->desired_spec['extra_domains'])->toBe(['blog.cz']);
    $this->withHeader('Idempotency-Key', 'unpair-again')->postJson("/v1/domains/{$eshop->id}/unpair")->assertStatus(409)->assertJsonPath('error', 'domain_not_paired'); // a fresh key: the same request would otherwise replay
    $this->flushHeaders();

    // guards: another customer's service, a terminated service
    $other = $this->customerWithOrganization()[1];
    $foreign = Service::query()->create(['organization_id' => $other->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'hostname' => 'other.cz', 'state' => 'ACTIVE', 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()]);
    $this->postJson("/v1/domains/{$eshop->id}/pair", ['service_id' => $foreign->id])->assertNotFound();
});
