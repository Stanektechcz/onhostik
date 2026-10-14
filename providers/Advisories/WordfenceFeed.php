<?php

declare(strict_types=1);

namespace Onhost\Providers\Advisories;

use Onhost\Providers\Contracts\AdvisoryBatch;
use Onhost\Providers\Contracts\AdvisoryFeed;

/**
 * Wordfence Intelligence vulnerability feed (WordPress core, plugins and themes; free, API key, `Authorization: Bearer`). The feed
 * is one document keyed by the record's id; each record lists the affected software (`type` core | plugin | theme, `slug`), its
 * affected ranges (`from_version`/`to_version` with inclusiveness, `*` = any) and the versions that carry the fix. The cursor is
 * the newest `updated` read; the first run takes `initial_days` back. Without a key the feed is not asked (doctor says so).
 */
final class WordfenceFeed implements AdvisoryFeed
{
    public function __construct(private readonly FeedHttp $http) {}

    public function key(): string
    {
        return 'wordfence';
    }

    public function enabled(): bool
    {
        return in_array('wordfence', (array) config('onhost.advisories.feeds', []), true) && (string) config('onhost.advisories.wordfence_api_key', '') !== '';
    }

    public function fetch(?string $cursor): AdvisoryBatch
    {
        $json = $this->http->json('wordfence', 'GET', (string) config('onhost.advisories.wordfence_url'), 'wordfence-vulnerabilities',
            headers: ['Authorization' => 'Bearer '.(string) config('onhost.advisories.wordfence_api_key')], maxBytes: 268_435_456, timeout: 120);
        $since = $cursor ?? now()->utc()->subDays(max(1, (int) config('onhost.advisories.initial_days', 14)))->format('Y-m-d H:i:s');
        $newest = $cursor;
        $out = [];
        foreach ($json as $id => $record) {
            if (! is_array($record)) {
                continue;
            }
            $updated = substr((string) ($record['updated'] ?? $record['published'] ?? ''), 0, 19);
            if ($updated === '' || $updated <= $since || ($record['informational'] ?? false) === true) {
                continue;
            }
            $newest = $newest === null || $updated > $newest ? $updated : $newest;
            $advisory = $this->advisory((string) ($record['id'] ?? $id), $record);
            if ($advisory !== null) {
                $out[] = $advisory;
            }
        }

        return new AdvisoryBatch($out, $newest);
    }

    /** @param array<string,mixed> $record @return array<string,mixed>|null */
    private function advisory(string $id, array $record): ?array
    {
        $affected = [];
        foreach ((array) ($record['software'] ?? []) as $software) {
            $type = (string) ($software['type'] ?? '');
            $ecosystem = ['core' => 'wordpress-core', 'plugin' => 'wordpress-plugin', 'theme' => 'wordpress-theme'][$type] ?? null;
            $slug = $type === 'core' ? 'wordpress' : strtolower((string) ($software['slug'] ?? ''));
            if ($ecosystem === null || preg_match('/^[a-z0-9][a-z0-9_.-]{0,159}$/', $slug) !== 1) {
                continue;
            }
            $patched = array_values(array_filter(array_map(fn ($v) => AdvisoryBatch::version($v), (array) ($software['patched_versions'] ?? []))));
            foreach ((array) ($software['affected_versions'] ?? []) as $range) {
                $range = (array) $range;
                $to = AdvisoryBatch::version($range['to_version'] ?? null);
                $toInclusive = (bool) ($range['to_inclusive'] ?? true);
                $affected[] = array_filter([
                    'ecosystem' => $ecosystem, 'product' => $slug, 'name' => AdvisoryBatch::text($software['name'] ?? $slug, 160),
                    'introduced' => AdvisoryBatch::version($range['from_version'] ?? null),
                    'fixed' => $toInclusive ? $this->firstAbove($patched, $to) : $to,
                    'last_affected' => $toInclusive ? $to : null,
                ], fn ($v) => $v !== null && $v !== '');
            }
        }
        if ($affected === []) {
            return null;
        }
        $cve = strtoupper(trim((string) ($record['cve'] ?? '')));
        $cve = preg_match('/^CVE-\d{4}-\d{4,}$/', $cve) === 1 ? $cve : null;
        $score = is_numeric($record['cvss']['score'] ?? null) ? round((float) $record['cvss']['score'], 1) : null;

        return [
            'key' => $cve ?? 'wordfence:'.$id, 'source' => 'wordfence', 'external_id' => mb_substr($id, 0, 100), 'aliases' => array_values(array_filter([$cve, 'wordfence:'.$id])),
            'title' => AdvisoryBatch::text($record['title'] ?? $id, 300), 'summary' => AdvisoryBatch::text($record['description'] ?? '', 4000),
            'severity' => AdvisoryBatch::severity($score, is_string($record['cvss']['rating'] ?? null) ? $record['cvss']['rating'] : null), 'cvss' => $score, 'kev' => false, 'kev_added_on' => null,
            'affected' => array_slice($affected, 0, 50), 'references' => AdvisoryBatch::references(array_merge((array) ($record['references'] ?? []), [$record['cve_link'] ?? null])),
            'published_at' => (string) ($record['published'] ?? ''), 'modified_at' => (string) ($record['updated'] ?? ''),
        ];
    }

    /** @param list<string> $patched */
    private function firstAbove(array $patched, ?string $to): ?string
    {
        $above = array_values(array_filter($patched, fn (string $v) => $to === null || version_compare($v, $to, '>')));
        usort($above, 'version_compare');

        return $above[0] ?? null;
    }
}
