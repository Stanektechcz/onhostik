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
            if ($type === 'TXT' && str_starts_with($content, '"') && preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', $content, $parts) > 0) {
                $content = implode('', array_map(fn (string $part) => (string) preg_replace('/\\\\(.)/s', '$1', $part), $parts[1])); // the value, as the platform holds it
            }
            $out[] = ['name' => ((string) $row['name']) === '' ? '@' : (string) $row['name'], 'type' => $type, 'content' => $content, 'ttl' => (int) $row['ttl'], 'prio' => $prio, 'remote_id' => (string) $row['ID']];
        }

        return $out;
    }

    /**
     * Stages rows with dns-row-add/update/delete and publishes with ONE dns-domain-commit; a failed commit leaves the batch
     * pending. The rows are sent one by one, so a batch can fail half-way — and it is repeated as a whole. Repeating it must
     * arrive at the same zone: a row that is already there is not added again (it used to be — every retry doubled what the
     * first attempt had staged), and the commit is sent even when nothing was left to stage (the first attempt may have staged
     * everything and failed at the commit; without it those rows would never be published).
     */
    public function applyChanges(string $zone, array $changes): ProviderResult
    {
        if ($changes === []) {
            return ProviderResult::completed(null, ['noop' => true]);
        }
        $current = $this->listRecords($zone);
        $staged = 0;
        foreach ($changes as $change) {
            $record = (array) $change['record'];
            switch ($change['op']) {
                case 'add':
                    $staged += $this->stageRow($zone, $current, $record);
                    break;
                case 'delete':
                    $staged += $this->unstageRow($zone, $current, $record);
                    break;
                case 'update':
                    $previous = (array) ($change['previous'] ?? $record);
                    $row = $this->findRow($current, $previous);
                    // `dns-row-update` takes a TTL and the data — never a name or a type: a record that was renamed (or whose type
                    // changed) kept its old name at WEDOS while the platform held the new one. It is a row removed and a row added.
                    if ($row !== null && $this->sameOwner($previous, $record)) {
                        $this->wapi->command('dns-row-update', ['domain' => $zone, 'row_id' => $row['remote_id'], 'ttl' => (int) ($record['ttl'] ?? 3600), 'rdata' => $this->rdata($record)], critical: true);
                        $current = array_map(fn (array $r) => $r['remote_id'] === $row['remote_id'] ? array_merge($r, ['content' => (string) $record['content'], 'prio' => $record['prio'] ?? null, 'ttl' => (int) ($record['ttl'] ?? 3600)]) : $r, $current);
                        $staged++;
                    } else {
                        $staged += $this->unstageRow($zone, $current, $previous) + $this->stageRow($zone, $current, $record);
                    }
                    break;
            }
        }
        $r = $this->wapi->command('dns-domain-commit', ['name' => $zone], critical: true);

        return ProviderResult::completed(null, ['staged' => $staged, 'committed' => true, 'raw' => $r['data']]);
    }

    /** Adds a row unless the zone has it already; a TTL that differs is brought in line. @param list<array<string,mixed>> $current */
    private function stageRow(string $zone, array &$current, array $record): int
    {
        $ttl = (int) ($record['ttl'] ?? 3600);
        $row = $this->findRow($current, $record);
        if ($row !== null) {
            if ((int) $row['ttl'] === $ttl) {
                return 0;
            }
            $this->wapi->command('dns-row-update', ['domain' => $zone, 'row_id' => $row['remote_id'], 'ttl' => $ttl, 'rdata' => $this->rdata($record)], critical: true);

            return 1;
        }
        $this->wapi->command('dns-row-add', ['domain' => $zone, 'name' => ($record['name'] ?? '@') === '@' ? '' : (string) $record['name'], 'ttl' => $ttl, 'type' => strtoupper((string) $record['type']), 'rdata' => $this->rdata($record)], critical: true);
        $current[] = ['name' => (string) ($record['name'] ?? '@'), 'type' => strtoupper((string) $record['type']), 'content' => (string) $record['content'], 'ttl' => $ttl, 'prio' => $record['prio'] ?? null, 'remote_id' => ''];

        return 1;
    }

    /** Removes a row when the zone has it. @param list<array<string,mixed>> $current */
    private function unstageRow(string $zone, array &$current, array $record): int
    {
        $row = $this->findRow($current, $record);
        if ($row === null || $row['remote_id'] === '') {
            return 0;
        }
        $this->wapi->command('dns-row-delete', ['domain' => $zone, 'row_id' => $row['remote_id']], critical: true);
        $current = array_values(array_filter($current, fn (array $r) => $r['remote_id'] !== $row['remote_id']));

        return 1;
    }

    private function rdata(array $record): string
    {
        return isset($record['prio']) && $record['prio'] !== null && in_array(strtoupper((string) $record['type']), ['MX', 'SRV'], true) ? $record['prio'].' '.$record['content'] : (string) $record['content'];
    }

    private function sameOwner(array $a, array $b): bool
    {
        return strtolower((string) ($a['name'] ?? '@')) === strtolower((string) ($b['name'] ?? '@')) && strtoupper((string) ($a['type'] ?? '')) === strtoupper((string) ($b['type'] ?? ''));
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

    /**
     * The row of the zone that is this record: name, type and data — and the priority where the record has one (two MX rows may
     * point at the same host with different priorities).
     *
     * @param  list<array<string,mixed>>  $current
     * @return array<string,mixed>|null
     */
    private function findRow(array $current, array $record): ?array
    {
        foreach ($current as $row) {
            if (strtolower((string) $row['name']) === strtolower((string) ($record['name'] ?? '@')) && $row['type'] === strtoupper((string) $record['type']) && $row['content'] === (string) $record['content']
                && (($record['prio'] ?? null) === null || $row['prio'] === null || (int) $row['prio'] === (int) $record['prio'])) {
                return $row;
            }
        }

        return null;
    }
}
