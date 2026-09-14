<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Providers\PowerDns\PowerDnsProvider;

function pdnsAdapter(): PowerDnsProvider
{
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], ['provider' => 'powerdns', 'name' => 'PowerDNS hidden', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active', 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz'], 'also_notify' => ['10.0.0.2:53']]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('powerdns', PowerDnsProvider::class);

    return $registry->forInstance($instance);
}

it('creates a zone idempotently and applies a change batch as one RRset PATCH followed by NOTIFY', function () {
    Http::fake([
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz.?rrsets=false' => Http::sequence()->push(['error' => 'Not Found'], 404)->push(['name' => 'example.cz.', 'serial' => 2026090601])->push(['name' => 'example.cz.', 'serial' => 2026090602]),
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones' => Http::response(['name' => 'example.cz.', 'serial' => 2026090601], 201),
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz./metadata' => Http::response(['kind' => 'ALSO-NOTIFY'], 201),
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz.' => Http::response(['name' => 'example.cz.', 'rrsets' => [
            ['name' => 'example.cz.', 'type' => 'A', 'ttl' => 3600, 'records' => [['content' => '89.187.160.5', 'disabled' => false]]],
            ['name' => 'example.cz.', 'type' => 'MX', 'ttl' => 3600, 'records' => [['content' => '10 mail.onhost.cz.', 'disabled' => false]]],
            ['name' => 'www.example.cz.', 'type' => 'CNAME', 'ttl' => 3600, 'records' => [['content' => 'example.cz.', 'disabled' => false]]],
        ]]),
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz./notify' => Http::response([], 200),
    ]);
    $adapter = pdnsAdapter();
    expect($adapter->createZone('example.cz')->alreadyExisted)->toBeFalse();
    $records = $adapter->listRecords('example.cz');
    expect($records)->toHaveCount(3)->and($records[1])->toBe(['name' => '@', 'type' => 'MX', 'content' => 'mail.onhost.cz.', 'ttl' => 3600, 'prio' => 10]);

    $result = $adapter->applyChanges('example.cz', [
        ['op' => 'update', 'record' => ['name' => '@', 'type' => 'A', 'content' => '89.187.160.9', 'ttl' => 300], 'previous' => ['name' => '@', 'type' => 'A', 'content' => '89.187.160.5', 'ttl' => 3600]],
        ['op' => 'add', 'record' => ['name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 include:_spf.onhost.cz -all', 'ttl' => 600]],
        ['op' => 'delete', 'record' => ['name' => 'www', 'type' => 'CNAME', 'content' => 'example.cz.', 'ttl' => 3600]],
    ]);
    expect($result->data['rrsets'])->toBe(3)->and($result->data['serial'])->toBe(2026090601);
    Http::assertSent(function ($r) {
        if ($r->method() !== 'PATCH') {
            return false;
        }
        $sets = collect($r['rrsets'])->keyBy(fn ($s) => $s['name'].'|'.$s['type']);

        return $sets['example.cz.|A']['records'][0]['content'] === '89.187.160.9' && $sets['example.cz.|A']['ttl'] === 300
            && $sets['example.cz.|TXT']['records'][0]['content'] === '"v=spf1 include:_spf.onhost.cz -all"'
            && $sets['www.example.cz.|CNAME']['changetype'] === 'DELETE' && $r->hasHeader('X-API-Key', 'pdns-key');
    });
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/notify'));
});

it('reports DNSSEC status with DS records', function () {
    Http::fake([
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz./cryptokeys' => Http::response([['id' => 1, 'keytype' => 'csk', 'active' => true, 'algorithm' => 'ECDSAP256SHA256', 'bits' => 256, 'ds' => ['12345 13 2 ABCDEF', '12345 13 4 0123']]]),
        'pdns.mgmt.test:8081/api/v1/servers/localhost/zones/example.cz.?rrsets=false' => Http::response(['name' => 'example.cz.', 'dnssec' => true]),
    ]);
    $status = pdnsAdapter()->dnssecStatus('example.cz');
    expect($status['enabled'])->toBeTrue()->and($status['ds'])->toHaveCount(2)->and($status['keys'][0]['type'])->toBe('csk');
});
