<?php

declare(strict_types=1);

namespace Onhost\Providers\IpGeo;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Onhost\Providers\Contracts\IpGeoProvider;
use Throwable;

/**
 * Country lookup over any JSON endpoint that answers with a country code for an address (`{ip}` in the URL is
 * replaced) — a public geo service, a self-hosted MaxMind front or the CDN's own API. Answers are cached for a day;
 * private and reserved addresses are never sent anywhere; a slow or failing endpoint means no signal, never a
 * slower or failed order.
 */
final class HttpIpGeoProvider implements IpGeoProvider
{
    public const KEYS = ['country', 'country_code', 'countryCode', 'country_code2', 'iso_code'];

    public function __construct(private readonly CacheRepository $cache, private readonly string $endpoint, private readonly int $timeoutSeconds = 2) {}

    public function country(string $ip): ?string
    {
        if ($this->endpoint === '' || ! str_contains($this->endpoint, '{ip}') || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        $key = 'onhost:ipgeo:'.sha1($ip);
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }
        $country = null;
        try {
            $response = Http::timeout($this->timeoutSeconds)->connectTimeout($this->timeoutSeconds)->acceptJson()->get(str_replace('{ip}', rawurlencode($ip), $this->endpoint));
            if ($response->successful()) {
                $country = $this->extract($response->json(), trim($response->body()));
            }
        } catch (Throwable) {
            $country = null;
        }
        $this->cache->put($key, $country ?? '', $country === null ? 600 : 86400); // a miss is retried after ten minutes, a hit kept for a day

        return $country;
    }

    private function extract(mixed $json, string $body): ?string
    {
        if (is_array($json)) {
            foreach (self::KEYS as $k) {
                $value = $json[$k] ?? data_get($json, "data.{$k}") ?? data_get($json, "location.{$k}");
                if (is_string($value) && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
                    return strtoupper($value);
                }
            }

            return null;
        }

        return preg_match('/^[A-Za-z]{2}$/', $body) === 1 ? strtoupper($body) : null;
    }
}
