<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Feature flag evaluation (audit 500 #110/#383).
 *
 * A flag is on for a customer when it is enabled AND either the customer is
 * explicitly allow-listed or falls inside the rollout percentage. The bucket is
 * derived from a stable hash of (key, customer id), so a customer never flips
 * back and forth between requests and widening the rollout only ever adds
 * customers — it never removes one who already had it.
 *
 * Flags are cached briefly; an unknown key is simply "off", so calling code can
 * ship guarded before the flag row exists.
 */
class Features
{
    private const CACHE_TTL = 60; // seconds

    public function enabled(string $key, ?Customer $customer = null): bool
    {
        $flag = $this->flag($key);

        if ($flag === null || ! $flag->is_enabled) {
            return false;
        }

        if ($customer === null) {
            return $flag->rollout_percent >= 100;
        }

        if (in_array($customer->id, $flag->customer_ids ?? [], true)) {
            return true;
        }

        if ($flag->rollout_percent <= 0) {
            return false;
        }

        if ($flag->rollout_percent >= 100) {
            return true;
        }

        return $this->bucket($key, $customer->id) < $flag->rollout_percent;
    }

    /** Stable 0–99 bucket for (flag, customer). */
    public function bucket(string $key, int $customerId): int
    {
        return (int) (hexdec(substr(md5($key . ':' . $customerId), 0, 8)) % 100);
    }

    public function forget(string $key): void
    {
        Cache::forget('feature-flag:' . $key);
    }

    private function flag(string $key): ?FeatureFlag
    {
        try {
            return Cache::remember(
                'feature-flag:' . $key,
                self::CACHE_TTL,
                fn (): ?FeatureFlag => FeatureFlag::query()->where('key', $key)->first(),
            );
        } catch (Throwable) {
            // Table not migrated yet / DB unavailable — a flag must never take
            // the app down, so treat it as "off".
            return null;
        }
    }
}
