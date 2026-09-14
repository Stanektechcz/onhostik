<?php

declare(strict_types=1);

namespace Onhost\Providers\PowerDns;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\DnsProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;

/**
 * PowerDNS Authoritative HTTP API (hidden primary, API private; §16, §48). Zone
 * changes are applied as one atomic RRset PATCH; DNSSEC keys are managed by the
 * API; public secondaries receive NOTIFY/AXFR and have no API.
 */
final class PowerDnsProvider implements DnsProvider
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 600), 60, 0.1);
    }

    public static function providerKey(): string
    {
        return 'powerdns';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['4.8', '4.9', '5.0'];
    }

    public function capabilities(): array
    {
        return ['zone.create' => true, 'zone.delete' => true, 'rrset.patch' => true, 'dnssec' => true, 'axfr.export' => true, 'notify' => true, 'secondary' => 'tsig'];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $server = $this->request('GET', '/servers/'.$this->server(), 'server.get');
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $this->cache->put("onhost:pdns:version:{$this->instance->id}", (string) ($server['version'] ?? ''), 3600);

            return new ProviderHealth(true, (string) ($server['version'] ?? null), $ms, ['daemon' => $server['daemon_type'] ?? null]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $v = $this->cache->get("onhost:pdns:version:{$this->instance->id}");

        return is_string($v) && $v !== '' ? $v : null;
    }

    public function createZone(string $zone, array $options = []): ProviderResult
    {
        $name = $this->fqdn($zone);
        if ($this->zoneExists($zone)) {
            return ProviderResult::completed(null, ['zone' => $name], alreadyExisted: true);
        }
        $nameservers = array_map(fn ($ns) => $this->fqdn($ns), (array) ($options['nameservers'] ?? $this->instance->option('nameservers', ['ns1.onhost.cz', 'ns2.onhost.cz'])));
        $payload = [
            'name' => $name, 'kind' => (string) ($options['kind'] ?? 'Master'), 'nameservers' => $nameservers,
            'soa_edit_api' => 'DEFAULT', 'dnssec' => (bool) ($options['dnssec'] ?? false), 'api_rectify' => true,
        ];
        if (! empty($options['masters'])) {
            $payload['masters'] = (array) $options['masters'];
        }
        $created = $this->request('POST', '/servers/'.$this->server().'/zones', 'zone.create', $payload);
        $this->applyMetadata($name);

        return ProviderResult::completed(null, ['zone' => $name, 'serial' => $created['serial'] ?? null]);
    }

    public function deleteZone(string $zone): ProviderResult
    {
        if (! $this->zoneExists($zone)) {
            return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
        }
        $this->request('DELETE', '/servers/'.$this->server().'/zones/'.$this->fqdn($zone), 'zone.delete');

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function zoneExists(string $zone): bool
    {
        try {
            $this->request('GET', '/servers/'.$this->server().'/zones/'.$this->fqdn($zone), 'zone.get', [], ['rrsets' => 'false']);

            return true;
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return false;
            }
            throw $e;
        }
    }

    public function listRecords(string $zone): array
    {
        $name = $this->fqdn($zone);
        $data = $this->request('GET', '/servers/'.$this->server().'/zones/'.$name, 'zone.records');
        $out = [];
        foreach ((array) ($data['rrsets'] ?? []) as $rrset) {
            $relative = $this->relative((string) $rrset['name'], $name);
            foreach ((array) ($rrset['records'] ?? []) as $record) {
                if (! empty($record['disabled'])) {
                    continue;
                }
                [$prio, $content] = $this->splitPriority((string) $rrset['type'], (string) $record['content']);
                $out[] = ['name' => $relative, 'type' => (string) $rrset['type'], 'content' => $content, 'ttl' => (int) $rrset['ttl'], 'prio' => $prio];
            }
        }

        return $out;
    }

    /**
     * Applies a change batch as RRset replacements: for each (name,type) touched by
     * the batch, the final desired record set is computed from the current zone +
     * changes and sent in ONE PATCH, then NOTIFY is sent to secondaries.
     */
    public function applyChanges(string $zone, array $changes): ProviderResult
    {
        $name = $this->fqdn($zone);
        $current = $this->listRecords($zone);
        $sets = [];
        foreach ($current as $r) {
            $sets[$this->setKey($r)][] = $r;
        }
        $touched = [];
        foreach ($changes as $change) {
            $record = $this->normalize((array) $change['record']);
            $previous = isset($change['previous']) ? $this->normalize((array) $change['previous']) : null;
            $key = $this->setKey($record);
            $touched[$key] = true;
            switch ($change['op']) {
                case 'add':
                    $sets[$key][] = $record;
                    break;
                case 'delete':
                    $sets[$key] = array_values(array_filter($sets[$key] ?? [], fn ($r) => ! $this->sameRecord($r, $record)));
                    break;
                case 'update':
                    if ($previous !== null) {
                        $prevKey = $this->setKey($previous);
                        $touched[$prevKey] = true;
                        $sets[$prevKey] = array_values(array_filter($sets[$prevKey] ?? [], fn ($r) => ! $this->sameRecord($r, $previous)));
                    }
                    $sets[$key][] = $record;
                    break;
                default:
                    throw new ProviderException('powerdns', ProviderErrorCode::VALIDATION, "Unknown change op {$change['op']}");
            }
        }
        $rrsets = [];
        foreach (array_keys($touched) as $key) {
            [$rname, $type] = explode('|', $key, 2);
            $records = array_values(array_unique(array_map(fn ($r) => $this->joinPriority($type, $r), $sets[$key] ?? []), SORT_REGULAR));
            $absolute = $this->absolute($rname, $name);
            if ($records === []) {
                $rrsets[] = ['name' => $absolute, 'type' => $type, 'changetype' => 'DELETE'];
            } else {
                $ttl = (int) ($sets[$key][0]['ttl'] ?? 3600);
                $rrsets[] = ['name' => $absolute, 'type' => $type, 'ttl' => $ttl, 'changetype' => 'REPLACE', 'records' => array_map(fn ($c) => ['content' => $c, 'disabled' => false], $records)];
            }
        }
        if ($rrsets === []) {
            return ProviderResult::completed(null, ['noop' => true]);
        }
        $this->request('PATCH', '/servers/'.$this->server().'/zones/'.$name, 'zone.patch', ['rrsets' => $rrsets]);
        $this->request('PUT', '/servers/'.$this->server().'/zones/'.$name.'/notify', 'zone.notify');
        $meta = $this->request('GET', '/servers/'.$this->server().'/zones/'.$name, 'zone.meta', [], ['rrsets' => 'false']);

        return ProviderResult::completed(null, ['rrsets' => count($rrsets), 'serial' => (int) ($meta['serial'] ?? 0)]);
    }

    public function exportZone(string $zone): string
    {
        $response = $this->raw('GET', '/servers/'.$this->server().'/zones/'.$this->fqdn($zone).'/export', 'zone.export');

        return $response->rawBody;
    }

    public function dnssecStatus(string $zone): array
    {
        $name = $this->fqdn($zone);
        $keys = (array) $this->request('GET', '/servers/'.$this->server().'/zones/'.$name.'/cryptokeys', 'dnssec.keys');
        $ds = [];
        foreach ($keys as $key) {
            if (! empty($key['active']) && in_array($key['keytype'] ?? '', ['ksk', 'csk'], true)) {
                $ds = array_merge($ds, (array) ($key['ds'] ?? []));
            }
        }
        $zoneMeta = $this->request('GET', '/servers/'.$this->server().'/zones/'.$name, 'zone.meta', [], ['rrsets' => 'false']);

        return ['enabled' => (bool) ($zoneMeta['dnssec'] ?? false), 'ds' => array_values(array_unique($ds)), 'keys' => array_map(fn ($k) => ['id' => $k['id'] ?? null, 'type' => $k['keytype'] ?? null, 'active' => (bool) ($k['active'] ?? false), 'algorithm' => $k['algorithm'] ?? null, 'bits' => $k['bits'] ?? null, 'dnskey' => $k['dnskey'] ?? null], $keys)];
    }

    public function enableDnssec(string $zone): ProviderResult
    {
        $name = $this->fqdn($zone);
        $status = $this->dnssecStatus($zone);
        if ($status['enabled'] && $status['ds'] !== []) {
            return ProviderResult::completed(null, ['ds' => $status['ds']], alreadyExisted: true);
        }
        $this->request('POST', '/servers/'.$this->server().'/zones/'.$name.'/cryptokeys', 'dnssec.key.create', ['keytype' => 'csk', 'active' => true, 'algorithm' => (string) $this->instance->option('dnssec_algorithm', 'ECDSAP256SHA256'), 'bits' => (int) $this->instance->option('dnssec_bits', 256)]);
        $this->request('PUT', '/servers/'.$this->server().'/zones/'.$name, 'zone.dnssec', ['dnssec' => true, 'api_rectify' => true]);
        $this->request('PUT', '/servers/'.$this->server().'/zones/'.$name.'/rectify', 'zone.rectify');
        $status = $this->dnssecStatus($zone);

        return ProviderResult::completed(null, ['ds' => $status['ds'], 'keys' => count($status['keys'])]);
    }

    public function disableDnssec(string $zone): ProviderResult
    {
        $name = $this->fqdn($zone);
        foreach ((array) $this->request('GET', '/servers/'.$this->server().'/zones/'.$name.'/cryptokeys', 'dnssec.keys') as $key) {
            $this->request('DELETE', '/servers/'.$this->server().'/zones/'.$name.'/cryptokeys/'.$key['id'], 'dnssec.key.delete');
        }
        $this->request('PUT', '/servers/'.$this->server().'/zones/'.$name, 'zone.dnssec', ['dnssec' => false]);

        return ProviderResult::completed(null, ['dnssec' => false]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function applyMetadata(string $name): void
    {
        $tsig = $this->instance->option('secondary_tsig_keys', []);
        $alsoNotify = (array) $this->instance->option('also_notify', []);
        if ($alsoNotify !== []) {
            $this->request('POST', '/servers/'.$this->server().'/zones/'.$name.'/metadata', 'zone.metadata', ['kind' => 'ALSO-NOTIFY', 'metadata' => $alsoNotify]);
        }
        if ($tsig !== []) {
            $this->request('POST', '/servers/'.$this->server().'/zones/'.$name.'/metadata', 'zone.metadata', ['kind' => 'TSIG-ALLOW-AXFR', 'metadata' => (array) $tsig]);
        }
        $allowAxfr = (array) $this->instance->option('allow_axfr_from', []);
        if ($allowAxfr !== []) {
            $this->request('POST', '/servers/'.$this->server().'/zones/'.$name.'/metadata', 'zone.metadata', ['kind' => 'ALLOW-AXFR-FROM', 'metadata' => $allowAxfr]);
        }
    }

    private function request(string $method, string $path, string $action, array $body = [], array $query = []): mixed
    {
        $response = $this->raw($method, $path, $action, $body, $query);
        if ($response->status === 204) {
            return [];
        }
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function raw(string $method, string $path, string $action, array $body = [], array $query = []): ProviderResponse
    {
        $apiKey = (string) ($this->credentials['api_key'] ?? '');
        if ($apiKey === '') {
            throw new ProviderException('powerdns', ProviderErrorCode::AUTH, 'PowerDNS API key is not configured');
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'powerdns', instanceKey: $this->instance->key, method: $method, url: rtrim((string) $this->instance->base_url, '/').'/api/v1'.$path, action: $action,
            headers: ['X-API-Key' => $apiKey, 'Accept' => 'application/json'], body: $body === [] ? null : $body, bodyType: 'json', query: $query, timeoutSeconds: 15, critical: true, idempotent: $method === 'GET',
        ));
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('powerdns', ProviderErrorCode::AUTH, "PowerDNS rejected the API key for {$action}", (string) $response->status);
        }
        if ($response->status === 404) {
            throw new ProviderException('powerdns', ProviderErrorCode::NOT_FOUND, "PowerDNS object not found for {$action}", '404');
        }
        if ($response->status === 409) {
            throw new ProviderException('powerdns', ProviderErrorCode::CONFLICT, "PowerDNS {$action}: ".(string) $response->json('error', 'conflict'), '409');
        }
        if ($response->status === 422) {
            throw new ProviderException('powerdns', ProviderErrorCode::VALIDATION, "PowerDNS {$action}: ".(string) $response->json('error', 'invalid'), '422');
        }
        if ($response->status >= 500) {
            throw new ProviderException('powerdns', ProviderErrorCode::TRANSIENT, "PowerDNS {$action}: HTTP {$response->status}", (string) $response->status);
        }
        if ($response->status >= 400) {
            throw new ProviderException('powerdns', ProviderErrorCode::VALIDATION, "PowerDNS {$action}: ".(string) $response->json('error', 'error'), (string) $response->status);
        }
        $this->http->recordSuccess($this->instance->key);

        return $response;
    }

    private function server(): string
    {
        return (string) $this->instance->option('server_id', 'localhost');
    }

    private function fqdn(string $name): string
    {
        return rtrim(strtolower($name), '.').'.';
    }

    private function relative(string $absolute, string $zoneFqdn): string
    {
        $absolute = strtolower($absolute);
        if ($absolute === $zoneFqdn) {
            return '@';
        }

        return rtrim(substr($absolute, 0, -strlen($zoneFqdn)), '.');
    }

    private function absolute(string $relative, string $zoneFqdn): string
    {
        return $relative === '@' || $relative === '' ? $zoneFqdn : $relative.'.'.$zoneFqdn;
    }

    /** @return array{0:?int, 1:string} */
    private function splitPriority(string $type, string $content): array
    {
        if (in_array($type, ['MX', 'SRV'], true) && preg_match('/^(\d+)\s+(.+)$/', $content, $m)) {
            return [(int) $m[1], $m[2]];
        }

        return [null, $content];
    }

    private function joinPriority(string $type, array $record): string
    {
        $content = (string) $record['content'];
        if (in_array($type, ['MX', 'SRV'], true) && $record['prio'] !== null) {
            return $record['prio'].' '.$content;
        }
        if ($type === 'TXT' && ! str_starts_with($content, '"')) {
            return '"'.str_replace('"', '\"', $content).'"';
        }

        return $content;
    }

    private function normalize(array $record): array
    {
        $type = strtoupper((string) $record['type']);
        $content = trim((string) $record['content']);
        if ($type === 'TXT' && str_starts_with($content, '"') && str_ends_with($content, '"')) {
            $content = stripslashes(substr($content, 1, -1));
        }
        if (in_array($type, ['CNAME', 'MX', 'NS', 'SRV', 'PTR'], true) && ! str_ends_with($content, '.') && str_contains($content, '.')) {
            $content .= '.';
        }

        return ['name' => strtolower(trim((string) ($record['name'] ?? '@'))) ?: '@', 'type' => $type, 'content' => $content, 'ttl' => (int) ($record['ttl'] ?? 3600), 'prio' => isset($record['prio']) ? (int) $record['prio'] : null];
    }

    private function setKey(array $record): string
    {
        return strtolower((string) $record['name']).'|'.strtoupper((string) $record['type']);
    }

    private function sameRecord(array $a, array $b): bool
    {
        return strtolower($a['name']) === strtolower($b['name']) && strtoupper($a['type']) === strtoupper($b['type']) && $a['content'] === $b['content'] && (int) ($a['prio'] ?? 0) === (int) ($b['prio'] ?? 0);
    }
}
