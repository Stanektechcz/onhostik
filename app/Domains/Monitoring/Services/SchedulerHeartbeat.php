<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Records that Laravel's scheduler actually ran, so a dead cron (the classic
 * "scheduled jobs silently stopped weeks ago" outage) becomes visible instead
 * of invisible.
 *
 * The heartbeat is written by a per-minute scheduled command; staleness is read
 * on demand (system-health page / external monitor) — a self-scheduled checker
 * couldn't help, since dead cron can't detect itself.
 */
class SchedulerHeartbeat
{
    private const KEY = 'scheduler:last_run';

    public function record(): void
    {
        Cache::forever(self::KEY, now()->timestamp);
    }

    public function lastRunAt(): ?Carbon
    {
        $timestamp = Cache::get(self::KEY);

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    /** Stale = never ran, or not within the last $minutes (cron likely down). */
    public function isStale(int $minutes = 5): bool
    {
        $last = $this->lastRunAt();

        return $last === null || $last->lt(now()->subMinutes($minutes));
    }
}
