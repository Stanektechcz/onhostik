<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\RecordValidator;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * Several parts of the platform publish records into the same zone: the website saga writes the site's A and AAAA,
 * the mail saga writes MX, SPF, DMARC, DKIM and the autoconfig records, and a web service writes those as well when
 * its first mailbox is made. `syncSystemRecords` deletes every system record it was not asked for — which is what
 * unpairing a domain needs — and those three asked without saying which records were theirs. So the second one to
 * run **deleted the first one's**: making the first mailbox on a web hosting whose domain is in our DNS took the
 * website off the internet, and publishing the site's records again stopped its mail. Every publisher now names
 * what it owns, and adopts the records of its own kind that were written before it had a name.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The zone of a customer's domain in our own DNS, with the site's records already published by the website saga. */
function zoneWithSiteRecords(string $name = 'shop.cz', ?string $organizationId = null): DnsZone
{
    $zone = DnsZone::query()->create(['organization_id' => $organizationId, 'name' => $name, 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id,
        'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]);
    foreach ([['@', '203.0.113.10'], ['www', '203.0.113.10']] as [$name2, $ip]) {
        DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => $name2, 'type' => 'A', 'content' => $ip, 'ttl' => 600, 'managed_by' => 'system', 'comment' => null]);
    }

    return $zone;
}

/** PowerDNS that accepts whatever is published, and the ISPConfig that makes the mail domain. */
function zonePublishPanel(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'pdns.mgmt.test')) {
            return Http::response(['name' => 'shop.cz.', 'serial' => 2]);
        }

        return Http::response(match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
            'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-own'],
            'mail_domain_add' => ['code' => 'ok', 'message' => '', 'response' => 909],
            'client_get_by_username' => ['code' => 'ok', 'message' => '', 'response' => ['client_id' => 3, 'username' => 'onh_1']],
            'mail_user_add' => ['code' => 'ok', 'message' => '', 'response' => 5001],
            'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

/** What the zone holds now, as `name|type` strings. @return list<string> */
function zoneShape(DnsZone $zone): array
{
    return $zone->records()->get()->map(fn (DnsRecord $r) => $r->name.'|'.$r->type)->sort()->values()->all();
}

it('does not take the website off the internet when the first mailbox is made', function () {
    zonePublishPanel();
    [$user, $org] = $this->customerWithOrganization();
    $zone = zoneWithSiteRecords('shop.cz', $org->id);
    $service = featureWebService($org, 'ispconfig');

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'mailbox.create', $this->contextFor($user, $org), 'own-1', ['address' => 'info@shop.cz', 'password' => 'Correct-Horse-Battery-9', 'quota_mb' => 1024]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''));
    $shape = zoneShape($zone->refresh());
    expect($shape)->toContain('@|A')->toContain('www|A')   // the site still answers
        ->and($shape)->toContain('@|MX')->toContain('_dmarc|TXT'); // and the mail records were added next to them
});

it('leaves the mail records alone when the site publishes its own again', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'MX', 'content' => 'mail.onhost.cz.', 'prio' => 10, 'ttl' => 3600, 'managed_by' => 'system', 'comment' => 'mail:shop.cz']);
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'TXT', 'content' => 'v=spf1 mx -all', 'ttl' => 3600, 'managed_by' => 'system', 'comment' => 'mail:shop.cz']);

    app(DnsService::class)->syncSystemRecords($zone, [
        ['name' => '@', 'type' => 'A', 'content' => '203.0.113.11', 'ttl' => 600],
        ['name' => 'www', 'type' => 'A', 'content' => '203.0.113.11', 'ttl' => 600],
    ], CommandContext::system('test'), 'web hosting moved', 'web:svc-1');

    $shape = zoneShape($zone->refresh());
    expect($shape)->toContain('@|MX')->toContain('@|TXT')      // the mail of the domain is not the website's to remove
        ->and($zone->records()->where('type', 'A')->where('name', '@')->value('content'))->toBe('203.0.113.11');
});

it('adopts the records of its own kind that were published before it had a name', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();

    // the site's address changed: the old records carry no owner, and must be replaced, not doubled
    app(DnsService::class)->syncSystemRecords($zone, [
        ['name' => '@', 'type' => 'A', 'content' => '203.0.113.99', 'ttl' => 600],
        ['name' => 'www', 'type' => 'A', 'content' => '203.0.113.99', 'ttl' => 600],
    ], CommandContext::system('test'), 'web hosting moved', 'web:svc-1');

    expect($zone->refresh()->records()->where('type', 'A')->count())->toBe(2)
        ->and($zone->records()->where('type', 'A')->pluck('content')->unique()->all())->toBe(['203.0.113.99']);
});

it('still removes exactly what it owns when a domain is unpaired', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => 'app', 'type' => 'A', 'content' => '203.0.113.50', 'ttl' => 600, 'managed_by' => 'system', 'comment' => 'service:svc-9']);

    app(DnsService::class)->syncSystemRecords($zone, [], CommandContext::system('test'), 'unpairing', 'service:svc-9');

    expect(zoneShape($zone->refresh()))->toBe(['@|A', 'www|A']); // its own record is gone, nobody else's is
});

it('takes over a record of the kind it publishes, instead of leaving two that split the domain', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'MX', 'content' => 'mail.onhost.cz.', 'prio' => 10, 'ttl' => 3600, 'managed_by' => 'system', 'comment' => 'mail:shop.cz']);

    // a website publisher that publishes an MX of its own does not get to keep the mail publisher's
    app(DnsService::class)->syncSystemRecords($zone, [['name' => '@', 'type' => 'MX', 'content' => 'mail.elsewhere.test.', 'prio' => 10, 'ttl' => 3600]], CommandContext::system('test'), 'other', 'web:svc-1');

    $contents = $zone->refresh()->records()->where('type', 'MX')->pluck('content')->sort()->values()->all();
    expect($contents)->toBe(['mail.elsewhere.test.']); // one MX for the domain, the newest publisher's — never two that split its mail
});

it('keeps a record the customer made themselves', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'MX', 'content' => 'mail.zakaznik.cz.', 'prio' => 5, 'ttl' => 3600, 'managed_by' => 'customer']);

    app(DnsService::class)->syncSystemRecords($zone, [['name' => '@', 'type' => 'MX', 'content' => 'mail.onhost.cz.', 'prio' => 10, 'ttl' => 3600]], CommandContext::system('test'), 'mail', 'mail:shop.cz');

    expect($zone->refresh()->records()->where('type', 'MX')->pluck('content')->all())->toBe(['mail.zakaznik.cz.']);
});

it('never touches a service that is not in this zone', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords();
    $other = zoneWithSiteRecords('jinyweb.cz');

    app(DnsService::class)->syncSystemRecords($zone, [['name' => '@', 'type' => 'A', 'content' => '203.0.113.77', 'ttl' => 600]], CommandContext::system('test'), 'web', 'web:svc-1');

    expect($other->refresh()->records()->where('type', 'A')->pluck('content')->unique()->all())->toBe(['203.0.113.10'])
        ->and(Service::query()->count())->toBe(0);
});

it('builds only records its own DNS accepts', function () {
    $validator = app(RecordValidator::class);
    $records = [
        ...MailSettings::records('shop.cz', 'mail.onhost.cz', 'onhost202609', "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0\n-----END PUBLIC KEY-----"),
        ['name' => '@', 'type' => 'A', 'content' => '203.0.113.10', 'ttl' => 600],
        ['name' => 'www', 'type' => 'AAAA', 'content' => '2001:db8::10', 'ttl' => 600],
    ];

    // the autoconfig SRV record was built with a priority inside its content, which our own validator refuses — and
    // the saga records a DNS error and carries on, so the mailbox was made and the domain got NO mail records at all
    foreach ($records as $record) {
        expect(fn () => $validator->normalize($record, 'shop.cz'))->not->toThrow(DomainError::class, $record['type'].' '.$record['name']);
    }
    expect(array_column(MailSettings::records('shop.cz'), 'type'))->toContain('MX')->toContain('SRV')->toContain('CNAME');
});

it('lets a site move into a parked domain: the parking address and the www alias give way', function () {
    zonePublishPanel();
    $zone = zoneWithSiteRecords('parkovana.cz');
    $zone->records()->create(['name' => 'www', 'type' => 'CNAME', 'content' => 'parkovana.cz.', 'ttl' => 3600, 'managed_by' => 'system', 'comment' => null]);

    app(DnsService::class)->syncSystemRecords($zone, [
        ['name' => '@', 'type' => 'A', 'content' => '203.0.113.20', 'ttl' => 600],
        ['name' => 'www', 'type' => 'A', 'content' => '203.0.113.20', 'ttl' => 600],
    ], CommandContext::system('test'), 'web hosting', 'web:svc-2');

    // a name is either a CNAME or everything else: publishing the site's own address takes the alias with it
    expect(zoneShape($zone->refresh()))->toBe(['@|A', 'www|A'])
        ->and($zone->records()->where('name', 'www')->value('content'))->toBe('203.0.113.20');
});
