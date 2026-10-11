<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\CdnZone;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\Web\OriginExposureWatch;
use Onhost\Platform\Dns\RecordResolver;

/*
 * A site behind the CDN is protected only while no other public name hands out the node's address (direct., origin.,
 * mail., ftp., the MX host, or the main name itself once it has fallen out of the proxy). Public DNS only — no connection.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Public DNS answers keyed `<name>|<type>`. */
function originDns(array $answers): void
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

function originWeb(object $org, bool $cdn = true): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    $service->forceFill(['hostname' => 'shop.cz', 'desired_spec' => array_merge((array) $service->desired_spec, ['domain' => 'shop.cz'])])->save();
    if ($cdn) {
        CdnZone::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider' => 'cloudflare', 'domain' => 'shop.cz', 'zone_id' => 'z1', 'state' => 'active']);
    }

    return $service->refresh();
}

function originFinding(Service $service): ?array
{
    return collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'origin_exposure');
}

it('warns when a leak name or the MX host resolves to the node behind the CDN', function () {
    originDns([
        'shop.cz|A' => [['ip' => '104.16.0.1']], // proxied: the edge
        'direct.shop.cz|A' => [['ip' => '203.0.113.10']],
        'shop.cz|MX' => [['target' => 'mx.shop.cz', 'pri' => 10]],
        'mx.shop.cz|A' => [['ip' => '203.0.113.10']],
        'ftp.shop.cz|A' => [['ip' => '198.51.100.7']], // somewhere else entirely
    ]);
    [, $org] = $this->customerWithOrganization();
    $service = originWeb($org);

    $result = app(OriginExposureWatch::class)->run();

    $finding = originFinding($service);
    $names = array_column($service->refresh()->tags['origin_exposure']['leaks'], 'name');
    expect($result)->toMatchArray(['checked' => 1, 'leaking' => 1])
        ->and($names)->toBe(['direct.shop.cz', 'mx.shop.cz'])
        ->and($finding['level'])->toBe('warn')
        ->and($finding['cs'])->toContain('direct.shop.cz')->and($finding['en'])->toContain('origin server')->toContain('mx.shop.cz');
});

it('warns when the main name itself answers with the node (it fell out of the proxy)', function () {
    originDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    [, $org] = $this->customerWithOrganization();
    $service = originWeb($org);

    app(OriginExposureWatch::class)->run();

    expect(originFinding($service)['level'])->toBe('warn')->and(array_column($service->refresh()->tags['origin_exposure']['leaks'], 'name'))->toBe(['shop.cz']);
});

it('is ok when nothing but the edge answers', function () {
    originDns(['shop.cz|A' => [['ip' => '104.16.0.1']], 'mail.shop.cz|A' => [['ip' => '198.51.100.7']]]);
    [, $org] = $this->customerWithOrganization();
    $service = originWeb($org);

    app(OriginExposureWatch::class)->run();

    $finding = originFinding($service);
    expect($finding['level'])->toBe('ok')->and($finding['state'])->toBe('current');
});

it('says nothing about a site that is not behind the CDN', function () {
    originDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    [, $org] = $this->customerWithOrganization();
    $service = originWeb($org, cdn: false);

    $result = app(OriginExposureWatch::class)->run();

    expect($result['checked'])->toBe(0)->and(originFinding($service))->toBeNull();
});

it('is registered as a collector and stale data cannot stay ok', function () {
    originDns([]);
    [, $org] = $this->customerWithOrganization();
    $service = originWeb($org);
    $service->forceFill(['tags' => ['origin_exposure' => ['leaks' => [], 'checked_at' => now()->subDays(5)->toIso8601String()]]])->save();

    $finding = originFinding($service);
    expect(CollectorHeartbeat::EXPECTED)->toHaveKey('sites.origin')
        ->and($finding['state'])->toBe('stale')->and($finding['level'])->toBe('warn');
});
