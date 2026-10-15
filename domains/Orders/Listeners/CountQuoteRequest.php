<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Listeners;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * W4-6 (docs/compliance/dpia-funnel-telemetry.md): `onhost.cart.quote.requested` → +1 in the daily aggregate `funnel_daily`
 * for every plan key. The aggregate has no identifier of a person, organization, cart or quote and is for the platform only.
 * A relay that dies after this write and delivers again counts twice — tolerated, the metric is approximate.
 */
final class CountQuoteRequest
{
    public const PERIODS = ['month', 'year'];

    public const LOCALES = ['cs', 'sk', 'en'];

    public function handle(OutboxMessage $message): void
    {
        if (! (bool) config('onhost.funnel_metrics')) {
            return;
        }
        $payload = (array) ($message->payload ?? []);
        $period = (string) ($payload['period'] ?? '');
        $locale = (string) ($payload['locale'] ?? '');
        if (! in_array($period, self::PERIODS, true) || ! in_array($locale, self::LOCALES, true)) {
            return;
        }
        $date = Carbon::parse($message->created_at ?? now())->toDateString();
        $keys = array_slice(array_values(array_unique(array_filter((array) ($payload['plan_keys'] ?? []), fn ($key) => is_string($key) && preg_match('/^[A-Za-z0-9._-]{1,60}$/', $key) === 1))), 0, 20);
        $keys = $keys === [] ? [] : Plan::query()->whereIn('key', $keys)->pluck('key')->map(fn ($key) => (string) $key)->unique()->values()->all(); // catalog keys only
        foreach ($keys as $key) {
            $row = ['date' => $date, 'plan_key' => $key, 'period' => $period, 'locale' => $locale];
            DB::table('funnel_daily')->insertOrIgnore($row + ['count' => 0]);
            DB::table('funnel_daily')->where($row)->increment('count');
        }
    }
}
