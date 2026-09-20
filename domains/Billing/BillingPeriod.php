<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Where a paid period ends. `addMonth()` overflows: 31 January plus a month is 3 March — 31 days sold as a month, three of
 * them free, and the day the service renews drifts for good (29 February plus a year is 1 March). A period ends on the
 * same day of the month as its anchor where that month has such a day, and on its last day where it has not; the anchor
 * (the day the service was activated) brings a period that was cut short by February back to the 31st in March.
 */
final class BillingPeriod
{
    public static function end(CarbonInterface $start, string $period, int $count = 1, ?int $anchorDay = null): Carbon
    {
        $count = max(1, $count);
        $end = Carbon::instance($start->toDateTime())->setTimezone($start->getTimezone());
        $end = $period === 'year' ? $end->addYearsNoOverflow($count) : $end->addMonthsNoOverflow($count);
        if ($anchorDay !== null && $anchorDay > $end->day) {
            $end = $end->day(min($anchorDay, $end->daysInMonth));
        }

        return $end;
    }
}
