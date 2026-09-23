<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\PublicDnsCheck;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * The platform publishes what a domain needs — the site's address, the MX, SPF, DKIM, DMARC — and then never looked
 * at what the internet actually answers. A customer whose DNS is somewhere else never adds them, a record falls out
 * of a zone, a domain is moved, and the first anyone hears of it is that the site is off or the mail bounces. Worse,
 * the platform could not even see when its own publish had not landed: the autoconfig SRV record it built was one
 * its own validator refused (audit row 80), so mail domains were left with no records at all and nothing noticed.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Public DNS as the world answers it right now. */
function publicDns(array $answers): void
{
    app()->bind(RecordResolver::class, fn () => new class($answers) implements RecordResolver
    {
        public function __construct(private readonly array $answers) {}

        public function records(string $name, string $type): array
        {
            return (array) ($this->answers[mb_strtolower($name).'|'.$type] ?? []);
        }
    });
}

/** A web hosting whose domain, site and mailbox all exist. */
function checkedWebService(object $org): Service
{
    $service = featureWebService($org, 'ispconfig');
    Node::query()->where('id', $service->node_id)->update(['tags' => ['public_ipv4' => '203.0.113.10']]);
    Website::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'aliases' => [], 'executor' => 'ispconfig', 'php_version' => '8.3',
        'remote_client_id' => 3, 'remote_site_id' => 7, 'remote_node' => '1', 'system_user' => 'web7', 'state' => 'active']);
    MailDomain::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'remote_id' => 909, 'remote_node' => '1', 'remote_client_id' => 3,
        'dkim_selector' => 'onhost202609', 'dkim_public' => 'MIIBIjANBgkqhkiG9w0', 'sending_enabled' => true, 'state' => 'active']);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $service->provider_instance_id, 'remote_type' => 'mail_domain',
        'remote_id' => '909', 'remote_node' => '1', 'meta' => ['domain' => 'shop.cz', 'client_id' => 3], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'pdc:'.$service->id, 'adapter_version' => '1.0.0']);

    return $service->refresh();
}

/** Everything the domain needs, exactly as the platform asks for it. */
function healthyAnswers(): array
{
    return [
        'shop.cz|A' => [['ip' => '203.0.113.10']],
        'shop.cz|MX' => [['target' => 'mail.onhost.cz', 'pri' => 10]],
        'shop.cz|TXT' => [['txt' => 'v=spf1 mx include:_spf.onhost.cz -all']],
        '_dmarc.shop.cz|TXT' => [['txt' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@shop.cz']],
        'onhost202609._domainkey.shop.cz|TXT' => [['txt' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0']],
    ];
}

it('says nothing when the domain answers the way the platform published it', function () {
    publicDns(healthyAnswers());
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);

    $result = app(PublicDnsCheck::class)->run();

    expect($result['checked'])->toBe(1)->and($result['problems'])->toBe(0)
        ->and(data_get($service->refresh()->tags, 'dns_check.problems'))->toBe([])
        ->and(OutboxMessage::query()->where('name', 'service.dns.problem')->exists())->toBeFalse();
});

it('notices that the domain points somewhere else than the site', function () {
    publicDns(array_replace(healthyAnswers(), ['shop.cz|A' => [['ip' => '198.51.100.7']]]));
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);

    app(PublicDnsCheck::class)->run();

    $problems = (array) data_get($service->refresh()->tags, 'dns_check.problems');
    expect(array_column($problems, 'kind'))->toBe(['site_elsewhere'])
        ->and($problems[0]['detail'])->toContain('198.51.100.7')     // where it points, so support does not have to look it up
        ->and($problems[0]['expected'])->toContain('203.0.113.10')
        ->and(OutboxMessage::query()->where('name', 'service.dns.problem')->exists())->toBeTrue();
});

it('notices that mail for the domain will not arrive or will not be accepted', function () {
    publicDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]); // the site answers; nothing of the mail does
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);

    app(PublicDnsCheck::class)->run();

    $kinds = array_column((array) data_get($service->refresh()->tags, 'dns_check.problems'), 'kind');
    expect($kinds)->toContain('mx_missing')->toContain('spf_missing')->toContain('dkim_missing')->toContain('dmarc_missing')
        ->and($kinds)->not->toContain('site_elsewhere');
});

it('knows mail that goes to somebody else from mail that is simply not set up', function () {
    publicDns(array_replace(healthyAnswers(), ['shop.cz|MX' => [['target' => 'mx.google.com', 'pri' => 1]]]));
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);

    app(PublicDnsCheck::class)->run();

    $problems = (array) data_get($service->refresh()->tags, 'dns_check.problems');
    expect(array_column($problems, 'kind'))->toContain('mx_elsewhere')
        ->and(collect($problems)->firstWhere('kind', 'mx_elsewhere')['detail'])->toContain('mx.google.com');
});

it('repairs its own zone instead of only complaining about it', function () {
    publicDns(healthyAnswers());
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);
    $zone = DnsZone::query()->create(['organization_id' => $org->id, 'name' => 'shop.cz', 'provider' => 'powerdns', 'provider_instance_id' => pdnsLab()->id,
        'serial' => 1, 'version' => 1, 'state' => 'active', 'kind' => 'primary', 'nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']]);
    DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => '@', 'type' => 'A', 'content' => '203.0.113.10', 'ttl' => 600, 'managed_by' => 'system', 'comment' => 'web:'.$service->id]);
    Http::fake(fn () => Http::response(['name' => 'shop.cz.', 'serial' => 2])); // the zone's own nameserver takes what it is given

    $result = app(PublicDnsCheck::class)->run();

    // the records went missing from our own zone: putting them back is ours to do, not the customer's to be told about
    expect($result['repaired'])->toBe(1)
        ->and($zone->refresh()->records()->where('type', 'MX')->exists())->toBeTrue()
        ->and($zone->records()->where('name', '@')->where('type', 'A')->exists())->toBeTrue(); // and the site keeps its address
});

it('tells the customer once a day, however often it looks', function () {
    publicDns(['shop.cz|A' => [['ip' => '198.51.100.7']]]);
    [, $org] = $this->customerWithOrganization();
    $service = checkedWebService($org);

    app(PublicDnsCheck::class)->run();
    app(PublicDnsCheck::class)->run();

    expect(OutboxMessage::query()->where('name', 'service.dns.problem')->count())->toBe(1)
        ->and(data_get($service->refresh()->tags, 'dns_check.told_on'))->toBe(now()->toDateString());
});
