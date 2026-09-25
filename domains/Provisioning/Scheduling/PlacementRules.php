<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Scheduling;

use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\PlacementService;

/**
 * Where a plan may run because of what it sells (owner decision 7, TASK-0023). A plan that sells dedicated PHP
 * workers (`php_workers_dedicated`) needs a PHP pool of its own: ISPConfig writes one FPM pool per site with
 * pm_max_children = the plan's workers and drift-checks it; aaPanel runs one pool per PHP version for the whole node
 * and answers applied=false to every worker change. Staff could pin web-hosting/profi to aaPanel, and a product-wide
 * aaPanel placement took profi with it.
 *
 * The rule binds a dedicated plan only when its product runs on a panel that gives a site its own pool
 * (`DEDICATED_PHP_PROVIDERS`). eshop/shop-peak is a managed product on aaPanel and is deliberately NOT moved:
 * ISPConfig applies no WAF rate limit (its "pro" level promises one) and the client-wide site count ISPConfig enforces
 * does not count a plan that sells no `sites` number. Such a plan is reported by `undelivered()` (doctor,
 * onhost:capacity:basis) instead of being moved; nothing already running is touched by any of this.
 */
final class PlacementRules
{
    /** Panels that give every site a PHP pool of its own. */
    public const DEDICATED_PHP_PROVIDERS = ['ispconfig'];

    /** @param array<string,mixed> $entitlements */
    public static function dedicatedPhp(array $entitlements): bool
    {
        return ! empty($entitlements['php_workers_dedicated']);
    }

    /**
     * What the plan requires of the panel it runs on; empty when nothing (the only marker today: `php => dedicated`).
     *
     * @param  array<string,mixed>  $entitlements
     * @return array<string,string>
     */
    public static function requires(string $productExecutor, array $entitlements): array
    {
        return self::dedicatedPhp($entitlements) && in_array($productExecutor, self::DEDICATED_PHP_PROVIDERS, true) ? ['php' => 'dedicated'] : [];
    }

    /**
     * The panels a spec with these requirements may run on, or null when it may run wherever its product may.
     *
     * @param  array<string,mixed>  $requires
     * @return list<string>|null
     */
    public static function providersFor(array $requires): ?array
    {
        return ($requires['php'] ?? null) === 'dedicated' ? self::DEDICATED_PHP_PROVIDERS : null;
    }

    /** @param array<string,mixed> $entitlements */
    public static function allows(string $productExecutor, string $provider, array $entitlements): bool
    {
        $allowed = self::providersFor(self::requires($productExecutor, $entitlements))
            ?? PlacementService::COMPATIBLE[$productExecutor] ?? [$productExecutor];

        return in_array($provider, $allowed, true);
    }

    /**
     * The executor of a new service: the placement's panel when the plan may run there, else the product's own.
     *
     * @param  array<string,mixed>  $entitlements
     */
    public static function executorFor(string $productExecutor, array $entitlements, ?PlanPlacement $placement): string
    {
        $instance = $placement?->providerInstance;
        $provider = $instance instanceof ProviderInstance ? (string) $instance->provider : '';
        if ($provider !== '' && self::allows($productExecutor, $provider, $entitlements)) {
            return $provider;
        }
        if (self::allows($productExecutor, $productExecutor, $entitlements)) {
            return $productExecutor;
        }

        return self::providersFor(self::requires($productExecutor, $entitlements))[0] ?? $productExecutor;
    }

    /**
     * A plan that sells dedicated PHP workers on a product whose panel runs one pool for the whole node: the promise is
     * on the price list and nothing keeps it (eshop/shop-peak today). Reported, never moved.
     *
     * @param  array<string,mixed>  $entitlements
     */
    public static function undelivered(string $productExecutor, array $entitlements): bool
    {
        return self::dedicatedPhp($entitlements) && ! in_array($productExecutor, self::DEDICATED_PHP_PROVIDERS, true);
    }
}
