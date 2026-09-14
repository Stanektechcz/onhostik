<?php

declare(strict_types=1);

use Database\Seeders\DnsTemplateSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsChange;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;

function dnsInstance(): ProviderInstance
{
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';

    return ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], [
        'provider' => 'powerdns', 'name' => 'PowerDNS hidden primary', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active',
        'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0',
    ]);
}

/** PowerDNS fixture for one zone: first existence check 404, then the zone exists. */
function pdnsFake(string $zone, array $rrsets = [], ?callable $zoneHandler = null): void
{
    $base = 'pdns.mgmt.test:8081/api/v1/servers/localhost/zones';
    Http::fake([
        "{$base}/{$zone}.?rrsets=false" => Http::sequence()->push(['error' => 'Not Found'], 404)->whenEmpty(Http::response(['name' => "{$zone}.", 'serial' => 2026090601])),
        $base => Http::response(['name' => "{$zone}.", 'serial' => 2026090601], 201),
        "{$base}/{$zone}./metadata" => Http::response(['kind' => 'ALSO-NOTIFY'], 201),
        "{$base}/{$zone}./notify" => Http::response([], 200),
        "{$base}/{$zone}." => $zoneHandler ?? Http::response(['name' => "{$zone}.", 'rrsets' => $rrsets]),
    ]);
}

beforeEach(function () {
    $this->seed(DnsTemplateSeeder::class);
    dnsInstance();
    Http::preventStrayRequests();
});

it('creates a zone from a template, skips records whose placeholders are missing and pushes the set in one batch', function () {
    pdnsFake('example.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $zone = $dns->ensureZone($org->id, 'Example.CZ.', $ctx, null, 'web_mail', ['ipv4' => '89.187.160.5', 'mail_host' => 'mail.onhost.cz', 'spf_include' => '_spf.onhost.cz']);

    expect($zone->name)->toBe('example.cz')->and($zone->state)->toBe('active')->and($zone->version)->toBe(1)->and($zone->serial)->toBeGreaterThan(0)->and($zone->nameservers)->toBe(['ns1.onhost.cz', 'ns2.onhost.cz']);
    $keys = $zone->records()->get()->map(fn ($r) => $r->name.'/'.$r->type)->all();
    expect($keys)->toContain('@/A', 'www/CNAME', '@/MX', 'mail/CNAME', '@/TXT', '_dmarc/TXT', '@/CAA')->not->toContain('@/AAAA');
    expect(collect($keys)->filter(fn ($k) => str_contains($k, '_domainkey')))->toBeEmpty();
    expect($zone->records()->where('type', 'MX')->first()->protected)->toBeTrue()->and($zone->records()->where('type', 'MX')->first()->prio)->toBe(10);
    expect($zone->versions()->count())->toBe(1);
    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && count($r['rrsets']) === 8 && $r->hasHeader('X-API-Key', 'pdns-key'));

    expect($dns->ensureZone($org->id, 'example.cz', $ctx, null, 'web_mail')->id)->toBe($zone->id);
    expect(collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'PATCH'))->toHaveCount(1);
});

it('stages, previews and commits atomically, refuses unconfirmed protected edits and rolls back to any version', function () {
    pdnsFake('example.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $zone = $dns->ensureZone($org->id, 'example.cz', $ctx, null, 'web_mail', ['ipv4' => '89.187.160.5', 'mail_host' => 'mail.onhost.cz', 'spf_include' => '_spf.onhost.cz']);
    $before = $zone->records()->count();

    expect(fn () => $dns->stageAdd($zone, ['name' => '@', 'type' => 'CNAME', 'content' => 'x.example.net'], $ctx))->toThrow(DomainError::class, 'apex');
    expect(fn () => $dns->stageAdd($zone, ['name' => 'api', 'type' => 'A', 'content' => '999.1.1.1'], $ctx))->toThrow(DomainError::class, 'IPv4');
    expect(fn () => $dns->stageAdd($zone, ['name' => 'api', 'type' => 'A', 'content' => '89.187.160.6', 'ttl' => 5], $ctx))->toThrow(DomainError::class, 'TTL');
    $dns->stageAdd($zone, ['name' => 'api.example.cz', 'type' => 'A', 'content' => '89.187.160.6', 'ttl' => 300], $ctx, 'api host');
    $mx = $zone->records()->where('type', 'MX')->firstOrFail();
    expect(fn () => $dns->stageDelete($zone, $mx, $ctx))->toThrow(DomainError::class, 'protected');
    $dns->stageDelete($zone, $mx, $ctx, 'moving mail', confirmProtected: true);

    $preview = $dns->preview($zone);
    expect($preview['changes'])->toHaveCount(2)->and(collect($preview['records'])->firstWhere('name', 'api')['content'])->toBe('89.187.160.6');

    $v2 = $dns->commit($zone, $ctx, 'add api, drop mx');
    expect($v2->version)->toBe(2)->and($zone->records()->count())->toBe($before)->and($zone->records()->where('type', 'MX')->exists())->toBeFalse()->and($zone->records()->where('name', 'api')->exists())->toBeTrue();
    expect(DnsChange::query()->where('zone_id', $zone->id)->where('state', 'committed')->count())->toBe(2);
    expect(fn () => $dns->commit($zone, $ctx))->toThrow(DomainError::class, 'no pending');

    $v3 = $dns->rollback($zone->fresh(), 1, $ctx);
    $zone->refresh();
    expect($v3->version)->toBe(3)->and($zone->version)->toBe(3)->and($zone->records()->where('type', 'MX')->exists())->toBeTrue()->and($zone->records()->where('name', 'api')->exists())->toBeFalse();
    expect(collect(Http::recorded())->filter(fn (array $p) => $p[0]->method() === 'PATCH'))->toHaveCount(3);
    expect($dns->export($zone))->toContain('@ IN SOA ns1.onhost.cz. hostmaster.example.cz.')->toContain('@ 3600 IN NS ns2.onhost.cz.')->toContain('10 mail.onhost.cz.');
});

it('leaves the canonical zone untouched and marks the batch failed when the provider rejects it (S39)', function () {
    pdnsFake('broken.cz', [], fn (Request $r) => $r->method() === 'PATCH' ? Http::response(['error' => 'RRset conflict'], 422) : Http::response(['name' => 'broken.cz.', 'rrsets' => []]));
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $zone = $dns->ensureZone($org->id, 'broken.cz', $ctx, null, 'external');
    $dns->stageAdd($zone, ['name' => '@', 'type' => 'A', 'content' => '89.187.160.7'], $ctx);

    expect(fn () => $dns->commit($zone, $ctx))->toThrow(ProviderException::class);
    $zone->refresh();
    expect($zone->records()->count())->toBe(0)->and($zone->version)->toBe(1)->and($zone->state)->toBe('active');
    expect(DnsChange::query()->where('zone_id', $zone->id)->value('state'))->toBe('failed');
});
