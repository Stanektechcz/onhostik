<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Onhost\Providers\Contracts\AdvisoryBatch;
use Onhost\Providers\Contracts\AdvisoryLookup;

/**
 * OSV.dev (open, no key): `POST /querybatch` with the packages we run answers which vulnerability ids cover them, `GET /vulns/{id}`
 * gives each one's ranges. Covers the GitHub advisory database and the distributions, so the control plane's own Composer
 * libraries (`Packagist`) and, once the node agent lists them, Debian packages. At most `osv_max_vulns` details per run.
 */
final class OsvLookup implements AdvisoryLookup
{
    private const BATCH = 500;

    public function __construct(private readonly FeedHttp $http) {}

    public function key(): string
    {
        return 'osv';
    }

    public function enabled(): bool
    {
        return in_array('osv', (array) config('onhost.advisories.feeds', []), true);
    }

    public function ecosystems(): array
    {
        return ['Packagist', 'npm', 'Debian:11', 'Debian:12', 'Debian:13'];
    }

    public function lookup(array $packages): array
    {
        $base = rtrim((string) config('onhost.advisories.osv_url'), '/');
        $ids = [];
        foreach (array_chunk($packages, self::BATCH) as $chunk) {
            $json = $this->http->json('osv', 'POST', $base.'/querybatch', 'osv-querybatch', body: ['queries' => array_map(fn (array $p) => ['package' => ['name' => $p['product'], 'ecosystem' => $p['ecosystem']], 'version' => $p['version']], $chunk)]);
            foreach ((array) ($json['results'] ?? []) as $result) {
                foreach ((array) ($result['vulns'] ?? []) as $vuln) {
                    $id = (string) ($vuln['id'] ?? '');
                    if (preg_match('/^[A-Za-z0-9._:-]{3,80}$/', $id) === 1) {
                        $ids[$id] = true;
                    }
                }
            }
        }
        $out = [];
        foreach (array_slice(array_keys($ids), 0, max(1, (int) config('onhost.advisories.osv_max_vulns', 200))) as $id) {
            $advisory = $this->advisory($this->http->json('osv', 'GET', $base.'/vulns/'.rawurlencode($id), 'osv-vuln'));
            if ($advisory !== null) {
                $out[] = $advisory;
            }
        }

        return $out;
    }

    /** @param array<mixed> $vuln @return array<string,mixed>|null */
    private function advisory(array $vuln): ?array
    {
        $id = (string) ($vuln['id'] ?? '');
        if ($id === '' || isset($vuln['withdrawn'])) {
            return null;
        }
        $affected = [];
        foreach ((array) ($vuln['affected'] ?? []) as $entry) {
            $ecosystem = (string) data_get($entry, 'package.ecosystem', '');
            $product = (string) data_get($entry, 'package.name', '');
            if ($ecosystem === '' || $product === '') {
                continue;
            }
            foreach ((array) ($entry['ranges'] ?? []) as $range) {
                if (! in_array($range['type'] ?? '', ['ECOSYSTEM', 'SEMVER'], true)) {
                    continue;
                }
                $introduced = null;
                foreach ((array) ($range['events'] ?? []) as $event) {
                    if (array_key_exists('introduced', (array) $event)) {
                        $introduced = AdvisoryBatch::version($event['introduced'] === '0' ? null : $event['introduced']);
                    } elseif (isset($event['fixed']) || isset($event['last_affected'])) {
                        $affected[] = array_filter(['ecosystem' => $ecosystem, 'product' => $product, 'introduced' => $introduced, 'fixed' => AdvisoryBatch::version($event['fixed'] ?? null), 'last_affected' => AdvisoryBatch::version($event['last_affected'] ?? null)], fn ($v) => $v !== null);
                        $introduced = null;
                    }
                }
                if ($introduced !== null) { // introduced and never fixed
                    $affected[] = ['ecosystem' => $ecosystem, 'product' => $product, 'introduced' => $introduced];
                }
            }
        }
        if ($affected === []) {
            return null;
        }
        $aliases = array_values(array_filter(array_map('strval', (array) ($vuln['aliases'] ?? [])), fn (string $a) => preg_match('/^[A-Za-z0-9._:-]{3,80}$/', $a) === 1));
        $cve = collect($aliases)->first(fn (string $a) => preg_match('/^CVE-\d{4}-\d{4,}$/', $a) === 1) ?? (preg_match('/^CVE-\d{4}-\d{4,}$/', $id) === 1 ? $id : null);
        $label = data_get($vuln, 'database_specific.severity');

        return [
            'key' => $cve ?? 'osv:'.$id, 'source' => 'osv', 'external_id' => $id, 'aliases' => array_values(array_unique(array_merge([$id], $aliases))),
            'title' => AdvisoryBatch::text($vuln['summary'] ?? $id, 300), 'summary' => AdvisoryBatch::text($vuln['details'] ?? '', 4000),
            'severity' => AdvisoryBatch::severity(null, is_string($label) ? $label : null), 'cvss' => null, 'kev' => false, 'kev_added_on' => null,
            'affected' => array_slice($affected, 0, 50), 'references' => AdvisoryBatch::references((array) ($vuln['references'] ?? [])),
            'published_at' => (string) ($vuln['published'] ?? ''), 'modified_at' => (string) ($vuln['modified'] ?? ''),
        ];
    }
}
