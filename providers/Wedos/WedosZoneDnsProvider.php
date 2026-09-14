<?php

declare(strict_types=1);

namespace Onhost\Providers\Wedos;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Clock\Clock;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\DnsProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;

/**
 * Optional WEDOS Zone provider (§48.2): either a full hosted zone edited through
 * dns-row-* + `dns-domain-commit` (two-phase, §docs-provider-apis §1) or an
 * Anycast secondary fed by AXFR/TSIG from the PowerDNS hidden primary.
 */
final class WedosZoneDnsProvider implements DnsProvider
{
    private readonly WapiGateway $wapi;

    public function __construct(ProviderInstance $instance, array $credentials, ProviderHttpClient $http, CacheRepository $cache, Clock $clock)
    {
        $this->wapi = new WapiGateway($instance, $credentials, $http, $cache, $clock);
    }

    public static function providerKey(): string
    {
        return 'wedos_zone';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['wapi-json-2025'];
    }

    public function capabilities(): array
    {
        return ['zone.create' => true, 'zone.delete' => true, 'rrset.patch' => 'two_phase_commit', 'dnssec' => 'registry_keyset', 'axfr.export' => false, 'secondary' => 'axfr_tsig'];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $this->wapi->command('ping', [], critical: true);

            return new ProviderHealth(true, null, (int) ((hrtime(true) - $started) / 1_000_000));
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        return 'wapi-json';
    }

    public function createZone(string $zone, array $options = []): ProviderResult
    {
        if ($this->zoneExists($zone)) {
            return ProviderResult::completed(null, ['zone' => $zone], alreadyExisted: true);
        }
        if (! empty($options['secondary_of'])) {
            $r = $this->wapi->command('dns-domain-add', ['name' => $zone, 'type' => 'secondary', 'primary_ip' => $options['secondary_of'], 'tsig_key' => $options['tsig_key'] ?? null], critical: true);
            $this->wapi->command('dns-domain-axfr-run', ['name' => $zone], critical: true);

            return ProviderResult::completed(null, ['zone' => $zone, 'mode' => 'secondary', 'raw' => $r['data']]);
        }
        $r = $this->wapi->command('dns-domain-add', ['name' => $zone], critical: true);

        return ProviderResult::completed(null, ['zone' => $zone, 'raw' => $r['data']]);
    }

    public function deleteZone(string $zone): ProviderResult
    {
        if (! $this->zoneExists($zone)) {
            return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
        }
        $this->wapi->command('dns-domain-delete', ['name' => $zone], critical: true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function zoneExists(string $zone): bool
    {
        try {
            $this->wapi->command('dns-domain-info', ['name' => $zone]);

            return true;
        } catch (ProviderException $e) {
            if (in_array($e->errorCode, [ProviderErrorCode::NOT_FOUND, ProviderErrorCode::VALIDATION], true)) {
                return false;
            }
            throw $e;
        }
    }

    public function listRecords(string $zone): array
    {
        $r = $this->wapi->command('dns-rows-list', ['domain' => $zone]);
        $out = [];
        foreach ((array) ($r['data']['row'] ?? $r['data']['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtoupper((string) $row['rdtype']);
            $content = (string) $row['rdata'];
            $prio = null;
            if (in_array($type, ['MX', 'SRV'], true) && preg_match('/^(\d+)\s+(.+)$/', $content, $m)) {
                $prio = (int) $m[1];
                $content = $m[2];
            }
            $out[] = ['name' => ((string) $row['name']) === '' ? '@' : (string) $row['name'], 'type' => $type, 'content' => $content, 'ttl' => (int) $row['ttl'], 'prio' => $prio, 'remote_id' => (string) $row['ID']];
        }

        return $out;
    }

    /** Stages rows with dns-row-add/update/delete and publishes with ONE dns-domain-commit; a failed commit leaves the batch pending. */
    public function applyChanges(string $zone, array $changes): ProviderResult
    {
        $current = $this->listRecords($zone);
        $staged = 0;
        foreach ($changes as $change) {
            $record = $change['record'];
            $name = ($record['name'] ?? '@') === '@' ? '' : (string) $record['name'];
            $rdata = isset($record['prio']) && $record['prio'] !== null && in_array(strtoupper((string) $record['type']), ['MX', 'SRV'], true) ? $record['prio'].' '.$record['content'] : (string) $record['content'];
            switch ($change['op']) {
                case 'add':
                    $this->wapi->command('dns-row-add', ['domain' => $zone, 'name' => $name, 'ttl' => (int) ($record['ttl'] ?? 3600), 'type' => strtoupper((string) $record['type']), 'rdata' => $rdata], critical: true);
                    $staged++;
                    break;
                case 'delete':
                    $id = $this->findRowId($current, $record);
                    if ($id !== null) {
                        $this->wapi->command('dns-row-delete', ['domain' => $zone, 'row_id' => $id], critical: true);
                        $staged++;
                    }
                    break;
                case 'update':
                    $id = $this->findRowId($current, (array) ($change['previous'] ?? $record));
                    if ($id === null) {
                        $this->wapi->command('dns-row-add', ['domain' => $zone, 'name' => $name, 'ttl' => (int) ($record['ttl'] ?? 3600), 'type' => strtoupper((string) $record['type']), 'rdata' => $rdata], critical: true);
                    } else {
                        $this->wapi->command('dns-row-update', ['domain' => $zone, 'row_id' => $id, 'ttl' => (int) ($record['ttl'] ?? 3600), 'rdata' => $rdata], critical: true);
                    }
                    $staged++;
                    break;
            }
        }
        if ($staged === 0) {
            return ProviderResult::completed(null, ['noop' => true]);
        }
        $r = $this->wapi->command('dns-domain-commit', ['name' => $zone], critical: true);

        return ProviderResult::completed(null, ['staged' => $staged, 'committed' => true, 'raw' => $r['data']]);
    }

    public function exportZone(string $zone): string
    {
        $lines = ["\$ORIGIN {$zone}."];
        foreach ($this->listRecords($zone) as $r) {
            $content = $r['type'] === 'TXT' ? '"'.str_replace('"', '\"', $r['content']).'"' : $r['content'];
            $lines[] = sprintf('%s %d IN %s %s%s', $r['name'], $r['ttl'], $r['type'], $r['prio'] !== null ? $r['prio'].' ' : '', $content);
        }

        return implode("\n", $lines)."\n";
    }

    public function dnssecStatus(string $zone): array
    {
        return ['enabled' => false, 'ds' => [], 'keys' => []]; // DNSSEC for WEDOS-hosted zones is managed at the registry keyset level
    }

    public function enableDnssec(string $zone): ProviderResult
    {
        throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, 'DNSSEC signing is performed on the PowerDNS hidden primary; WEDOS Zone acts as secondary');
    }

    public function disableDnssec(string $zone): ProviderResult
    {
        throw new ProviderException('wedos', ProviderErrorCode::VALIDATION, 'DNSSEC signing is performed on the PowerDNS hidden primary; WEDOS Zone acts as secondary');
    }

    private function findRowId(array $current, array $record): ?string
    {
        foreach ($current as $row) {
            if (strtolower($row['name']) === strtolower((string) ($record['name'] ?? '@')) && $row['type'] === strtoupper((string) $record['type']) && $row['content'] === (string) $record['content']) {
                return $row['remote_id'];
            }
        }

        return null;
    }
}
