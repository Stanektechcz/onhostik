<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Services\ServiceFeatures;

/**
 * Whether the server a web plan runs on can deliver what the plan sells (owner decision R4, audit 2026-10, finding P1-15).
 *
 * A web or managed plan is provisioned on its product's executor, and what a site there can have is what that executor
 * declares (`siteFeatures()`, statically `ServiceFeatures::declaredSite()`). The e-shop plans run on aaPanel, which creates no
 * mailboxes (`mail: false`), and sold 20 to 500 of them; every web product offered a dedicated IPv4 that neither web panel
 * assigns to a site. A key listed here is sold only where the executor declares the feature that delivers it.
 *
 * Only keys whose delivery depends on the executor are listed; every other key is a question for `PlanPromises` (measured,
 * enforced or fair use), not for this table.
 */
final class ExecutorDelivery
{
    /** The families whose plans are sites on a web executor. */
    public const WEB_FAMILIES = ['web', 'managed'];

    /**
     * What a web plan (or a priced option of its product) sells => the site feature of the executor that delivers it.
     * `dedicated_ipv4` is declared by no web executor today: no web panel assigns an address of its own to a site.
     *
     * @var array<string, string>
     */
    public const WEB_FEATURES = ['mailboxes' => 'mail', 'ipv4' => 'dedicated_ipv4'];

    /**
     * The keys of `$sold` the executor cannot deliver: listed in `WEB_FEATURES`, sold (a positive number or `true`), and the
     * feature behind them not declared by the executor. An executor the platform has no declaration for is not judged here.
     *
     * @param  array<string, mixed>  $sold
     * @return list<string>
     */
    public static function undelivered(?string $family, ?string $executor, array $sold): array
    {
        if (! in_array($family, self::WEB_FAMILIES, true) || $executor === null || $executor === '') {
            return [];
        }
        $site = ServiceFeatures::declaredSite($executor);
        if ($site === []) {
            return [];
        }
        $out = [];
        foreach (self::WEB_FEATURES as $key => $feature) {
            if (array_key_exists($key, $sold) && self::isSold($sold[$key]) && empty($site[$feature])) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** A number above zero or a switch that is on; `0`, `false`, `null` and an empty value sell nothing. */
    private static function isSold(mixed $value): bool
    {
        return is_numeric($value) ? (float) $value > 0 : ! in_array($value, [null, false, '', []], true);
    }
}
