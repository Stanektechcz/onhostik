<?php

declare(strict_types=1);

namespace Onhost\Platform\Clock;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class SystemClock implements Clock
{
    public const OFFSET_CACHE_KEY = 'onhost:clock:offset_seconds';

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public function offsetSeconds(): ?float
    {
        $value = Cache::get(self::OFFSET_CACHE_KEY);

        return is_numeric($value) ? (float) $value : null;
    }

    /** Recorded by the clock health check (chrony/NTP or HTTP Date header comparison). */
    public static function recordOffset(float $seconds): void
    {
        Cache::put(self::OFFSET_CACHE_KEY, $seconds, now()->addMinutes(30));
    }
}
