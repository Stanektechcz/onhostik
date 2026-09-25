<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;

/**
 * "Zálohy 30 dní" on a plan backed up every six hours (owner decision 18, TASK-0024): the newest `generations` backups
 * are kept as before, and beyond them the last backup of every calendar day inside the last `backup_days` days. Without
 * this a sub-daily plan kept seven generations whatever it sold — 42 hours of history for "30 days", 105 minutes for
 * "90 days" on shop-peak. Only under the rule `backups.as_sold`; with it off the scheduler prunes to the generation cap
 * as before — except a keeper stamped while the rule was on, which then lives out its own retention date instead of
 * going at once with every other customer's (review round 1). Nothing here deletes: it says which rows the prune may
 * take, and stamps the keepers of days that have ended.
 */
final class BackupDailyKeepers
{
    /** `meta` key on a backup kept as a day's keeper under the rule: it then leaves only through its retention date. */
    public const STAMP = 'kept_as_sold';

    /**
     * The completed, unprotected scheduled backups beyond the generation cap that are NOT the day's keeper, newest first,
     * at most `$limit`. Rows past their retention date are the expiry's business (the scheduler adds them separately).
     *
     * @return Collection<int, Backup>
     */
    public static function surplus(Service $service, int $generations, int $days, int $limit): Collection
    {
        $keepers = self::keeperIds($service, $days);
        // among the first days + limit rows beyond the cap, at most `days` are keepers, so up to `limit` deletable ones are found
        $beyond = self::scheduled($service)->orderByDesc('started_at')->orderByDesc('id')->skip(max(1, $generations))->take(max(1, $days) + $limit)->get();

        return $beyond->reject(fn (Backup $b) => isset($keepers[(string) $b->id]))->take($limit)->values();
    }

    /**
     * Stamps `meta.kept_as_sold` on the keeper of every day that has ENDED (today's may still be overtaken by a later
     * backup). Called by the prune while the rule is on; the stamp is what lets a keeper outlive the rule being switched
     * off. Returns how many rows it stamped.
     */
    public static function markKept(Service $service, int $days): int
    {
        $ended = array_keys(self::keeperIds($service, $days, endedDaysOnly: true));
        if ($ended === []) {
            return 0;
        }
        $stamped = 0;
        foreach (Backup::query()->whereIn('id', $ended)->whereNull('meta->'.self::STAMP)->get() as $backup) {
            $backup->forceFill(['meta' => array_merge((array) $backup->meta, [self::STAMP => true])])->save();
            $stamped++;
        }

        return $stamped;
    }

    /**
     * With the rule off: the completed, unprotected scheduled backups beyond the newest `$generations`, newest first, at
     * most `$limit` — exactly the old generation prune, except for a keeper stamped while the rule was on that still has a
     * retention date. Switching the rule off used to delete every such keeper across all customers at the next ticks; a
     * keeper now leaves only through its own `retention_until` (the scheduler's expiry), which is at most `backup_days`.
     *
     * @return Collection<int, Backup>
     */
    public static function beyondGenerations(Service $service, int $generations, int $limit): Collection
    {
        $newest = self::scheduled($service)->orderByDesc('started_at')->orderByDesc('id')->take(max(1, $generations))->pluck('id')->all();

        return self::scheduled($service)->whereNotIn('id', $newest)
            ->where(fn ($q) => $q->whereNull('meta->'.self::STAMP)->orWhereNull('retention_until'))
            ->orderByDesc('started_at')->orderByDesc('id')->take($limit)->get();
    }

    /**
     * The newest completed backup of each calendar day (application time zone) inside the last `$days` days.
     *
     * With `$endedDaysOnly`, today is left out: its newest backup is not final until the day is over.
     *
     * @return array<string, true> ids as keys
     */
    public static function keeperIds(Service $service, int $days, bool $endedDaysOnly = false): array
    {
        $rows = self::scheduled($service)->where('started_at', '>=', now()->subDays(max(1, $days)))
            ->when($endedDaysOnly, fn ($q) => $q->where('started_at', '<', now()->startOfDay()))
            ->orderByDesc('started_at')->orderByDesc('id')->get(['id', 'started_at']);
        $keepers = [];
        $seen = [];
        foreach ($rows as $row) {
            $day = $row->started_at?->format('Y-m-d');
            if ($day === null || isset($seen[$day])) {
                continue;
            }
            $seen[$day] = true;
            $keepers[(string) $row->id] = true;
        }

        return $keepers;
    }

    /**
     * How far back a schedule reaches, in minutes: `generations` copies of `minutes` each, never beyond `days` — and under
     * the rule, for a plan backed up at least daily, the full `days` through the daily keepers.
     */
    public static function historyMinutes(int $minutes, int $days, int $generations, bool $keepers): int
    {
        $window = max(1, $days) * 1440;
        $copies = max(1, $generations) * max(1, $minutes);

        return $keepers && $minutes <= 1440 ? $window : min($window, $copies);
    }

    /** How many copies a schedule keeps at most (the generations, plus under the rule a keeper for every day they do not reach). */
    public static function copies(int $minutes, int $days, int $generations, bool $keepers): int
    {
        $byWindow = intdiv(max(1, $days) * 1440, max(1, $minutes));
        if (! $keepers || $minutes > 1440) {
            return min(max(1, $generations), max(1, $byWindow));
        }
        $reachedDays = (int) ceil(max(1, $generations) * $minutes / 1440);

        return min(max(1, $generations), max(1, $byWindow)) + max(0, max(1, $days) - $reachedDays);
    }

    /** @return Builder<Backup> */
    private static function scheduled(Service $service)
    {
        return Backup::query()->where('service_id', $service->id)->where('state', 'completed')->where('protected', false)->where('kind', 'scheduled');
    }
}
