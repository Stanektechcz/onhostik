<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * A hosting is written into DNS by three publishers, each with a name of its own: the site's A and AAAA
 * (`web:<service>`), what the pairing of a further domain added (`service:<service>`), and the mail records of every
 * mail domain the service was given (`mail:<domain>`). The removal of the service took none of them.
 *
 * So a customer whose hosting ended kept a domain resolving to a node that no longer served their site — where a
 * visitor meets whatever that node answers for a name it does not know, which on a shared node is another customer's
 * website — and their mail went on being delivered to a node that no longer accepts it, with SPF and DKIM still
 * authorising it. Nothing noticed: the nightly drift check compares the provider with what the platform holds, and
 * both still held the records.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The zone of a customer's domain in our DNS, holding what each publisher wrote plus one record of the customer's own. */
function endedHostingZone(string $organizationId, string $serviceId): DnsZone
{
    $zone = DnsZone::query()->create(['organization_id' => $organizationId, 'name' => 'shop.cz', 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id,
        'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]);
    $rows = [
        ['@', 'A', '203.0.113.10', 'system', 'web:'.$serviceId],
        ['www', 'A', '203.0.113.10', 'system', 'web:'.$serviceId],
        ['@', 'MX', '10 mail.onhost.cz.', 'system', 'mail:shop.cz'],
        ['@', 'TXT', 'v=spf1 include:_spf.onhost.cz -all', 'system', 'mail:shop.cz'],
        ['office', 'A', '198.51.100.5', 'customer', null], // the customer's own: never ours to remove
    ];
    foreach ($rows as [$name, $type, $content, $managed, $owner]) {
        DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => $name, 'type' => $type, 'content' => $content, 'ttl' => 600, 'managed_by' => $managed, 'comment' => $owner]);
    }

    return $zone;
}

/** PowerDNS that takes whatever is published, and the ISPConfig of the site being removed. */
function endedHostingPanels(?callable $dns = null): void
{
    Http::fake(function (Request $request) use ($dns) {
        if (str_contains($request->url(), 'pdns.mgmt.test')) {
            return $dns === null ? Http::response(['name' => 'shop.cz.', 'serial' => 2]) : $dns($request);
        }

        return Http::response(match ((string) parse_url($request->url(), PHP_URL_QUERY)) {
            'login' => ['code' => 'ok', 'message' => '', 'response' => 'sess-dns'],
            'sites_web_domain_get' => ['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 7, 'domain' => 'shop.cz', 'system_user' => 'web7']],
            'mail_domain_get' => ['code' => 'ok', 'message' => '', 'response' => ['domain_id' => 909, 'domain' => 'shop.cz']],
            'monitor_jobqueue_count' => ['code' => 'ok', 'message' => '', 'response' => 0],
            default => ['code' => 'ok', 'message' => '', 'response' => []],
        });
    });
}

/**
 * A web hosting on ISPConfig with a mail domain, already cancelled and past its restore window.
 *
 * @return array{0: Service, 1: DnsZone}
 */
function endedHosting(string $organizationId): array
{
    $service = featureWebService(Organization::query()->findOrFail($organizationId), 'ispconfig');
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain', 'remote_id' => '909',
        'remote_node' => '1', 'meta' => ['domain' => 'shop.cz', 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'dns-mail:'.$service->id]);
    MailDomain::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'remote_id' => 909, 'remote_node' => '1', 'remote_client_id' => 3, 'sending_enabled' => true, 'state' => 'active']);
    $service->forceFill(['state' => ServiceStateMachine::SUSPENDED, 'terminate_at' => now()->subDay()])->save();

    return [$service, endedHostingZone($organizationId, $service->id)];
}

/** What the zone holds now, as `name|type` strings. @return list<string> */
function endedHostingShape(DnsZone $zone): array
{
    return $zone->records()->get()->map(fn (DnsRecord $r) => $r->name.'|'.$r->type)->sort()->values()->all();
}

it('stops pointing the customer domain and their mail at the node when the hosting is removed', function () {
    endedHostingPanels();
    [, $org] = $this->customerWithOrganization();
    [$service, $zone] = endedHosting($org->id);

    $purge = driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'purge', CommandContext::system('grace window over')->withScope($org->id), 'dns-purge-1',
        ['reason' => 'grace window over', 'archive_before_delete' => false, 'archive_skip_reason' => 'test fixture']), 40);

    expect($purge->state)->toBe(Operation::SUCCEEDED, (string) data_get($purge->error, 'message', ''))
        // the site's records and the mail records are gone; the customer's own row stays, whatever happens to us
        ->and(endedHostingShape($zone))->toBe(['office|A']);
});

it('says it out loud when the DNS will not take the removal, and ends the service anyway', function () {
    endedHostingPanels(fn (Request $request) => in_array($request->method(), ['PATCH', 'POST'], true)
        ? Http::response(['error' => 'Backend refused the update'], 500)
        : Http::response(['name' => 'shop.cz.', 'serial' => 2]));
    [, $org] = $this->customerWithOrganization();
    [$service, $zone] = endedHosting($org->id);

    $purge = driveOperation(app(ServiceService::class)->requestAction($service->fresh(), 'purge', CommandContext::system('grace window over')->withScope($org->id), 'dns-purge-2',
        ['reason' => 'grace window over', 'archive_before_delete' => false, 'archive_skip_reason' => 'test fixture']), 40);

    // the service ends — DNS must not hold a termination — but what is still published is named to the operators
    expect($purge->state)->toBe(Operation::SUCCEEDED)
        ->and(endedHostingShape($zone))->toContain('@|A')
        ->and(OutboxMessage::query()->where('name', 'service.purge.leftover')->exists())->toBeTrue();
});
