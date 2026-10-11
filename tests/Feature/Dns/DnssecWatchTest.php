<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnssecWatch;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Incidents\Models\OnCallAlert;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceHealthCheck;
use Tests\FakeHostResolver;

/*
 * A signed zone only works while the registry holds the DS of the key that signs it. The parent's DS comes from a public
 * validating resolver (DNS-over-HTTPS, through EgressGuard); a mismatch or a missing DS is `bad` and pages once; a DS that
 * could not be read is `unknown`, never `ok`.
 */

const OUR_DS = ['12345 13 2 ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789'];

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('onhost.oncall.provider', '');
});

function dnssecZone(object $org, ?array $ds = OUR_DS): DnsZone
{
    return DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'shop.cz', 'provider' => 'powerdns', 'serial' => 1, 'version' => 1,
        'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'], 'dnssec' => true, 'dnssec_ds' => $ds]);
}

/** What the public resolver answers for the DS of shop.cz. @param list<string> $records */
function parentDs(array $records, int $status = 0): void
{
    Http::fake(['https://cloudflare-dns.com/*' => Http::response(['Status' => $status, 'Answer' => array_map(fn (string $data) => ['name' => 'shop.cz.', 'type' => 43, 'TTL' => 300, 'data' => $data], $records)])]);
}

function dnssecFinding(Service $service): ?array
{
    return collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'dnssec');
}

function dnssecWebService(object $org): Service
{
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['hostname' => 'shop.cz'])->save();

    return $service->refresh();
}

it('is ok when the registry holds a DS that matches our key, whatever the case and spacing', function () {
    parentDs(['12345  13 2 abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789']);
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);
    $service = dnssecWebService($org);

    $result = app(DnssecWatch::class)->run();

    expect($result)->toMatchArray(['checked' => 1, 'broken' => 0])
        ->and($zone->refresh()->dnssec_check['state'])->toBe('ok')
        ->and(dnssecFinding($service)['level'])->toBe('ok')
        ->and(OnCallAlert::query()->count())->toBe(0);
});

it('is bad and pages when the registry has no DS at all', function () {
    parentDs([]);
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);
    $service = dnssecWebService($org);

    $result = app(DnssecWatch::class)->run();

    $finding = dnssecFinding($service);
    expect($result['broken'])->toBe(1)
        ->and($zone->refresh()->dnssec_check['state'])->toBe('missing')
        ->and($finding['level'])->toBe('bad')->and($finding['en'])->toContain('no DS record')
        ->and(OnCallAlert::query()->where('dedup_key', OnCallService::dedupKey('dns.dnssec.broken', 'dns_zone', $zone->id))->count())->toBe(1);
});

it('is bad when the registry holds a DS that is not ours, and one alert only however often it is seen', function () {
    parentDs(['999 13 2 0000000000000000000000000000000000000000000000000000000000000000']);
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);
    $service = dnssecWebService($org);

    app(DnssecWatch::class)->run();
    app(DnssecWatch::class)->run();

    $alerts = OnCallAlert::query()->where('dedup_key', OnCallService::dedupKey('dns.dnssec.broken', 'dns_zone', $zone->id))->get();
    expect($zone->refresh()->dnssec_check['state'])->toBe('mismatch')
        ->and(dnssecFinding($service)['level'])->toBe('bad')
        ->and($alerts)->toHaveCount(1)->and($alerts->first()->occurrences)->toBe(2);
});

it('closes the alert once the registry holds the right DS again', function () {
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);
    $published = [];
    Http::fake(function () use (&$published) {
        return Http::response(['Status' => 0, 'Answer' => array_map(fn (string $data) => ['name' => 'shop.cz.', 'type' => 43, 'TTL' => 300, 'data' => $data], $published)]);
    });
    app(DnssecWatch::class)->run();
    $published = ['12345 13 2 abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789']; // the customer published the DS at the registry

    app(DnssecWatch::class)->run();

    $alert = OnCallAlert::query()->where('dedup_key', OnCallService::dedupKey('dns.dnssec.broken', 'dns_zone', $zone->id))->first();
    expect($zone->refresh()->dnssec_check['state'])->toBe('ok')->and($alert->state)->toBe(OnCallAlert::RESOLVED);
});

it('is unknown, never ok, when the parent DS cannot be read', function () {
    Http::fake(['https://cloudflare-dns.com/*' => fn () => throw new ConnectionException('resolver down')]);
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);
    $service = dnssecWebService($org);

    $result = app(DnssecWatch::class)->run();

    $finding = dnssecFinding($service);
    expect($result)->toMatchArray(['broken' => 0, 'unknown' => 1])
        ->and($zone->refresh()->dnssec_check['state'])->toBe('unknown')
        ->and($finding['level'])->toBe('warn')->and($finding['level'])->not->toBe('ok')
        ->and(OnCallAlert::query()->count())->toBe(0); // not knowing is not paging
});

it('is unknown when the resolver answers with a failure status or we hold no DS of our own', function () {
    parentDs([], 2); // SERVFAIL
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);

    app(DnssecWatch::class)->run();
    expect($zone->refresh()->dnssec_check['state'])->toBe('unknown');

    $zone->forceFill(['dnssec_ds' => []])->save();
    parentDs(['12345 13 2 abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789']);
    app(DnssecWatch::class)->run();
    expect($zone->refresh()->dnssec_check['state'])->toBe('unknown')->and($zone->dnssec_check['reason'])->toContain('our signing key');
});

it('refuses to ask a resolver that sits on a private address', function () {
    FakeHostResolver::$hosts['cloudflare-dns.com'] = ['10.0.0.9'];
    Http::fake();
    [, $org] = $this->customerWithOrganization();
    $zone = dnssecZone($org);

    app(DnssecWatch::class)->run();

    Http::assertNothingSent();
    expect($zone->refresh()->dnssec_check['state'])->toBe('unknown');
});

it('leaves unsigned zones alone and is registered as a collector', function () {
    Http::fake();
    [, $org] = $this->customerWithOrganization();
    DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'plain.cz', 'provider' => 'powerdns', 'serial' => 1, 'version' => 1,
        'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz'], 'dnssec' => false]);

    expect(app(DnssecWatch::class)->run())->toMatchArray(['checked' => 0])
        ->and(CollectorHeartbeat::EXPECTED)->toHaveKey('dns.dnssec');
    Http::assertNothingSent();
});

it('runs from the nightly drift command and records its own ledger key', function () {
    parentDs([]);
    [, $org] = $this->customerWithOrganization();
    dnssecZone($org);

    $this->artisan('onhost:dns:drift')->assertSuccessful();

    expect(app(AutomationLedger::class)->last('dns.dnssec')['stats'])->toMatchArray(['checked' => 1, 'broken' => 1]);
});
