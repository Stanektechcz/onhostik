<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Onhost\Providers\Contracts\AdvisoryBatch;
use Onhost\Providers\Contracts\AdvisoryFeed;

/**
 * The CVE List (CVEProject/cvelistV5, open, no key): the record of a CVE as its CNA published it, with the affected versions from
 * the first day — NVD adds its CPE analysis later, often weeks later. Read for WordPress plugins and themes, which have no keyless
 * feed of their own: a new record whose references name a wordpress.org plugin or theme (`wordpress.org/plugins/<slug>`,
 * `plugins.trac.wordpress.org/browser/<slug>`, the same for themes) is kept with the CNA's version ranges.
 *
 * `deltaLog.json` lists the records the CVE Project published lately (`new` per fetch); only new records are read, whole fetches
 * oldest first up to `cvelist_max_new` records a run, each from the repository's own raw URL (a link elsewhere is never followed).
 * The cursor is the `fetchTime` of the last fetch read, so a backlog is worked off over the next runs.
 */
final class CveListFeed implements AdvisoryFeed
{
    private const RAW = 'https://raw.githubusercontent.com/CVEProject/cvelistV5/';

    public function __construct(private readonly FeedHttp $http) {}

    public function key(): string
    {
        return 'cvelist';
    }

    public function enabled(): bool
    {
        return in_array('cvelist', (array) config('onhost.advisories.feeds', []), true);
    }

    public function fetch(?string $cursor): AdvisoryBatch
    {
        $log = $this->http->json('cvelist', 'GET', (string) config('onhost.advisories.cvelist_delta_url'), 'cvelist-delta', maxBytes: 33_554_432);
        $since = $cursor ?? now()->utc()->subDays(max(1, (int) config('onhost.advisories.initial_days', 14)))->format('Y-m-d\TH:i:s');
        $fetches = [];
        foreach ($log as $fetch) {
            $time = is_array($fetch) ? substr((string) ($fetch['fetchTime'] ?? ''), 0, 19) : '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $time) !== 1 || $time <= $since) {
                continue;
            }
            foreach ((array) ($fetch['new'] ?? []) as $record) {
                $link = is_array($record) ? (string) ($record['githubLink'] ?? '') : '';
                if (str_starts_with($link, self::RAW) && preg_match('#^[A-Za-z0-9/._-]+\.json$#', substr($link, strlen(self::RAW))) === 1) {
                    $fetches[$time][$link] = true;
                }
            }
            $fetches[$time] ??= [];
        }
        ksort($fetches);

        // whole fetches, oldest first, up to `cvelist_max_new` records a run: the cursor is the last fetch read, the next run goes on from it
        $max = max(1, (int) config('onhost.advisories.cvelist_max_new', 400));
        $links = [];
        $newest = $cursor;
        $complete = true;
        foreach ($fetches as $time => $records) {
            if ($links !== [] && count($links) + count($records) > $max) {
                $complete = false;
                break;
            }
            $links += $records;
            $newest = (string) $time;
        }
        $out = [];
        foreach (array_keys($links) as $link) {
            $advisory = $this->advisory($this->http->json('cvelist', 'GET', $link, 'cvelist-record', maxBytes: 2_097_152));
            if ($advisory !== null) {
                $out[] = $advisory;
            }
        }

        return new AdvisoryBatch($out, $newest, $complete);
    }

    /** @param array<mixed> $record @return array<string,mixed>|null */
    private function advisory(array $record): ?array
    {
        $id = strtoupper((string) data_get($record, 'cveMetadata.cveId', ''));
        if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id) !== 1 || data_get($record, 'cveMetadata.state') !== 'PUBLISHED') {
            return null;
        }
        $cna = (array) data_get($record, 'containers.cna', []);
        $references = array_values(array_filter(array_map(fn ($r) => is_array($r) ? (string) ($r['url'] ?? '') : '', (array) ($cna['references'] ?? []))));
        $addon = $this->addon($references);
        if ($addon === null) {
            return null;
        }
        [$ecosystem, $slug] = $addon;
        $affected = [];
        foreach ((array) ($cna['affected'] ?? []) as $product) {
            foreach ((array) ($product['versions'] ?? []) as $v) {
                if (! is_array($v) || ($v['status'] ?? '') !== 'affected') {
                    continue;
                }
                $start = AdvisoryBatch::version(in_array($v['version'] ?? null, ['0', '*', 'n/a'], true) ? null : ($v['version'] ?? null));
                $lessThan = AdvisoryBatch::version($v['lessThan'] ?? null);
                $orEqual = AdvisoryBatch::version($v['lessThanOrEqual'] ?? null);
                $range = $lessThan === null && $orEqual === null
                    ? ($start !== null ? ['versions' => [$start]] : [])
                    : array_filter(['introduced' => $start, 'fixed' => $lessThan, 'last_affected' => $orEqual], fn ($x) => $x !== null);
                if ($range !== []) {
                    $affected[] = ['ecosystem' => $ecosystem, 'product' => $slug, 'name' => AdvisoryBatch::text($product['product'] ?? $slug, 160)] + $range;
                }
            }
        }
        if ($affected === []) {
            return null;
        }
        $metric = collect((array) ($cna['metrics'] ?? []))->map(fn ($m) => is_array($m) ? ($m['cvssV3_1'] ?? $m['cvssV4_0'] ?? $m['cvssV3_0'] ?? null) : null)->filter()->first();
        $score = is_numeric($metric['baseScore'] ?? null) ? round((float) $metric['baseScore'], 1) : null;
        $description = collect((array) ($cna['descriptions'] ?? []))->first(fn ($d) => is_array($d) && str_starts_with((string) ($d['lang'] ?? ''), 'en'))['value'] ?? '';

        return [
            'key' => $id, 'source' => 'cvelist', 'external_id' => $id, 'aliases' => [$id],
            'title' => AdvisoryBatch::text(($cna['title'] ?? null) ?: $id.': '.mb_substr((string) $description, 0, 200), 300), 'summary' => AdvisoryBatch::text($description, 4000),
            'severity' => AdvisoryBatch::severity($score, is_string($metric['baseSeverity'] ?? null) ? $metric['baseSeverity'] : null), 'cvss' => $score, 'kev' => false, 'kev_added_on' => null,
            'affected' => array_slice($affected, 0, 50), 'references' => AdvisoryBatch::references($references),
            'published_at' => (string) data_get($record, 'cveMetadata.datePublished', ''), 'modified_at' => (string) data_get($record, 'cveMetadata.dateUpdated', ''),
        ];
    }

    /**
     * The WordPress.org plugin or theme a record's references name.
     *
     * @param  list<string>  $references
     * @return array{0:string, 1:string}|null
     */
    private function addon(array $references): ?array
    {
        foreach ($references as $url) {
            if (preg_match('#^https://(?:[a-z]{2,3}\.)?wordpress\.org/(plugins|themes)/([a-z0-9][a-z0-9-]{0,159})(?:/|$)#', $url, $m) === 1
                || preg_match('#^https://(plugins|themes)\.trac\.wordpress\.org/(?:browser|changeset/[0-9]+)/([a-z0-9][a-z0-9-]{0,159})(?:/|$)#', $url, $m) === 1) {
                return [$m[1] === 'themes' ? 'wordpress-theme' : 'wordpress-plugin', $m[2]];
            }
        }

        return null;
    }
}
