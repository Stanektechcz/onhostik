<?php

declare(strict_types=1);

use Database\Seeders\DnsTemplateSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsChange;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Outbox\OutboxPublisher;

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

it('bounds the size of a zone and of its waiting list', function () {
    pdnsFake('limity.cz');
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $zone = $dns->ensureZone($org->id, 'limity.cz', $ctx, null, 'web_basic', ['ipv4' => '89.187.160.5']);
    $has = $zone->records()->count();

    // it used to take records without end: every one a row here, and a commit pushes the whole zone to the provider
    config(['onhost.dns.max_records_per_zone' => $has + 2, 'onhost.dns.max_pending_changes' => 50]);
    $dns->stageAdd($zone, ['name' => 'a', 'type' => 'A', 'content' => '203.0.113.1'], $ctx);
    $dns->stageAdd($zone, ['name' => 'b', 'type' => 'A', 'content' => '203.0.113.2'], $ctx);
    expect(fn () => $dns->stageAdd($zone, ['name' => 'c', 'type' => 'A', 'content' => '203.0.113.3'], $ctx))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('dns_record_limit'));

    config(['onhost.dns.max_records_per_zone' => 500, 'onhost.dns.max_pending_changes' => 2]);
    expect(fn () => $dns->stageAdd($zone, ['name' => 'd', 'type' => 'A', 'content' => '203.0.113.4'], $ctx))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('dns_pending_limit'));
    expect(DnsChange::query()->where('zone_id', $zone->id)->where('state', 'pending')->count())->toBe(2);
});

it('compares every zone with what its provider serves, tells operations once and keeps showing it in the doctor', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $served = []; // what the provider answers when asked for the zone (fakes stack: the first one registered wins, so it is one handler with a switch)
    pdnsFake('srovnani.cz', [], function (Request $r) use (&$served) {
        return $r->method() === 'GET' ? Http::response(['name' => 'srovnani.cz.', 'rrsets' => $served]) : Http::response([], 204);
    });
    $zone = $dns->ensureZone($org->id, 'srovnani.cz', $ctx, null, null);
    $dns->stageAdd($zone, ['name' => '@', 'type' => 'A', 'content' => '89.187.160.5'], $ctx);
    $dns->stageAdd($zone, ['name' => 'www', 'type' => 'A', 'content' => '89.187.160.5'], $ctx);
    $dns->commit($zone, $ctx, 'first records');
    expect($zone->records()->count())->toBe(2);
    // what the provider serves tonight: our apex record is gone, and a record nobody here knows stands in the zone
    $rrset = fn (string $name, string $ip) => ['name' => $name, 'type' => 'A', 'ttl' => 3600, 'records' => [['content' => $ip, 'disabled' => false]]];
    $served = [$rrset('www.srovnani.cz.', '89.187.160.5'), $rrset('zapomenuty.srovnani.cz.', '198.51.100.9')];

    $stats = $dns->checkDrift();

    expect($stats)->toMatchArray(['checked' => 1, 'drifted' => 1, 'errors' => 0]);
    $zone->refresh();
    expect($zone->drift_checked_at)->not->toBeNull()->and($zone->drift['missing_at_provider'])->toBe(1)->and($zone->drift['unknown_at_provider'])->toBe(1)
        ->and(implode(' ', $zone->drift['sample']))->toContain('zapomenuty A');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'DNS zóna se liší od poskytovatele: srovnani.cz')->count())->toBe(1);

    // the next night: still different, said once; the doctor keeps showing it
    $dns->checkDrift();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('title', 'DNS zóna se liší od poskytovatele: srovnani.cz')->count())->toBe(1);
    Artisan::call('onhost:doctor', ['--json' => true]);
    $check = collect(json_decode(trim(Artisan::output()), true)['checks'])->firstWhere('check', 'every DNS zone equals what its provider serves');
    expect($check['status'])->toBe('WARN')->and($check['detail'])->toContain('srovnani.cz');
});

it('says that a zone is not at the provider at all, and keeps a comparison that could not be made apart from one that found nothing', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $mode = 'ok'; // what the provider does tonight: answers, has lost the zone, or refuses the key (one handler with a switch: fakes stack)
    $created = false;
    $base = 'pdns.mgmt.test:8081/api/v1/servers/localhost/zones';
    $answer = function (array $body) use (&$mode) { // by reference: an arrow function would keep the value it saw when it was made
        return match ($mode) {
            'gone' => Http::response(['error' => 'Not Found'], 404),
            'refused' => Http::response(['error' => 'Unauthorized'], 401),
            default => Http::response($body),
        };
    };
    Http::fake([
        "{$base}/zmizela.cz.?rrsets=false" => function () use (&$created, $answer) {
            return $created ? $answer(['name' => 'zmizela.cz.', 'serial' => 2026090601]) : Http::response(['error' => 'Not Found'], 404);
        },
        $base => function () use (&$created) {
            $created = true;

            return Http::response(['name' => 'zmizela.cz.', 'serial' => 2026090601], 201);
        },
        "{$base}/zmizela.cz./metadata" => Http::response(['kind' => 'ALSO-NOTIFY'], 201),
        "{$base}/zmizela.cz./notify" => Http::response([], 200),
        "{$base}/zmizela.cz." => fn (Request $r) => $r->method() === 'GET' ? $answer(['name' => 'zmizela.cz.', 'rrsets' => [['name' => 'zmizela.cz.', 'type' => 'A', 'ttl' => 3600, 'records' => [['content' => '89.187.160.5', 'disabled' => false]]]]]) : Http::response([], 204),
    ]);
    $zone = $dns->ensureZone($org->id, 'zmizela.cz', $ctx, null, null);
    $dns->stageAdd($zone, ['name' => '@', 'type' => 'A', 'content' => '89.187.160.5'], $ctx);
    $dns->commit($zone, $ctx, 'first record');
    expect($dns->checkDrift())->toMatchArray(['checked' => 1, 'drifted' => 0, 'errors' => 0]);

    // the provider refuses the key: nothing is known about the zone tonight — that is not "the zone equals", and not "the zone is gone"
    $mode = 'refused';
    expect($dns->checkDrift())->toMatchArray(['checked' => 0, 'drifted' => 0, 'errors' => 1]);
    $zone->refresh();
    expect($zone->getAttribute('drift_error'))->toBe('AUTH')->and($zone->drift)->toBeNull();
    Artisan::call('onhost:doctor', ['--json' => true]);
    $checks = collect(json_decode(trim(Artisan::output()), true)['checks']);
    expect($checks->firstWhere('check', 'every DNS zone could be compared with its provider')['detail'])->toContain('zmizela.cz (AUTH)');
    expect($checks->firstWhere('check', 'every DNS zone equals what its provider serves')['status'])->toBe('OK');

    // somebody deleted the zone at the provider: it used to count as a comparison that failed, and nobody heard of it
    $mode = 'gone';
    expect($dns->checkDrift())->toMatchArray(['checked' => 1, 'drifted' => 1, 'errors' => 0]);
    $zone->refresh();
    expect($zone->drift)->toMatchArray(['zone_missing' => true, 'missing_at_provider' => 1])->and($zone->getAttribute('drift_error'))->toBeNull();
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('title', 'DNS zóna se liší od poskytovatele: zmizela.cz')->sole();
    expect($note->body)->toBe('zóna u poskytovatele neexistuje')->and($note->severity)->toBe('hot');

    // and when it is back and equal, the zone is clean again
    $mode = 'ok';
    expect($dns->checkDrift())->toMatchArray(['checked' => 1, 'drifted' => 0, 'errors' => 0]);
    expect($zone->refresh()->drift)->toBeNull();
});

it('finds no difference in a zone the provider serves exactly what it was sent: TXT in wire form, host names with a dot, one TTL a set', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $held = []; // the sets as the server holds them: whatever was sent to it, in the form it was sent
    pdnsFake('shoda.cz', [], function (Request $r) use (&$held) {
        if ($r->method() !== 'PATCH') {
            return Http::response(['name' => 'shoda.cz.', 'rrsets' => array_values($held)]);
        }
        foreach ($r['rrsets'] as $set) {
            if ($set['changetype'] === 'DELETE') {
                unset($held[$set['name'].'|'.$set['type']]);
            } else {
                $held[$set['name'].'|'.$set['type']] = ['name' => $set['name'], 'type' => $set['type'], 'ttl' => $set['ttl'], 'records' => $set['records']];
            }
        }

        return Http::response([], 204);
    });
    // SPF, DMARC, MX, CNAME and CAA from the template — and a set of two A records the customer gave two TTLs
    $zone = $dns->ensureZone($org->id, 'shoda.cz', $ctx, null, 'web_mail', ['ipv4' => '89.187.160.5', 'mail_host' => 'mail.onhost.cz', 'spf_include' => '_spf.onhost.cz']);
    $dns->stageAdd($zone, ['name' => 'lb', 'type' => 'A', 'content' => '203.0.113.10', 'ttl' => 300], $ctx);
    $dns->stageAdd($zone, ['name' => 'lb', 'type' => 'A', 'content' => '203.0.113.11', 'ttl' => 3600], $ctx);
    $dns->commit($zone, $ctx, 'two addresses');
    expect($held['shoda.cz.|TXT']['records'][0]['content'])->toStartWith('"v=spf1')->and(count($held['lb.shoda.cz.|A']['records']))->toBe(2);

    // a TXT value read back quoted matched nothing, and a set has ONE TTL on the wire: both were reported as a difference every night
    expect($dns->checkDrift())->toMatchArray(['checked' => 1, 'drifted' => 0, 'errors' => 0]);
    expect($zone->refresh()->drift)->toBeNull();

    // a TTL somebody changed at the provider is still a difference
    $held['www.shoda.cz.|CNAME']['ttl'] = 60;
    expect($dns->checkDrift())->toMatchArray(['checked' => 1, 'drifted' => 1, 'errors' => 0]);
    expect(implode(' ', $zone->refresh()->drift['sample']))->toContain('www CNAME');
});

it('makes the provider serve what the platform holds: the repair after a difference was found, and after the zone was lost', function () {
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $held = []; // the sets as the server holds them
    $exists = false;
    $base = 'pdns.mgmt.test:8081/api/v1/servers/localhost/zones';
    Http::fake([
        "{$base}/oprava.cz.?rrsets=false" => function () use (&$exists) {
            return $exists ? Http::response(['name' => 'oprava.cz.', 'serial' => 2026092001]) : Http::response(['error' => 'Not Found'], 404);
        },
        $base => function () use (&$exists) {
            $exists = true;

            return Http::response(['name' => 'oprava.cz.', 'serial' => 2026092001], 201);
        },
        "{$base}/oprava.cz./metadata" => Http::response(['kind' => 'ALSO-NOTIFY'], 201),
        "{$base}/oprava.cz./notify" => Http::response([], 200),
        "{$base}/oprava.cz." => function (Request $r) use (&$held, &$exists) {
            if (! $exists) {
                return Http::response(['error' => 'Not Found'], 404);
            }
            if ($r->method() !== 'PATCH') {
                return Http::response(['name' => 'oprava.cz.', 'rrsets' => array_values($held)]);
            }
            foreach ($r['rrsets'] as $set) {
                if ($set['changetype'] === 'DELETE') {
                    unset($held[$set['name'].'|'.$set['type']]);
                } else {
                    $held[$set['name'].'|'.$set['type']] = ['name' => $set['name'], 'type' => $set['type'], 'ttl' => $set['ttl'], 'records' => $set['records']];
                }
            }

            return Http::response([], 204);
        },
    ]);
    $zone = $dns->ensureZone($org->id, 'oprava.cz', $ctx, null, 'web_mail', ['ipv4' => '89.187.160.5', 'mail_host' => 'mail.onhost.cz', 'spf_include' => '_spf.onhost.cz']);
    $sent = $held;

    // at the provider: the SPF record is gone, the TTL of `www` was changed, and a record nobody here knows stands in the zone
    unset($held['oprava.cz.|TXT']);
    $held['www.oprava.cz.|CNAME']['ttl'] = 60;
    $held['zapomenuty.oprava.cz.|A'] = ['name' => 'zapomenuty.oprava.cz.', 'type' => 'A', 'ttl' => 3600, 'records' => [['content' => '198.51.100.9', 'disabled' => false]]];
    expect($dns->checkDrift())->toMatchArray(['drifted' => 1]);

    $this->actingAs($user, 'sanctum');
    $this->getJson("/v1/dns/zones/{$zone->id}")->assertOk()->assertJsonPath('data.drift.differs', true)->assertJsonPath('data.drift.summary.unknown_at_provider', 2);
    $this->postJson("/v1/dns/zones/{$zone->id}/republish", ['reason' => 'po nočním srovnání'])->assertOk()
        ->assertJson(['created' => false, 'added' => 1, 'removed' => 1, 'updated' => 1, 'verified' => true, 'left' => 0]);

    // the record whose TTL differed is still there (sent as a row added and a row removed, the server would have dropped it)
    expect($held)->toEqual($sent)->and($zone->refresh()->drift)->toBeNull();
    $this->getJson("/v1/dns/zones/{$zone->id}")->assertOk()->assertJsonPath('data.drift.differs', false);

    // somebody deleted the whole zone at the provider: it is created again and filled with what the platform holds
    $exists = false;
    $held = [];
    expect($dns->checkDrift())->toMatchArray(['drifted' => 1]);
    expect($zone->refresh()->drift['zone_missing'])->toBeTrue();
    $result = $dns->republish($zone, $ctx, 'zóna u poskytovatele zmizela');
    expect($result)->toMatchArray(['created' => true, 'verified' => true, 'dnssec_attention' => false])->and($result['added'])->toBe($zone->records()->count());
    expect($held)->toEqual($sent)->and($zone->refresh()->drift)->toBeNull();

    // somebody from another organization may neither look nor publish
    [$reader] = $this->customerWithOrganization();
    $this->actingAs($reader, 'sanctum');
    $this->postJson("/v1/dns/zones/{$zone->id}/republish")->assertForbidden();
});
