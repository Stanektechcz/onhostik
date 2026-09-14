<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A CDN / edge provider in front of a customer site: a zone per domain, the records the edge proxies, the
 * edge settings (TLS mode, HTTPS redirect, HTTP/3, security level) and cache purge. The platform keeps the
 * authoritative records in its own DNS; the edge zone mirrors them while the domain points at the edge.
 */
interface CdnProvider
{
    /** @return array{zone_id:string, nameservers:list<string>, status:string} */
    public function createZone(string $domain): array;

    /** @return array{zone_id:string, nameservers:list<string>, status:string, domain:string} */
    public function zone(string $zoneId): array;

    /** @return array{zone_id:string, nameservers:list<string>, status:string, domain:string}|null */
    public function findZone(string $domain): ?array;

    public function deleteZone(string $zoneId): void;

    /** @return list<array{id:string,type:string,name:string,content:string,ttl:int,proxied:bool}> */
    public function listRecords(string $zoneId): array;

    /**
     * Make the edge zone carry exactly these records (others managed by the platform are removed).
     *
     * @param  list<array{type:string,name:string,content:string,ttl?:int,proxied?:bool,priority?:int}>  $records
     * @return array{created:int, updated:int, deleted:int}
     */
    public function syncRecords(string $zoneId, array $records): array;

    /**
     * @param  array<string,mixed>  $settings  ssl (flexible|full|strict), always_use_https, http3, min_tls_version, security_level, brotli, cache_level, development_mode
     * @return array<string,mixed> the settings as the edge reports them afterwards
     */
    public function setSettings(string $zoneId, array $settings): array;

    /** @return array<string,mixed> */
    public function settings(string $zoneId): array;

    /** Purge everything (empty list) or the given URLs. */
    public function purge(string $zoneId, array $urls = []): void;

    /** @return array<string,mixed> analytics for the last 24 hours when the provider offers them (requests, bandwidth_bytes, cached_ratio, threats) */
    public function analytics(string $zoneId): array;

    public function available(): bool;
}
