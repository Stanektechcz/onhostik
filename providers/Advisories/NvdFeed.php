<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Illuminate\Support\Carbon;
use Onhost\Providers\Contracts\AdvisoryBatch;
use Onhost\Providers\Contracts\AdvisoryFeed;

/**
 * NVD CVE API 2.0, read by the time a CVE last changed (`lastModStartDate` … `lastModEndDate`, at most 120 days a request, 2 000
 * a page). Only CVEs whose configurations name a product the platform runs are kept (`onhost.advisories.nvd_cpe`: `vendor:product`
 * or `vendor:*`); their CPE matches become version ranges in the `cpe` ecosystem, WordPress core as `wordpress-core`.
 *
 * Without an API key NVD allows 5 requests in 30 seconds, with one 50; a run reads at most `nvd_max_pages` pages and the cursor
 * (`window start|next index|window end`) resumes inside a window it could not finish.
 */
final class NvdFeed implements AdvisoryFeed
{
    private const PAGE = 2000;

    public function __construct(private readonly FeedHttp $http) {}

    public function key(): string
    {
        return 'nvd';
    }

    public function enabled(): bool
    {
        return in_array('nvd', (array) config('onhost.advisories.feeds', []), true);
    }

    public function fetch(?string $cursor): AdvisoryBatch
    {
        [$start, $index, $end] = $this->window($cursor);
        $key = (string) config('onhost.advisories.nvd_api_key', '');
        $out = [];
        for ($page = 0; $page < max(1, (int) config('onhost.advisories.nvd_max_pages', 5)); $page++) {
            $json = $this->http->json('nvd', 'GET', (string) config('onhost.advisories.nvd_url'), 'nvd-cves', [
                'lastModStartDate' => $start->format('Y-m-d\TH:i:s.v\Z'), 'lastModEndDate' => $end->format('Y-m-d\TH:i:s.v\Z'), 'resultsPerPage' => self::PAGE, 'startIndex' => $index,
            ], $key !== '' ? ['apiKey' => $key] : []);
            foreach ((array) ($json['vulnerabilities'] ?? []) as $row) {
                $advisory = $this->advisory((array) ($row['cve'] ?? []));
                if ($advisory !== null) {
                    $out[] = $advisory;
                }
            }
            $index += max(1, (int) ($json['resultsPerPage'] ?? count((array) ($json['vulnerabilities'] ?? []))));
            if ($index >= (int) ($json['totalResults'] ?? 0)) {
                return new AdvisoryBatch($out, $end->format('Y-m-d\TH:i:s\Z'));
            }
        }

        return new AdvisoryBatch($out, $start->format('Y-m-d\TH:i:s\Z').'|'.$index.'|'.$end->format('Y-m-d\TH:i:s\Z'), false);
    }

    /** @return array{0:Carbon, 1:int, 2:Carbon} */
    private function window(?string $cursor): array
    {
        $now = now()->utc()->startOfSecond();
        $parts = explode('|', (string) $cursor);
        if (count($parts) === 3) {
            return [Carbon::parse($parts[0])->utc(), max(0, (int) $parts[1]), Carbon::parse($parts[2])->utc()];
        }
        $start = $cursor !== null && $cursor !== '' ? Carbon::parse($cursor)->utc() : $now->copy()->subDays(max(1, (int) config('onhost.advisories.initial_days', 14)));
        // NVD refuses a window longer than 120 days; a cursor older than that is read up in steps
        $end = $start->copy()->addDays(119)->lessThan($now) ? $start->copy()->addDays(119) : $now;

        return [$start, 0, $end];
    }

    /** @param array<string,mixed> $cve @return array<string,mixed>|null */
    private function advisory(array $cve): ?array
    {
        $id = strtoupper((string) ($cve['id'] ?? ''));
        if (preg_match('/^CVE-\d{4}-\d{4,}$/', $id) !== 1 || ($cve['vulnStatus'] ?? '') === 'Rejected') {
            return null;
        }
        $description = collect((array) ($cve['descriptions'] ?? []))->firstWhere('lang', 'en')['value'] ?? '';
        // NVD names a WordPress add-on by its CPE (`vendor:product`, target software `wordpress`) and says only in words whether it is a theme
        $theme = preg_match('/\btheme\b[^.]{0,40}\bwordpress\b/i', (string) $description) === 1;
        $affected = [];
        foreach ((array) ($cve['configurations'] ?? []) as $configuration) {
            foreach ((array) ($configuration['nodes'] ?? []) as $node) {
                foreach ((array) ($node['cpeMatch'] ?? []) as $match) {
                    $range = $this->range((array) $match, $theme);
                    if ($range !== null && ! in_array($range, $affected, true)) {
                        $affected[] = $range;
                    }
                }
            }
        }
        if ($affected === []) {
            return null;
        }
        $metric = data_get($cve, 'metrics.cvssMetricV31.0.cvssData') ?? data_get($cve, 'metrics.cvssMetricV40.0.cvssData') ?? data_get($cve, 'metrics.cvssMetricV30.0.cvssData');
        $score = is_numeric($metric['baseScore'] ?? null) ? round((float) $metric['baseScore'], 1) : null;

        return [
            'key' => $id, 'source' => 'nvd', 'external_id' => $id, 'aliases' => [$id],
            'title' => AdvisoryBatch::text($id.': '.mb_substr((string) $description, 0, 200), 300), 'summary' => AdvisoryBatch::text($description, 4000),
            'severity' => AdvisoryBatch::severity($score, is_string($metric['baseSeverity'] ?? null) ? $metric['baseSeverity'] : null), 'cvss' => $score, 'kev' => isset($cve['cisaExploitAdd']),
            'kev_added_on' => is_string($cve['cisaExploitAdd'] ?? null) ? substr($cve['cisaExploitAdd'], 0, 10) : null,
            'affected' => array_slice($affected, 0, 50), 'references' => AdvisoryBatch::references((array) ($cve['references'] ?? [])),
            'published_at' => (string) ($cve['published'] ?? ''), 'modified_at' => (string) ($cve['lastModified'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $match @return array<string,mixed>|null */
    private function range(array $match, bool $theme = false): ?array
    {
        if (! ($match['vulnerable'] ?? false)) {
            return null;
        }
        $parts = explode(':', (string) ($match['criteria'] ?? ''));
        if (count($parts) < 6 || $parts[0] !== 'cpe' || $parts[2] !== 'a' && $parts[2] !== 'o') {
            return null;
        }
        $product = strtolower($parts[3].':'.$parts[4]);
        // a plugin or theme for WordPress (target software `wordpress`): kept without a Wordfence key, matched by its slug, which the
        // CPE product spells with underscores (`contact_form_7` → `contact-form-7`)
        $addon = $product !== 'wordpress:wordpress' && strtolower($parts[10] ?? '') === 'wordpress' && preg_match('/^[a-z0-9][a-z0-9_.-]{0,159}$/', strtolower($parts[4])) === 1;
        if (! $addon && ! $this->watched($product)) {
            return null;
        }
        [$ecosystem, $product] = match (true) {
            $product === 'wordpress:wordpress' => ['wordpress-core', 'wordpress'],
            $addon => [$theme ? 'wordpress-theme' : 'wordpress-plugin', str_replace('_', '-', strtolower($parts[4]))],
            default => ['cpe', $product],
        };
        $exact = AdvisoryBatch::version($parts[5]);
        $range = array_filter([
            'ecosystem' => $ecosystem, 'product' => $product,
            'introduced' => AdvisoryBatch::version($match['versionStartIncluding'] ?? null) ?? AdvisoryBatch::version($match['versionStartExcluding'] ?? null),
            'fixed' => AdvisoryBatch::version($match['versionEndExcluding'] ?? null),
            'last_affected' => AdvisoryBatch::version($match['versionEndIncluding'] ?? null),
            'versions' => $exact !== null ? [$exact] : null,
        ], fn ($v) => $v !== null);

        return $range;
    }

    private function watched(string $product): bool
    {
        [$vendor] = explode(':', $product);
        foreach ((array) config('onhost.advisories.nvd_cpe', []) as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === $product || $pattern === $vendor.':*') {
                return true;
            }
        }

        return false;
    }
}
