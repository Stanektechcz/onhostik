<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * N14: the public status (`/v1/status`, behind `/stav` and status.onhost.cz) as a cached snapshot. Only allowlisted
 * fields leave the platform, so nothing tenant-specific (affected organizations, internal notes) can leak through a new
 * column. The last good snapshot is kept without expiry and served when building fails, so the page carries a value
 * during an outage. `incident.resolved` drops the short-lived copy (DomainServiceProvider).
 */
final class StatusSnapshot
{
    public const KEY = 'onhost:status:snapshot';

    public const LAST_GOOD_KEY = 'onhost:status:snapshot:last-good';

    private const TOP = ['overall', 'components', 'incidents', 'maintenance', 'generated_at'];

    private const COMPONENT = ['key', 'n', 'group', 'state', 'u', 'bad'];

    private const INCIDENT = ['number', 'title', 'sev', 'state', 'ui_state', 'state_label', 'components', 'impact', 'started_at', 'resolved_at', 'duration', 'hist', 'postmortem'];

    private const UPDATE = ['state', 'ui_state', 'at', 'note'];

    private const MAINTENANCE = ['number', 'title', 'components', 'starts_at', 'ends_at', 'impact', 'state', 'emergency', 'counts_toward_sla'];

    public function __construct(private readonly IncidentService $incidents) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        $cached = Cache::get(self::KEY);
        if (is_array($cached)) {
            return $cached;
        }
        try {
            $snapshot = self::allowlist($this->incidents->publicStatus());
        } catch (\Throwable $e) {
            Log::warning('status snapshot build failed, serving the last good one', ['exception' => $e::class]);
            $last = Cache::get(self::LAST_GOOD_KEY);
            if (is_array($last)) {
                return $last + ['stale' => true];
            }
            throw $e;
        }
        Cache::put(self::KEY, $snapshot, (int) config('onhost.status.snapshot_ttl', 60));
        Cache::forever(self::LAST_GOOD_KEY, $snapshot);

        return $snapshot;
    }

    public function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    public static function allowlist(array $status): array
    {
        $out = self::only($status, self::TOP);
        $out['components'] = array_map(fn ($c) => self::only((array) $c, self::COMPONENT), (array) ($out['components'] ?? []));
        $out['incidents'] = array_map(function ($i) {
            $i = self::only((array) $i, self::INCIDENT);
            $i['hist'] = array_map(fn ($u) => self::only((array) $u, self::UPDATE), (array) ($i['hist'] ?? []));

            return $i;
        }, (array) ($out['incidents'] ?? []));
        $out['maintenance'] = array_map(fn ($m) => self::only((array) $m, self::MAINTENANCE), (array) ($out['maintenance'] ?? []));

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }
}
