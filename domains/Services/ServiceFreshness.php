<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Carbon;
use Onhost\Domain\Services\Models\Service;
use Throwable;

/**
 * How old is what we say about a service (Brain card H325). The status, load and drift shown to a customer are the
 * last thing the reconciler read from the panel — when the reconciler or the panel's API is down, that value keeps
 * sitting there and looks as current as ever. Every reading therefore carries its time and a verdict: fresh, stale,
 * or never taken. An unknown age is never presented as "now".
 */
final class ServiceFreshness
{
    /**
     * @return array{measured_at:?string, age_seconds:?int, stale:bool, stale_after_seconds:int, verdict:'fresh'|'stale'|'pending'|'never'}
     */
    public static function of(Service $service): array
    {
        $interval = (int) config($service->sla_class !== 'standard' ? 'onhost.provisioning.reconcile.critical_minutes' : 'onhost.provisioning.reconcile.normal_minutes', 15);
        $staleAfter = max(600, $interval * 60 * 3); // three missed passes: one slow pass is not an outage of the measurement
        $measured = self::measuredAt($service);
        if ($measured === null) {
            // nothing was ever read: a service that has just been activated is waiting for its first pass, an older one is simply unmeasured
            $young = $service->activated_at !== null && $service->activated_at->diffInSeconds(now(), true) < $staleAfter;

            return ['measured_at' => null, 'age_seconds' => null, 'stale' => ! $young, 'stale_after_seconds' => $staleAfter, 'verdict' => $young ? 'pending' : 'never'];
        }
        $age = (int) $measured->diffInSeconds(now(), true);
        $stale = $age > $staleAfter;

        return ['measured_at' => $measured->toIso8601String(), 'age_seconds' => $age, 'stale' => $stale, 'stale_after_seconds' => $staleAfter, 'verdict' => $stale ? 'stale' : 'fresh'];
    }

    private static function measuredAt(Service $service): ?Carbon
    {
        $candidates = [$service->last_reconciled_at];
        $checked = data_get($service->health, 'checked_at');
        if (is_string($checked) && $checked !== '') {
            try {
                $candidates[] = Carbon::parse($checked);
            } catch (Throwable) {
                // an unreadable timestamp is no timestamp
            }
        }
        $candidates = array_values(array_filter($candidates, fn ($c) => $c instanceof Carbon));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn (Carbon $a, Carbon $b) => $b->getTimestamp() <=> $a->getTimestamp());

        return $candidates[0];
    }
}
