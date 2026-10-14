<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Onhost\Providers\Contracts\AdvisoryBatch;
use Onhost\Providers\Contracts\AdvisoryFeed;

/**
 * CISA Known Exploited Vulnerabilities (one public JSON catalogue, no key): a CVE in it is being exploited now. It names a vendor and
 * a product but no versions, so it marks advisories urgent (`kev`) rather than matching anything by itself; the versions come from
 * NVD, Wordfence or OSV under the same CVE. The cursor is the newest `dateAdded` read; the catalogue is downloaded whole each time.
 */
final class CisaKevFeed implements AdvisoryFeed
{
    public function __construct(private readonly FeedHttp $http) {}

    public function key(): string
    {
        return 'kev';
    }

    public function enabled(): bool
    {
        return in_array('kev', (array) config('onhost.advisories.feeds', []), true);
    }

    public function fetch(?string $cursor): AdvisoryBatch
    {
        $json = $this->http->json('kev', 'GET', (string) config('onhost.advisories.kev_url'), 'kev-catalog', maxBytes: 16_777_216);
        $since = $cursor ?? now()->subDays(max(1, (int) config('onhost.advisories.initial_days', 14)))->toDateString();
        $newest = $cursor;
        $out = [];
        foreach ((array) ($json['vulnerabilities'] ?? []) as $row) {
            $cve = strtoupper(trim((string) ($row['cveID'] ?? '')));
            $added = (string) ($row['dateAdded'] ?? '');
            if (preg_match('/^CVE-\d{4}-\d{4,}$/', $cve) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $added) !== 1 || $added < $since) {
                continue;
            }
            $newest = $newest === null || $added > $newest ? $added : $newest;
            $out[] = [
                'key' => $cve, 'source' => 'kev', 'external_id' => $cve, 'aliases' => [$cve],
                'title' => AdvisoryBatch::text($row['vulnerabilityName'] ?? $cve, 300),
                'summary' => AdvisoryBatch::text(trim(($row['shortDescription'] ?? '').' '.(isset($row['requiredAction']) ? 'CISA: '.$row['requiredAction'] : '')), 4000),
                'severity' => 'unknown', 'cvss' => null, 'kev' => true, 'kev_added_on' => $added,
                'affected' => [['ecosystem' => 'vendor', 'product' => strtolower(AdvisoryBatch::text(($row['vendorProject'] ?? '').':'.($row['product'] ?? ''), 160))]],
                'references' => AdvisoryBatch::references(['https://nvd.nist.gov/vuln/detail/'.$cve]),
                'published_at' => $added.'T00:00:00Z', 'modified_at' => $added.'T00:00:00Z',
            ];
        }

        return new AdvisoryBatch($out, $newest);
    }
}
