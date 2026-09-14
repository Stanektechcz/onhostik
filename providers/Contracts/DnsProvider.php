<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * DNS executor contract (PowerDNS canonical; WEDOS Zone / ISPConfig optional).
 * Records are normalised: ['name' => 'www', 'type' => 'A', 'content' => '1.2.3.4', 'ttl' => 300, 'prio' => null].
 */
interface DnsProvider extends ProviderAdapter
{
    public function createZone(string $zone, array $options = []): ProviderResult;

    public function deleteZone(string $zone): ProviderResult;

    public function zoneExists(string $zone): bool;

    /** @return list<array{name:string,type:string,content:string,ttl:int,prio:int|null}> */
    public function listRecords(string $zone): array;

    /**
     * Apply a batch atomically where the vendor supports it (PowerDNS PATCH rrsets,
     * WEDOS rows + commit). @param list<array{op:string,record:array<string,mixed>,previous?:array<string,mixed>}> $changes
     */
    public function applyChanges(string $zone, array $changes): ProviderResult;

    public function exportZone(string $zone): string;

    /** @return array{enabled:bool,ds:list<string>,keys:list<array<string,mixed>>} */
    public function dnssecStatus(string $zone): array;

    public function enableDnssec(string $zone): ProviderResult;

    public function disableDnssec(string $zone): ProviderResult;
}
