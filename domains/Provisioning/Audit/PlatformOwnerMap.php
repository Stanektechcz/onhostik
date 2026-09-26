<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

use Illuminate\Support\Facades\DB;
use Onhost\Providers\Contracts\Naming;

/**
 * Which platform service a site, a name or a mail domain belongs to — from the platform's own tables only.
 *
 * Sites come from `provider_bindings` (remote_type `web_domain`), mail domains from the bindings' `meta.domain` and
 * from `mail_domains` (which keeps a removed domain's row, while its binding is deleted), names from the per-service
 * prefix (`services.name_prefix`, else Naming::prefix of the id — the same rule the migration backfilled). Removed
 * services are included: a record that belonged to a cancelled service was still not the acting customer's.
 */
final class PlatformOwnerMap
{
    /** `oh1yz8n6_shop`, also behind an ISPConfig client prefix (`c3oh1yz8n6_shop`) */
    private const PREFIX_PATTERN = '/^(?:c\d+)?(oh[a-z0-9]{1,6})_/';

    /**
     * @param  array<string, ?string>  $organizations  service => organization
     * @param  array<string, list<string>>  $prefixes  name prefix => services
     * @param  array<string, array<int|string, string>>  $sites  instance key => site id => service (PHP keeps a numeric id as an int key)
     * @param  array<string, array<string, true>>  $mailDomains  domain => services
     * @param  array<string, list<string>>  $serviceInstances  service => instance keys of its bindings
     * @param  array<string, string>  $instanceKeys  instance id => key
     * @param  array<string, string>  $homes  service => the instance key the service itself names (services.provider_instance_id)
     * @param  array<string, string>  $providers  instance key => provider
     */
    private function __construct(
        private readonly array $organizations,
        private readonly array $prefixes,
        private readonly array $sites,
        private readonly array $mailDomains,
        private readonly array $serviceInstances,
        private readonly array $instanceKeys,
        private readonly array $homes,
        private readonly array $providers,
    ) {}

    public static function load(): self
    {
        $instances = DB::table('provider_instances')->get(['id', 'key', 'provider']);
        $instanceKeys = $instances->pluck('key', 'id')->map(fn ($key) => (string) $key)->all();
        $providers = $instances->pluck('provider', 'key')->map(fn ($provider) => (string) $provider)->all();
        $organizations = [];
        $prefixes = [];
        $homes = [];
        foreach (DB::table('services')->select(['id', 'organization_id', 'name_prefix', 'provider_instance_id'])->lazyById(1000, 'id') as $service) {
            $organizations[(string) $service->id] = $service->organization_id === null ? null : (string) $service->organization_id;
            $prefixes[(string) ($service->name_prefix ?: Naming::prefix((string) $service->id))][] = (string) $service->id;
            if (isset($instanceKeys[$service->provider_instance_id ?? ''])) {
                $homes[(string) $service->id] = $instanceKeys[$service->provider_instance_id];
            }
        }
        $sites = [];
        $mailDomains = [];
        $serviceInstances = [];
        foreach (DB::table('provider_bindings')->select(['id', 'service_id', 'provider_instance_id', 'remote_type', 'remote_id', 'meta'])->lazyById(1000, 'id') as $binding) {
            $key = $instanceKeys[$binding->provider_instance_id] ?? null;
            $service = (string) $binding->service_id;
            if ($key !== null) {
                $serviceInstances[$service][$key] = $key;
                if ($binding->remote_type === 'web_domain') {
                    $sites[$key][(string) $binding->remote_id] = $service;
                }
            }
            $domain = mb_strtolower(trim((string) (json_decode((string) $binding->meta, true)['domain'] ?? '')));
            if ($binding->remote_type === 'mail_domain' && $domain !== '') {
                $mailDomains[$domain][$service] = true;
            }
        }
        foreach (DB::table('mail_domains')->select(['id', 'service_id', 'domain'])->lazyById(1000, 'id') as $row) {
            $mailDomains[mb_strtolower(trim((string) $row->domain))][(string) $row->service_id] = true;
        }

        return new self($organizations, $prefixes, $sites, $mailDomains, array_map('array_values', $serviceInstances), $instanceKeys, $homes, $providers);
    }

    public function organizationOf(?string $service): ?string
    {
        return $service === null ? null : ($this->organizations[$service] ?? null);
    }

    public function serviceOfSite(string $instanceKey, int $site): ?string
    {
        return $this->sites[$instanceKey][(string) $site] ?? null;
    }

    /** The prefix a platform-made name starts with, or null for a name the platform did not make. */
    public static function prefixOf(string $name): ?string
    {
        return preg_match(self::PREFIX_PATTERN, mb_strtolower($name), $m) === 1 ? $m[1] : null;
    }

    /** @return list<string> the services a name's prefix belongs to (more than one only for a shared legacy prefix) */
    public function servicesOfName(string $name): array
    {
        $prefix = self::prefixOf($name);

        return $prefix === null ? [] : ($this->prefixes[$prefix] ?? []);
    }

    /** @return list<string> */
    public function servicesOfMailDomain(string $domain): array
    {
        return array_keys($this->mailDomains[mb_strtolower(trim($domain))] ?? []);
    }

    /**
     * @return list<string> instance keys the service has resources on: its bindings, and the instance the service row
     *                      itself names — which survives the cleanup that deletes a terminated service's bindings
     */
    public function instancesOf(?string $service): array
    {
        if ($service === null) {
            return [];
        }

        return array_values(array_unique(array_filter([...($this->serviceInstances[$service] ?? []), $this->homes[$service] ?? null])));
    }

    public function providerOf(string $instanceKey): ?string
    {
        return $this->providers[$instanceKey] ?? null;
    }

    public function instanceKey(?string $instanceId): ?string
    {
        return $instanceId === null ? null : ($this->instanceKeys[$instanceId] ?? null);
    }

    public function instanceId(string $instanceKey): ?string
    {
        $id = array_search($instanceKey, $this->instanceKeys, true);

        return $id === false ? null : (string) $id;
    }
}
