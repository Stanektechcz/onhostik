<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * Corrects the prototype sentences that promise credit back in cash (G8 item 9, owner decision 2026-10-05): credit is never paid
 * back in cash. The prototypes (`apps/surfaces/*.dc.html`, `onhost-content.js`, `onhost-svc-*.js`) must stay byte-identical, so the
 * served copy is corrected here, on its way out, like every other seam. The sentences and their corrections are the data file
 * `resources/surfaces/credit-claims.php`; a test pins that every needle still exists in the prototype (a rewritten sentence cannot
 * silently escape the seam) and that nothing served still says it.
 */
final class CreditClaimsSeam
{
    /** @return array<string, array<string, string>> group => [prototype text => the true text] */
    public static function claims(): array
    {
        static $claims = null;

        return $claims ??= require resource_path('surfaces/credit-claims.php');
    }

    /** The corrected copy of a prototype script or page; `$groups` names which claim groups apply to it. @param list<string> $groups */
    public static function apply(string $source, array $groups): string
    {
        foreach ($groups as $group) {
            $source = strtr($source, self::claims()[$group] ?? []);
        }

        return $source;
    }
}
