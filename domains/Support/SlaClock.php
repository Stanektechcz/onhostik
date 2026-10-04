<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Onhost\Domain\Support\Models\SlaPolicy;

/**
 * When a support target falls due (blueprint §68.5). A policy with `business_hours_only` counts only the minutes inside its
 * `business_hours` ({days: ISO 1..7, from: 'HH:MM', to: 'HH:MM', tz}); every other policy counts wall-clock minutes. The
 * hours were stored with every policy and never read: a P3 ticket opened on Friday at 17:00 was "late" on Friday at 21:00
 * for a team that works Monday to Friday (readiness audit 2026-10, P1-3).
 */
final class SlaClock
{
    /** A guard, not a limit anybody reaches: a validated window has at least one working minute a week (ten years of days). */
    private const MAX_DAYS = 3660;

    public static function due(CarbonInterface $from, int $minutes, ?SlaPolicy $policy): Carbon
    {
        $hours = $policy !== null && $policy->business_hours_only ? self::window((array) ($policy->business_hours ?? [])) : null;

        return $hours === null ? Carbon::instance($from)->copy()->addMinutes($minutes) : self::addBusinessMinutes($from, $minutes, $hours);
    }

    /**
     * @param  array{days:list<int>, from:string, to:string, tz:string}  $hours
     */
    public static function addBusinessMinutes(CarbonInterface $from, int $minutes, array $hours): Carbon
    {
        $appTz = $from->getTimezone();
        $cursor = CarbonImmutable::instance($from)->setTimezone($hours['tz'])->startOfMinute();
        $remaining = max(0, $minutes);
        for ($day = 0; $day < self::MAX_DAYS && $remaining > 0; $day++) {
            [$start, $end] = self::bounds($cursor, $hours);
            if (! in_array($cursor->dayOfWeekIso, $hours['days'], true) || $cursor >= $end) {
                $cursor = $cursor->addDay()->setTimeFromTimeString($hours['from']);

                continue;
            }
            if ($cursor < $start) {
                $cursor = $start;
            }
            $available = (int) $cursor->diffInMinutes($end);
            if ($available >= $remaining) {
                return Carbon::instance($cursor->addMinutes($remaining))->setTimezone($appTz);
            }
            $remaining -= $available;
            $cursor = $cursor->addDay()->setTimeFromTimeString($hours['from']);
        }

        return $remaining === 0 ? Carbon::instance($cursor)->setTimezone($appTz) : Carbon::instance($from)->copy()->addMinutes($minutes);
    }

    /**
     * A usable business-hours definition, or null (then the clock counts wall time).
     *
     * @return array{days:list<int>, from:string, to:string, tz:string}|null
     */
    public static function window(array $hours): ?array
    {
        $days = array_values(array_unique(array_map('intval', (array) ($hours['days'] ?? []))));
        $days = array_values(array_filter($days, fn (int $d) => $d >= 1 && $d <= 7));
        $from = (string) ($hours['from'] ?? '');
        $to = (string) ($hours['to'] ?? '');
        $tz = (string) ($hours['tz'] ?? config('app.timezone', 'UTC'));
        if ($days === [] || ! self::isTime($from) || ! self::isTime($to) || $from >= $to || ! in_array($tz, timezone_identifiers_list(), true)) {
            return null;
        }

        return ['days' => $days, 'from' => $from, 'to' => $to, 'tz' => $tz];
    }

    public static function isTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private static function bounds(CarbonImmutable $day, array $hours): array
    {
        return [$day->setTimeFromTimeString($hours['from']), $day->setTimeFromTimeString($hours['to'])];
    }
}
