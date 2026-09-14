<?php

declare(strict_types=1);

namespace Onhost\Providers\Cloudflare;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\CdnProvider;

/**
 * Cloudflare as the edge in front of customer sites (API v4, one platform account, token with Zone:Edit,
 * DNS:Edit, Cache Purge and Analytics:Read). Records the platform creates carry the comment "onhost" so a
 * resync never touches records an operator added by hand in the Cloudflare dashboard.
 */
final class CloudflareCdnProvider implements CdnProvider
{
    public const SETTINGS = ['ssl', 'always_use_https', 'http3', 'min_tls_version', 'security_level', 'brotli', 'cache_level', 'development_mode', 'automatic_https_rewrites', 'early_hints', 'zero_rtt'];

    private const NO_PROXY = ['mail', 'smtp', 'imap', 'pop', 'pop3', 'ftp', 'sftp', 'ssh', 'ns1', 'ns2', 'autoconfig', 'autodiscover', 'mx', 'webmail', 'cpanel', 'panel'];

    private ?array $credentials = null;

    public function __construct(private readonly ProviderHttpClient $http, private readonly SecretStore $secrets) {}

    public function available(): bool
    {
        return ($this->credentials()['token'] ?? '') !== '';
    }

    public function createZone(string $domain): array
    {
        $existing = $this->findZone($domain);
        if ($existing !== null) {
            return $existing;
        }
        $body = ['name' => strtolower($domain), 'type' => 'full'];
        if (($this->credentials()['account_id'] ?? '') !== '') {
            $body['account'] = ['id' => $this->credentials()['account_id']];
        }
        $zone = (array) $this->call('POST', '/zones', 'zone.create', $body)->json('result');

        return $this->presentZone($zone);
    }

    public function zone(string $zoneId): array
    {
        return $this->presentZone((array) $this->call('GET', '/zones/'.rawurlencode($zoneId), 'zone.get')->json('result'));
    }

    public function findZone(string $domain): ?array
    {
        $rows = (array) $this->call('GET', '/zones', 'zone.find', null, ['name' => strtolower($domain), 'per_page' => 1])->json('result');
        $zone = $rows[0] ?? null;

        return is_array($zone) ? $this->presentZone($zone) : null;
    }

    public function deleteZone(string $zoneId): void
    {
        $this->call('DELETE', '/zones/'.rawurlencode($zoneId), 'zone.delete');
    }

    public function listRecords(string $zoneId): array
    {
        $rows = (array) $this->call('GET', '/zones/'.rawurlencode($zoneId).'/dns_records', 'dns.list', null, ['per_page' => 500])->json('result');

        return array_values(array_map(fn (array $r) => ['id' => (string) $r['id'], 'type' => (string) $r['type'], 'name' => strtolower((string) $r['name']), 'content' => (string) $r['content'], 'ttl' => (int) ($r['ttl'] ?? 1), 'proxied' => (bool) ($r['proxied'] ?? false), 'priority' => isset($r['priority']) ? (int) $r['priority'] : null, 'managed' => (($r['comment'] ?? '') === 'onhost')], $rows));
    }

    public function syncRecords(string $zoneId, array $records): array
    {
        $zone = $this->zone($zoneId);
        $wanted = [];
        foreach ($records as $r) {
            $name = $this->fqdn((string) $r['name'], $zone['domain']);
            $type = strtoupper((string) $r['type']);
            if ($type === 'NS' && $name === $zone['domain']) {
                continue; // the edge serves its own apex NS
            }
            $wanted[$type.'|'.$name.'|'.strtolower(trim((string) $r['content'], '."'))] = ['type' => $type, 'name' => $name, 'content' => trim((string) $r['content']), 'ttl' => (int) ($r['ttl'] ?? 1) >= 60 ? (int) $r['ttl'] : 1, 'proxied' => (bool) ($r['proxied'] ?? $this->defaultProxied($type, $name, $zone['domain'])), 'priority' => $r['priority'] ?? null];
        }
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $present = [];
        foreach ($this->listRecords($zoneId) as $existing) {
            $key = $existing['type'].'|'.$existing['name'].'|'.strtolower(trim($existing['content'], '."'));
            if (isset($wanted[$key])) {
                $present[$key] = true;
                $w = $wanted[$key];
                if ($existing['proxied'] !== $w['proxied'] || ($existing['ttl'] !== $w['ttl'] && ! $w['proxied'])) {
                    $this->call('PATCH', '/zones/'.rawurlencode($zoneId).'/dns_records/'.rawurlencode($existing['id']), 'dns.update', ['proxied' => $w['proxied'], 'ttl' => $w['proxied'] ? 1 : $w['ttl'], 'comment' => 'onhost']);
                    $stats['updated']++;
                }
            } elseif ($existing['managed']) {
                $this->call('DELETE', '/zones/'.rawurlencode($zoneId).'/dns_records/'.rawurlencode($existing['id']), 'dns.delete');
                $stats['deleted']++;
            }
        }
        foreach ($wanted as $key => $w) {
            if (isset($present[$key])) {
                continue;
            }
            $body = ['type' => $w['type'], 'name' => $w['name'], 'content' => $w['content'], 'ttl' => $w['proxied'] ? 1 : $w['ttl'], 'proxied' => $w['proxied'] && in_array($w['type'], ['A', 'AAAA', 'CNAME'], true), 'comment' => 'onhost'];
            if ($w['priority'] !== null && in_array($w['type'], ['MX', 'SRV'], true)) {
                $body['priority'] = (int) $w['priority'];
            }
            $this->call('POST', '/zones/'.rawurlencode($zoneId).'/dns_records', 'dns.create', $body);
            $stats['created']++;
        }

        return $stats;
    }

    public function setSettings(string $zoneId, array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (! in_array($key, self::SETTINGS, true)) {
                continue;
            }
            $this->call('PATCH', '/zones/'.rawurlencode($zoneId).'/settings/'.$key, 'settings.set', ['value' => $this->settingValue($key, $value)]);
        }

        return $this->settings($zoneId);
    }

    public function settings(string $zoneId): array
    {
        $out = [];
        foreach ((array) $this->call('GET', '/zones/'.rawurlencode($zoneId).'/settings', 'settings.get')->json('result') as $row) {
            if (is_array($row) && in_array($row['id'] ?? '', self::SETTINGS, true)) {
                $out[(string) $row['id']] = $row['value'] ?? null;
            }
        }

        return $out;
    }

    public function purge(string $zoneId, array $urls = []): void
    {
        $this->call('POST', '/zones/'.rawurlencode($zoneId).'/purge_cache', 'cache.purge', $urls === [] ? ['purge_everything' => true] : ['files' => array_values(array_slice($urls, 0, 30))]);
    }

    public function analytics(string $zoneId): array
    {
        $query = 'query($zone:String!,$since:Time!){viewer{zones(filter:{zoneTag:$zone}){httpRequests1hGroups(limit:24,filter:{datetime_geq:$since}){sum{requests bytes cachedRequests cachedBytes threats}}}}}';
        try {
            $groups = (array) $this->call('POST', '/graphql', 'analytics.get', ['query' => $query, 'variables' => ['zone' => $zoneId, 'since' => now()->subDay()->toIso8601ZuluString()]])->json('data.viewer.zones.0.httpRequests1hGroups');
        } catch (ProviderException) {
            return [];
        }
        $sum = ['requests' => 0, 'bytes' => 0, 'cached' => 0, 'cached_bytes' => 0, 'threats' => 0];
        foreach ($groups as $g) {
            $s = (array) ($g['sum'] ?? []);
            $sum['requests'] += (int) ($s['requests'] ?? 0);
            $sum['bytes'] += (int) ($s['bytes'] ?? 0);
            $sum['cached'] += (int) ($s['cachedRequests'] ?? 0);
            $sum['cached_bytes'] += (int) ($s['cachedBytes'] ?? 0);
            $sum['threats'] += (int) ($s['threats'] ?? 0);
        }

        return ['requests' => $sum['requests'], 'bandwidth_bytes' => $sum['bytes'], 'cached_ratio' => $sum['requests'] > 0 ? round($sum['cached'] / $sum['requests'], 3) : null, 'threats' => $sum['threats'], 'window' => '24h'];
    }

    private function presentZone(array $zone): array
    {
        return ['zone_id' => (string) ($zone['id'] ?? ''), 'domain' => strtolower((string) ($zone['name'] ?? '')), 'nameservers' => array_values(array_map('strval', (array) ($zone['name_servers'] ?? []))), 'status' => (string) ($zone['status'] ?? 'pending')];
    }

    private function defaultProxied(string $type, string $name, string $apex): bool
    {
        if (! in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            return false;
        }
        $host = $name === $apex ? '@' : substr($name, 0, -strlen('.'.$apex));

        return ! in_array(strtolower((string) preg_replace('/\..*$/', '', $host)), self::NO_PROXY, true);
    }

    private function fqdn(string $name, string $apex): string
    {
        $name = strtolower(rtrim($name, '.'));
        if ($name === '' || $name === '@' || $name === $apex) {
            return $apex;
        }

        return str_ends_with($name, '.'.$apex) ? $name : $name.'.'.$apex;
    }

    private function settingValue(string $key, mixed $value): mixed
    {
        return match ($key) {
            'ssl' => in_array($value, ['off', 'flexible', 'full', 'strict'], true) ? $value : 'full',
            'min_tls_version' => in_array((string) $value, ['1.0', '1.1', '1.2', '1.3'], true) ? (string) $value : '1.2',
            'security_level' => in_array($value, ['off', 'essentially_off', 'low', 'medium', 'high', 'under_attack'], true) ? $value : 'medium',
            'cache_level' => in_array($value, ['basic', 'simplified', 'aggressive'], true) ? $value : 'aggressive',
            default => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off',
        };
    }

    private function call(string $method, string $path, string $action, ?array $body = null, array $query = []): ProviderResponse
    {
        if (! $this->available()) {
            throw new ProviderException('cloudflare', ProviderErrorCode::AUTH, 'CDN is not configured (set ONHOST_CDN_CLOUDFLARE_SECRET_REF to a secret with token and account_id).');
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'cloudflare', instanceKey: 'cloudflare', method: $method, url: rtrim((string) config('onhost.cdn.cloudflare.base_url', 'https://api.cloudflare.com/client/v4'), '/').$path, action: $action,
            headers: ['Authorization' => 'Bearer '.$this->credentials()['token'], 'Accept' => 'application/json'], body: $body, bodyType: 'json', query: $query, timeoutSeconds: 30, idempotent: $method === 'GET', bucket: 'cloudflare',
        ));
        if ($response->status >= 400 || ($response->isJson() && $response->json('success') === false)) {
            $errors = (array) $response->json('errors', []);
            $first = is_array($errors[0] ?? null) ? $errors[0] : [];
            $code = match (true) {
                $response->status === 401, $response->status === 403 => ProviderErrorCode::AUTH,
                $response->status === 404 => ProviderErrorCode::NOT_FOUND,
                $response->status === 429 => ProviderErrorCode::RATE_LIMIT,
                $response->status >= 500 => ProviderErrorCode::TRANSIENT,
                in_array((int) ($first['code'] ?? 0), [1061, 81057, 81053], true) => ProviderErrorCode::CONFLICT,
                default => ProviderErrorCode::VALIDATION,
            };
            throw new ProviderException('cloudflare', $code, 'Cloudflare '.$action.' failed: '.((string) ($first['message'] ?? ('HTTP '.$response->status))), isset($first['code']) ? (string) $first['code'] : null, ['action' => $action], $response->retryAfterSeconds());
        }

        return $response;
    }

    /** @return array{token:string, account_id:string} */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }
        $ref = (string) config('onhost.cdn.cloudflare.secret_ref', '');
        $data = [];
        if ($ref !== '') {
            try {
                $parsed = SecretRef::parse($ref);
                $data = $this->secrets->exists($parsed) ? (array) $this->secrets->read($parsed) : [];
            } catch (\Throwable) {
                $data = [];
            }
        }

        return $this->credentials = ['token' => (string) ($data['token'] ?? ($data['api_token'] ?? '')), 'account_id' => (string) ($data['account_id'] ?? '')];
    }
}
