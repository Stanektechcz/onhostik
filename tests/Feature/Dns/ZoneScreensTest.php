<?php

declare(strict_types=1);

use Database\Seeders\DnsTemplateSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/*
 * What the panel's DNS screens call (TASK-0056): standalone zones (create, list, delete), the version list, the rollback and the export.
 * The PowerDNS API is faked; nothing leaves the process.
 */

/** @var list<array{string,string}> $zoneScreenCalls method + path of every call the provider got */
function zoneScreensFake(array &$calls): void
{
    $created = [];
    Http::fake(['pdns.mgmt.test:8081/*' => function (Request $r) use (&$calls, &$created) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $calls[] = [$r->method(), $path];
        if ($r->method() === 'POST' && str_ends_with($path, '/zones')) {
            $name = (string) ($r->data()['name'] ?? '');
            $created[$name] = true;

            return Http::response(['name' => $name, 'serial' => 2026100401], 201);
        }
        if ($r->method() === 'GET' && str_contains($r->url(), 'rrsets=false')) {
            $name = basename($path);

            return isset($created[$name]) ? Http::response(['name' => $name, 'serial' => 2026100401]) : Http::response(['error' => 'Not Found'], 404);
        }
        if ($r->method() === 'GET') {
            return Http::response(['name' => basename($path), 'rrsets' => []]);
        }
        if ($r->method() === 'DELETE' || $r->method() === 'PATCH') {
            return Http::response([], 204);
        }

        return Http::response([], 200);
    }]);
}

beforeEach(function () {
    $this->seed(DnsTemplateSeeder::class);
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';
    ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], [
        'provider' => 'powerdns', 'name' => 'PowerDNS hidden primary', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active',
        'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0',
    ]);
    Http::preventStrayRequests();
});

function zoneScreensStepUp(object $user): void
{
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
}

it('creates a standalone zone, lists it and refuses a name another organization already holds', function () {
    $calls = [];
    zoneScreensFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    [$other, $otherOrg] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum');
    $created = $this->withHeaders(['X-Organization' => $org->id, 'Idempotency-Key' => 'zone-1'])->postJson('/v1/dns/zones', ['name' => 'Samostatna.CZ'])->assertCreated();
    $created->assertJsonPath('name', 'samostatna.cz')->assertJsonPath('nameservers', ['ns1.onhost.cz', 'ns2.onhost.cz']);
    $list = $this->withHeaders(['X-Organization' => $org->id])->getJson('/v1/dns/zones')->assertOk();
    expect(collect($list->json('data'))->pluck('name')->all())->toBe(['samostatna.cz']);
    expect(collect($calls)->contains(fn ($c) => $c[0] === 'POST' && str_ends_with($c[1], '/zones')))->toBeTrue();

    $this->actingAs($other, 'sanctum');
    $this->withHeaders(['X-Organization' => $otherOrg->id, 'Idempotency-Key' => 'zone-2'])->postJson('/v1/dns/zones', ['name' => 'samostatna.cz'])->assertStatus(409)->assertJsonPath('error', 'dns_zone_taken');
    expect(collect($this->withHeaders(['X-Organization' => $otherOrg->id])->getJson('/v1/dns/zones')->json('data'))->pluck('name')->all())->toBe([]);
});

it('lists the versions of a zone and rolls back to one — only after a fresh step-up', function () {
    $calls = [];
    zoneScreensFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $zone = $dns->ensureZone($org->id, 'verze.cz', $ctx, null, null);
    $dns->stageAdd($zone, ['name' => 'api', 'type' => 'A', 'content' => '203.0.113.7', 'ttl' => 300], $ctx, 'api');
    $dns->commit($zone, $ctx, 'add api');
    $this->actingAs($user, 'sanctum');
    $headers = ['X-Organization' => $org->id];

    $versions = $this->withHeaders($headers)->getJson("/v1/dns/zones/{$zone->id}/versions")->assertOk();
    expect(collect($versions->json('data'))->pluck('version')->sort()->values()->all())->toBe([1, 2]);
    expect(collect($versions->json('data'))->firstWhere('version', 2)['reason'])->toBe('add api');

    $patchesBefore = collect($calls)->where(0, 'PATCH')->count();
    $this->withHeaders($headers + ['Idempotency-Key' => 'rb-1'])->postJson("/v1/dns/zones/{$zone->id}/rollback", ['version' => 1])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect(collect($calls)->where(0, 'PATCH')->count())->toBe($patchesBefore)->and($zone->fresh()->version)->toBe(2);

    zoneScreensStepUp($user);
    $this->withHeaders($headers + ['Idempotency-Key' => 'rb-2'])->postJson("/v1/dns/zones/{$zone->id}/rollback", ['version' => 99])->assertStatus(404)->assertJsonPath('error', 'dns_version_not_found');
    $this->withHeaders($headers + ['Idempotency-Key' => 'rb-3'])->postJson("/v1/dns/zones/{$zone->id}/rollback", ['version' => 1])->assertOk()->assertJsonPath('version', 3);
    expect($zone->fresh()->records()->where('name', 'api')->exists())->toBeFalse();
    $this->withHeaders($headers + ['Idempotency-Key' => 'rb-4'])->postJson("/v1/dns/zones/{$zone->id}/rollback", ['version' => 1])->assertStatus(409)->assertJsonPath('error', 'dns_rollback_noop');
});

it('exports the zone as a zone file for its own organization only', function () {
    $calls = [];
    zoneScreensFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    [$stranger] = $this->customerWithOrganization();
    $zone = app(DnsService::class)->ensureZone($org->id, 'export.cz', $this->contextFor($user, $org), null, null);

    $this->actingAs($user, 'sanctum');
    $response = $this->withHeaders(['X-Organization' => $org->id])->get("/v1/dns/zones/{$zone->id}/export")->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('export.cz.zone');
    expect($response->getContent())->toContain('@ IN SOA ns1.onhost.cz.')->toContain('@ 3600 IN NS ns2.onhost.cz.');

    $this->actingAs($stranger, 'sanctum');
    expect($this->get("/v1/dns/zones/{$zone->id}/export")->status())->toBeIn([403, 404]);
});

it('deletes a standalone zone with a reason after a step-up, and refuses one a live domain is delegated to', function () {
    $calls = [];
    zoneScreensFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($user, $org);
    $dns = app(DnsService::class);
    $standalone = $dns->ensureZone($org->id, 'zrusit.cz', $ctx, null, null);
    $domain = Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => 'bezi.cz', 'fqdn_unicode' => 'bezi.cz', 'tld' => 'cz', 'state' => DomainStateMachine::ACTIVE, 'registrar_provider' => 'wedos', 'dns_provider' => 'powerdns', 'expires_at' => now()->addYear()]);
    $serving = $dns->ensureZone($org->id, 'bezi.cz', $ctx, $domain->id, null);
    $domain->forceFill(['dns_zone_id' => $serving->id])->save();
    $this->actingAs($user, 'sanctum');
    $headers = ['X-Organization' => $org->id];

    $this->withHeaders($headers + ['Idempotency-Key' => 'del-1'])->deleteJson("/v1/dns/zones/{$standalone->id}", ['reason' => 'už ji nepotřebuji'])->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect(DnsZone::query()->find($standalone->id))->not->toBeNull()->and(collect($calls)->where(0, 'DELETE')->count())->toBe(0);

    zoneScreensStepUp($user);
    $this->withHeaders($headers + ['Idempotency-Key' => 'del-2'])->deleteJson("/v1/dns/zones/{$standalone->id}", [])->assertStatus(422);
    $this->withHeaders($headers + ['Idempotency-Key' => 'del-3'])->deleteJson("/v1/dns/zones/{$serving->id}", ['reason' => 'omyl'])->assertStatus(409)->assertJsonPath('error', 'dns_zone_in_use')->assertJsonPath('domain', 'bezi.cz');
    expect(collect($calls)->where(0, 'DELETE')->count())->toBe(0)->and(DnsZone::query()->find($serving->id))->not->toBeNull();

    $this->withHeaders($headers + ['Idempotency-Key' => 'del-4'])->deleteJson("/v1/dns/zones/{$standalone->id}", ['reason' => 'už ji nepotřebuji'])->assertOk()->assertJsonPath('deleted', true);
    expect(DnsZone::query()->find($standalone->id))->toBeNull()->and(collect($calls)->where(0, 'DELETE')->count())->toBe(1);
    expect(collect($this->withHeaders($headers)->getJson('/v1/dns/zones')->json('data'))->pluck('name')->all())->toBe(['bezi.cz']);
});

it('does not delete the zone of another organization', function () {
    $calls = [];
    zoneScreensFake($calls);
    [$user, $org] = $this->customerWithOrganization();
    [$stranger, $strangerOrg] = $this->customerWithOrganization();
    $zone = app(DnsService::class)->ensureZone($org->id, 'cizi.cz', $this->contextFor($user, $org), null, null);
    zoneScreensStepUp($stranger);

    $this->actingAs($stranger, 'sanctum');
    expect($this->withHeaders(['X-Organization' => $strangerOrg->id, 'Idempotency-Key' => 'x-1'])->deleteJson("/v1/dns/zones/{$zone->id}", ['reason' => 'test'])->status())->toBeIn([403, 404]);
    expect(DnsZone::query()->find($zone->id))->not->toBeNull()->and(collect($calls)->where(0, 'DELETE')->count())->toBe(0);
});
