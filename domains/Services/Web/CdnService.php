<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Services\Models\CdnZone;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Cloudflare\CloudflareCdnProvider;
use Onhost\Providers\Contracts\CdnProvider;
use Throwable;

/**
 * CDN in front of a site: an edge zone for the site's registrable domain, the platform's DNS records mirrored
 * into it (web hosts proxied, mail and the like passed through), edge settings, cache purge and the nameserver
 * switch at the registrar when the domain is ours to switch. The platform's DNS stays authoritative for what the
 * records are; the edge only serves them while the domain is delegated to it.
 */
final class CdnService
{
    public const DEFAULT_SETTINGS = ['ssl' => 'full', 'always_use_https' => true, 'http3' => true, 'min_tls_version' => '1.2', 'security_level' => 'medium', 'brotli' => true, 'cache_level' => 'aggressive', 'automatic_https_rewrites' => true];

    private const SECOND_LEVEL = ['co', 'com', 'org', 'net', 'gov', 'edu', 'ac', 'ne', 'or'];

    public function __construct(private readonly CloudflareCdnProvider $provider, private readonly DnsService $dns, private readonly DomainService $domains, private readonly ServiceFeatures $features, private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit) {}

    public function provider(): CdnProvider
    {
        return $this->provider;
    }

    /** @return array<string,mixed> */
    public function status(Service $service): array
    {
        $domain = strtolower((string) $service->spec('domain', $service->hostname));
        [$apex, $zone] = $this->apexOf($service, $domain);
        $row = $this->zoneRow($service);
        $managed = Domain::query()->where('organization_id', $service->organization_id)->where('name', $apex)->first();
        $features = $this->features->features($service);

        return [
            'available' => ! empty($features['cdn']['enabled']) && $this->provider->available(),
            'enabled' => $row !== null && $row->state !== 'disabled',
            'state' => $row?->state ?? 'off',
            'domain' => $domain, 'apex' => $apex, 'dns_managed' => $zone !== null, 'domain_managed' => $managed !== null && $managed->isActive(),
            'nameservers' => (array) ($row?->nameservers ?? []),
            'settings' => (array) ($row?->settings ?? self::DEFAULT_SETTINGS),
            'proxied_records' => (array) ($row?->proxied_records ?? []),
            'activated_at' => $row?->activated_at?->toIso8601String(),
            'last_error' => $row?->last_error,
            'analytics' => $row !== null && $row->state === 'active' ? $this->analytics($row) : null,
            'provider' => 'edge', // vendor stays internal
        ];
    }

    public function zoneRow(Service $service): ?CdnZone
    {
        return CdnZone::query()->where('service_id', $service->id)->first();
    }

    /** @return array{0:string,1:?DnsZone} the registrable apex of the site domain and our DNS zone for it, when we serve it */
    public function apexOf(Service $service, string $domain): array
    {
        $domain = strtolower(trim($domain, '.'));
        $zones = DnsZone::query()->where('organization_id', $service->organization_id)->get()->filter(fn (DnsZone $z) => $domain === $z->name || str_ends_with($domain, '.'.$z->name))->sortByDesc(fn (DnsZone $z) => strlen($z->name));
        $zone = $zones->first();
        if ($zone !== null) {
            return [$zone->name, $zone];
        }
        $labels = explode('.', $domain);
        $count = count($labels);
        if ($count >= 3 && strlen($labels[$count - 1]) === 2 && in_array($labels[$count - 2], self::SECOND_LEVEL, true)) {
            return [implode('.', array_slice($labels, -3)), null];
        }

        return [implode('.', array_slice($labels, -2)), null];
    }

    /** @param  array<string,mixed>  $settings */
    public function enable(Service $service, array $settings, CommandContext $context): CdnZone
    {
        if (empty($this->features->features($service)['cdn']['enabled'])) {
            throw new DomainError('feature_unavailable', 'CDN is not part of this plan.', 422);
        }
        if (! $this->provider->available()) {
            throw new DomainError('cdn_unavailable', 'The CDN is not configured on the platform yet.', 503);
        }
        $domain = strtolower((string) $service->spec('domain', $service->hostname));
        [$apex, $zone] = $this->apexOf($service, $domain);
        $edge = $this->provider->createZone($apex);
        $records = $this->edgeRecords($service, $zone, $apex, $domain);
        $stats = $this->provider->syncRecords($edge['zone_id'], $records);
        $wanted = array_replace(self::DEFAULT_SETTINGS, array_intersect_key($settings, self::DEFAULT_SETTINGS));
        $applied = $this->provider->setSettings($edge['zone_id'], $wanted);
        $row = CdnZone::query()->firstOrNew(['service_id' => $service->id]);
        $row->forceFill([
            'organization_id' => $service->organization_id, 'provider' => 'cloudflare', 'domain' => $apex, 'zone_id' => $edge['zone_id'], 'nameservers' => $edge['nameservers'],
            'state' => $edge['status'] === 'active' ? 'active' : 'pending_ns', 'settings' => array_replace($wanted, $applied), 'proxied_records' => array_values(array_map(fn ($r) => $r['name'], array_filter($records, fn ($r) => ! empty($r['proxied'])))),
            'activated_at' => $edge['status'] === 'active' ? ($row->activated_at ?? now()) : null, 'last_error' => null,
        ])->save();
        $switched = $this->switchNameservers($service, $apex, $edge['nameservers'], $context, 'to-edge');
        $this->audit->record($context->withScope($service->organization_id), 'service.cdn.enable', 'succeeded', ['apex' => $apex, 'records' => $stats, 'nameservers_switched' => $switched], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('cdn.enabled', 'service', $service->id, ['domain' => $apex, 'nameservers' => $edge['nameservers'], 'nameservers_switched' => $switched, 'state' => $row->state], $service->organization_id));
        $this->features->forget($service);

        return $row;
    }

    public function disable(Service $service, CommandContext $context): void
    {
        $row = $this->zoneRow($service);
        if ($row === null) {
            return;
        }
        $switched = $this->switchNameservers($service, (string) $row->domain, $this->dns->nameservers(), $context, 'to-platform');
        try {
            if ((string) $row->zone_id !== '') {
                $this->provider->deleteZone((string) $row->zone_id);
            }
        } catch (Throwable $e) {
            $row->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }
        $row->delete();
        $this->audit->record($context->withScope($service->organization_id), 'service.cdn.disable', 'succeeded', ['apex' => $row->domain, 'nameservers_switched' => $switched], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('cdn.disabled', 'service', $service->id, ['domain' => $row->domain, 'nameservers_switched' => $switched], $service->organization_id));
        $this->features->forget($service);
    }

    /** @param  list<string>  $urls */
    public function purge(Service $service, array $urls, CommandContext $context): int
    {
        $row = $this->zoneRow($service);
        if ($row === null || (string) $row->zone_id === '') {
            throw new DomainError('cdn_not_enabled', 'Enable the CDN first.', 409);
        }
        $domain = strtolower((string) $service->spec('domain', $service->hostname));
        $urls = array_values(array_filter(array_map('trim', $urls), fn ($u) => filter_var($u, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($u, PHP_URL_HOST)), [$domain, $row->domain, 'www.'.$row->domain], true)));
        $this->provider->purge((string) $row->zone_id, $urls);
        $this->audit->record($context->withScope($service->organization_id), 'service.cdn.purge', 'succeeded', ['urls' => count($urls)], 'service', $service->id);

        return $urls === [] ? -1 : count($urls);
    }

    /**
     * Scheduler: activate zones whose delegation arrived and mirror record changes into active zones.
     *
     * @return array{checked:int, activated:int, synced:int, errors:int}
     */
    public function refresh(int $limit = 100): array
    {
        $stats = ['checked' => 0, 'activated' => 0, 'synced' => 0, 'errors' => 0];
        if (! $this->provider->available()) {
            return $stats;
        }
        foreach (CdnZone::query()->whereIn('state', ['pending_ns', 'active'])->orderBy('updated_at')->limit($limit)->get() as $row) {
            $stats['checked']++;
            $service = Service::query()->find($row->service_id);
            if ($service === null) {
                $row->delete();

                continue;
            }
            try {
                $edge = $this->provider->zone((string) $row->zone_id);
                if ($row->state === 'pending_ns' && $edge['status'] === 'active') {
                    $row->forceFill(['state' => 'active', 'activated_at' => now(), 'nameservers' => $edge['nameservers'], 'last_error' => null])->save();
                    $this->outbox->publish(GenericEvent::of('cdn.activated', 'service', $service->id, ['domain' => $row->domain], $service->organization_id));
                    $stats['activated']++;
                }
                $domain = strtolower((string) $service->spec('domain', $service->hostname));
                [, $zone] = $this->apexOf($service, $domain);
                $records = $this->edgeRecords($service, $zone, (string) $row->domain, $domain);
                $changed = $this->provider->syncRecords((string) $row->zone_id, $records);
                if (array_sum($changed) > 0) {
                    $row->forceFill(['proxied_records' => array_values(array_map(fn ($r) => $r['name'], array_filter($records, fn ($r) => ! empty($r['proxied']))))])->save();
                    $stats['synced']++;
                }
                $row->touch();
            } catch (Throwable $e) {
                $row->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 250)])->save();
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * The records the edge should carry: everything from our zone when we serve the domain, otherwise the site
     * host and www pointing at the server. Web hosts are proxied; mail, ftp and similar names pass through.
     *
     * @return list<array{type:string,name:string,content:string,ttl:int,proxied:bool,priority:?int}>
     */
    public function edgeRecords(Service $service, ?DnsZone $zone, string $apex, string $domain): array
    {
        $records = [];
        if ($zone !== null) {
            foreach ($zone->records()->get() as $r) {
                /** @var DnsRecord $r */
                if (in_array($r->type, ['SOA', 'NS'], true) && in_array($r->name, ['@', '', $zone->name], true)) {
                    continue;
                }
                $name = in_array($r->name, ['@', ''], true) ? $apex : (str_ends_with($r->name, '.'.$apex) ? $r->name : $r->name.'.'.$apex);
                $records[] = ['type' => $r->type, 'name' => $name, 'content' => $r->content, 'ttl' => (int) $r->ttl, 'proxied' => in_array($r->type, ['A', 'AAAA', 'CNAME'], true) && $this->proxiable($name, $apex, $domain), 'priority' => $r->prio];
            }

            return $records;
        }
        $ipv4 = (string) (data_get($service->tags, 'access.public_ipv4') ?? $service->spec('public_ipv4', ''));
        $ipv6 = (string) (data_get($service->tags, 'access.public_ipv6') ?? $service->spec('public_ipv6', ''));
        foreach (array_unique([$domain, $domain === $apex ? 'www.'.$apex : $domain]) as $host) {
            if ($ipv4 !== '') {
                $records[] = ['type' => 'A', 'name' => $host, 'content' => $ipv4, 'ttl' => 300, 'proxied' => true, 'priority' => null];
            }
            if ($ipv6 !== '') {
                $records[] = ['type' => 'AAAA', 'name' => $host, 'content' => $ipv6, 'ttl' => 300, 'proxied' => true, 'priority' => null];
            }
        }

        return $records;
    }

    private function proxiable(string $name, string $apex, string $siteDomain): bool
    {
        if ($name === $apex || $name === $siteDomain || $name === 'www.'.$apex) {
            return true;
        }
        $label = strtolower((string) preg_replace('/\..*$/', '', $name));

        return ! in_array($label, ['mail', 'smtp', 'imap', 'pop', 'pop3', 'ftp', 'sftp', 'ssh', 'ns1', 'ns2', 'autoconfig', 'autodiscover', 'mx', 'webmail', 'panel', 'cpanel', 'vpn'], true);
    }

    /** Switch the registrar delegation when the apex is a domain we manage for this organization; returns whether it was. */
    private function switchNameservers(Service $service, string $apex, array $nameservers, CommandContext $context, string $direction): bool
    {
        $domain = Domain::query()->where('organization_id', $service->organization_id)->where('name', $apex)->first();
        if ($domain === null || ! $domain->isActive() || $nameservers === []) {
            return false;
        }
        try {
            $this->domains->updateNameservers($domain, array_values($nameservers), $context, 'cdn:'.$direction.':'.$service->id.':'.md5(implode(',', $nameservers)), $direction === 'to-edge' ? 'external' : 'powerdns');

            return true;
        } catch (DomainError $e) {
            CdnZone::query()->where('service_id', $service->id)->update(['last_error' => 'nameservers: '.mb_substr($e->getMessage(), 0, 200)]);

            return false;
        }
    }

    private function analytics(CdnZone $row): ?array
    {
        try {
            return $this->provider->analytics((string) $row->zone_id) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
