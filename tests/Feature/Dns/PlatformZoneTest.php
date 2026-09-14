<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Platform\Commands\CommandContext;

/*
 * Hosting subdomains (<label>.web.onhost.cz) live in a zone the platform owns. Every service keeps its own A/AAAA rows
 * there, tagged with `service:<id>`, so provisioning one site never touches another site's rows and termination removes
 * exactly its own. The customer's own records in the same zone (the wildcard, the apex) are never fought.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('keeps one hosting hostname per service inside a shared platform zone and removes it on termination', function () {
    [$user, $org] = $this->customerWithOrganization();
    config(['onhost.dns.platform_zones' => ['onhost.test']]);
    pdnsLab();
    pdnsZoneFake('onhost.test');
    $dns = app(DnsService::class);
    $actor = new CommandContext('user', $user->id, $org->id, null, '127.0.0.1', 'test', null, 'test');
    $zone = $dns->ensureZone($org->id, 'onhost.test', $actor, null, null);
    // the wildcard the operator keeps at the provider stays customer-managed
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '*.web', 'type' => 'A', 'content' => '198.51.100.9', 'ttl' => 3600, 'managed_by' => 'customer']);

    expect($dns->platformZoneFor('688mr1zw.web.onhost.test'))->not->toBeNull()
        ->and($dns->platformZoneFor('688mr1zw.web.onhost.test')[1])->toBe('688mr1zw.web')
        ->and($dns->platformZoneFor('onhost.test'))->toBeNull()
        ->and($dns->platformZoneFor('shop.cz'))->toBeNull();

    $dns->syncHostname($zone, '688mr1zw.web', '192.0.2.10', '2001:db8::10', $actor, 'service:srv_a', 'web hosting srv_a');
    $dns->syncHostname($zone, 'copy.web', '192.0.2.10', null, $actor, 'service:srv_b', 'web hosting srv_b');
    $rows = fn () => DnsZone::query()->findOrFail($zone->id)->records()->where('managed_by', 'system')->get()->map(fn (DnsRecord $r) => $r->name.' '.$r->type.' '.$r->content.' '.$r->comment)->sort()->values()->all();
    expect($rows())->toBe(['688mr1zw.web A 192.0.2.10 service:srv_a', '688mr1zw.web AAAA 2001:db8::10 service:srv_a', 'copy.web A 192.0.2.10 service:srv_b']);

    // a re-sync of the same service is a no-op; a moved node rewrites only that service's rows
    expect($dns->syncHostname($zone, '688mr1zw.web', '192.0.2.10', '2001:db8::10', $actor, 'service:srv_a', 'again'))->toBeNull();
    $dns->syncHostname($zone, '688mr1zw.web', '192.0.2.11', null, $actor, 'service:srv_a', 'moved');
    expect($rows())->toBe(['688mr1zw.web A 192.0.2.11 service:srv_a', 'copy.web A 192.0.2.10 service:srv_b']);

    // termination removes exactly the service's rows; the operator's wildcard and the other service stay
    $dns->syncHostname($zone, '688mr1zw.web', null, null, $actor, 'service:srv_a', 'terminated');
    expect($rows())->toBe(['copy.web A 192.0.2.10 service:srv_b'])
        ->and(DnsZone::query()->findOrFail($zone->id)->records()->where('name', '*.web')->where('managed_by', 'customer')->exists())->toBeTrue();
});
