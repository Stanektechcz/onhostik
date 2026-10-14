<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Illuminate\Support\Carbon;
use Onhost\Domain\Advisories\Models\Advisory;
use Onhost\Domain\Advisories\Models\AdvisorySource;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Providers\Contracts\AdvisoryFeed;
use Onhost\Providers\Contracts\AdvisoryLookup;
use Throwable;

/**
 * Reads every switched-on feed from where it stopped and asks the lookup databases about what we run (SN-1). Advisories are merged
 * by their CVE: the sources are listed, each source's affected ranges replace only its own, the worst severity and an exploited
 * flag win. A feed that fails keeps its cursor (the next run reads the same window again) and records the error for the staff page.
 */
final class AdvisoryIngest
{
    /** Who words an advisory best, when several describe it. */
    private const TEXT_PRIORITY = ['wordfence' => 4, 'osv' => 3, 'kev' => 2, 'nvd' => 1];

    private const SEVERITY_RANK = ['unknown' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    /**
     * @param  iterable<AdvisoryFeed>  $feeds
     * @param  iterable<AdvisoryLookup>  $lookups
     */
    public function __construct(private readonly iterable $feeds, private readonly iterable $lookups, private readonly Redactor $redactor) {}

    /** @param list<string>|null $only feed keys to read (null = all switched on) @return array{feeds:int, failed:int, advisories:int, changed:list<string>} */
    public function run(?array $only = null): array
    {
        $changed = [];
        $feeds = 0;
        $failed = 0;
        foreach ($this->feeds as $feed) {
            if (! $feed->enabled() || ($only !== null && ! in_array($feed->key(), $only, true))) {
                continue;
            }
            $feeds++;
            $source = $this->source($feed->key());
            try {
                $batch = $feed->fetch($source->cursor);
                foreach ($batch->advisories as $row) {
                    $changed[] = $this->upsert($row);
                }
                $source->forceFill(['cursor' => $batch->cursor, 'last_success_at' => now(), 'last_error' => null, 'fetched' => count($batch->advisories)]);
            } catch (Throwable $e) {
                $failed++;
                $source->forceFill(['last_error' => mb_substr($this->redactor->redactString($e->getMessage()), 0, 300)]);
            }
            $source->forceFill(['last_run_at' => now()])->save();
        }
        foreach ($this->lookups as $lookup) {
            if (! $lookup->enabled() || ($only !== null && ! in_array($lookup->key(), $only, true))) {
                continue;
            }
            $feeds++;
            $source = $this->source($lookup->key());
            $packages = InventoryItem::query()->whereIn('ecosystem', $lookup->ecosystems())->get(['ecosystem', 'product', 'version'])
                ->map(fn (InventoryItem $i) => ['ecosystem' => $i->ecosystem, 'product' => $i->product, 'version' => $i->version])->unique(fn (array $p) => implode('|', $p))->values()->all();
            try {
                $rows = $packages === [] ? [] : $lookup->lookup($packages);
                foreach ($rows as $row) {
                    $changed[] = $this->upsert($row);
                }
                $source->forceFill(['last_success_at' => now(), 'last_error' => null, 'fetched' => count($rows)]);
            } catch (Throwable $e) {
                $failed++;
                $source->forceFill(['last_error' => mb_substr($this->redactor->redactString($e->getMessage()), 0, 300)]);
            }
            $source->forceFill(['last_run_at' => now()])->save();
        }
        $changed = array_values(array_unique(array_filter($changed)));

        return ['feeds' => $feeds, 'failed' => $failed, 'advisories' => count($changed), 'changed' => $changed];
    }

    /** @param array<string,mixed> $row a normalized advisory (AdvisoryBatch) @return string the advisory's id */
    public function upsert(array $row): string
    {
        $source = (string) ($row['source'] ?? 'unknown');
        $key = mb_substr((string) ($row['key'] ?? ''), 0, 120);
        if ($key === '') {
            return '';
        }
        $advisory = Advisory::query()->where('key', $key)->first() ?? new Advisory(['key' => $key, 'sources' => [], 'affected' => [], 'severity' => 'unknown', 'title' => $key]);
        $known = (array) $advisory->sources;
        $bestKnown = max(array_map(fn ($s) => self::TEXT_PRIORITY[$s] ?? 0, $known ?: ['none']));
        $wordsBetter = (self::TEXT_PRIORITY[$source] ?? 0) >= $bestKnown;
        $affected = array_values(array_filter((array) $advisory->affected, fn (array $a) => ($a['source'] ?? null) !== $source));
        foreach ((array) ($row['affected'] ?? []) as $range) {
            $affected[] = ['source' => $source] + (array) $range;
        }
        $severity = (string) ($row['severity'] ?? 'unknown');
        $advisory->forceFill([
            'sources' => array_values(array_unique(array_merge($known, [$source]))),
            'aliases' => array_values(array_unique(array_merge((array) $advisory->aliases, array_map('strval', (array) ($row['aliases'] ?? []))))),
            'title' => $wordsBetter && ($row['title'] ?? '') !== '' ? (string) $row['title'] : $advisory->title,
            'summary' => $wordsBetter && ($row['summary'] ?? '') !== '' ? (string) $row['summary'] : $advisory->summary,
            'severity' => (self::SEVERITY_RANK[$severity] ?? 0) > (self::SEVERITY_RANK[$advisory->severity] ?? 0) ? $severity : $advisory->severity,
            'cvss' => max((float) ($advisory->cvss ?? 0), (float) ($row['cvss'] ?? 0)) ?: null,
            'kev' => (bool) $advisory->kev || (bool) ($row['kev'] ?? false),
            'kev_added_on' => $advisory->kev_added_on ?? ($row['kev_added_on'] ?? null),
            'affected' => array_slice($affected, 0, 200),
            'references' => array_values(array_unique(array_merge((array) $advisory->references, (array) ($row['references'] ?? [])))),
            'published_at' => $advisory->published_at ?? self::time($row['published_at'] ?? null),
            'source_modified_at' => self::time($row['modified_at'] ?? null) ?? $advisory->source_modified_at,
        ])->save();

        return $advisory->id;
    }

    private function source(string $key): AdvisorySource
    {
        return AdvisorySource::query()->firstOrCreate(['key' => $key], ['fetched' => 0]);
    }

    private static function time(mixed $value): ?string
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value)->utc()->toDateTimeString() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
