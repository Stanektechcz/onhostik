<?php

declare(strict_types=1);

namespace Onhost\Domain\Advisories;

use Onhost\Domain\Advisories\Models\InventoryItem;

/**
 * What can be done about one affected item (SN-5), decided from structured data only — never from an advisory's text:
 *
 *  - a WordPress plugin with a fixed release in the same major line → **A**: `wp.plugin update` on the site;
 *  - WordPress core with a fix in the same `x.y` branch (a security release) → **A**: `wp.update core`, through staging when the site has one;
 *  - a plugin whose fix is a new major line, a theme (themes update together) or core across branches → **B**: may change how the site
 *    behaves, run only when staff include it, the customer is told first;
 *  - no fixed release, or a version range that could not be compared → **D**: the customer is told what, where and why;
 *  - the platform's own software (panels, the control plane) → **P**: operations, never the customer.
 */
final class RemediationPlanner
{
    /** @return array{class:string, action:?array{action:string, params:array<string,mixed>}} */
    public static function plan(InventoryItem $item, ?string $fixed, string $confidence): array
    {
        if ($item->subject_type !== InventoryItem::SERVICE) {
            return ['class' => 'P', 'action' => null];
        }
        if ($confidence !== 'exact' || $fixed === null || AdvisoryVersion::normalize($fixed) === '') {
            return ['class' => 'D', 'action' => null];
        }

        return match (true) {
            // the service action takes WordPress.org slugs only; another name is fixed by the customer
            $item->ecosystem === 'wordpress-plugin' && preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/', $item->product) !== 1 => ['class' => 'D', 'action' => null],
            default => self::byEcosystem($item, $fixed),
        };
    }

    /** @return array{class:string, action:?array{action:string, params:array<string,mixed>}} */
    private static function byEcosystem(InventoryItem $item, string $fixed): array
    {
        return match ($item->ecosystem) {
            'wordpress-plugin' => [
                'class' => AdvisoryVersion::line($fixed, 1) === AdvisoryVersion::line($item->version, 1) ? 'A' : 'B',
                'action' => ['action' => 'wp.plugin', 'params' => ['slug' => $item->product, 'op' => 'update']],
            ],
            'wordpress-core' => [
                'class' => AdvisoryVersion::line($fixed, 2) === AdvisoryVersion::line($item->version, 2) ? 'A' : 'B',
                'action' => ['action' => 'wp.update', 'params' => ['what' => 'core', 'staged' => true]],
            ],
            'wordpress-theme' => ['class' => 'B', 'action' => ['action' => 'wp.update', 'params' => ['what' => 'themes', 'staged' => true]]],
            default => ['class' => 'D', 'action' => null],
        };
    }
}
