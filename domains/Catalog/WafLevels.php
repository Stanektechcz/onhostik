<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;

/**
 * What the `waf` line of the price list promises, in the rules the platform can actually put on a site.
 *
 * Every web hosting plan states a WAF level — „WAF základní“, „WAF standard“, „WAF + CDN“ — and the level was a
 * **label and nothing else**: it was rendered on the price list and handed to the panel as a string, and no code ever
 * compared it with what the site's own server can do. The two panels do not do the same things: aaPanel applies
 * per-site connection and request limits, ISPConfig cannot (nginx wants its `limit_req_zone` in the http block, which
 * belongs to the node and not to a customer's site), so a plan promising a rate limit delivered nothing of the sort on
 * a shared ISPConfig node and nobody was told.
 *
 * This is the one place that says what a level means. `ServiceFeatures` reports what the service's own server delivers
 * of it and what it does not, the doctor names a plan on sale whose executor cannot keep its promise, and a guard test
 * refuses a level nobody defined — a new word in the plan editor must not quietly become „basic“.
 */
final class WafLevels
{
    /** Every rule a level can promise; the panels answer with the ones they support (`securityRules()['supports']`). */
    public const RULES = ['headers', 'bots', 'hotlink', 'deny', 'allow', 'hsts', 'rate'];

    /**
     * The levels of the price list, weakest first. The match is by keyword, because the plans write the level next to
     * other words („pro + CDN“, „advanced+cdn“, „custom rules“).
     *
     * @var array<string, list<string>>
     */
    public const LEVELS = [
        'basic' => ['headers', 'bots'],
        'standard' => ['headers', 'bots', 'hotlink', 'deny', 'allow'],
        'custom' => ['headers', 'bots', 'hotlink', 'deny', 'allow'], // the WAF add-on's own words: rules written by hand, no rate limit promised
        'advanced' => ['headers', 'bots', 'hotlink', 'deny', 'allow', 'hsts', 'rate'],
        'pro' => ['headers', 'bots', 'hotlink', 'deny', 'allow', 'hsts', 'rate'],
    ];

    /** The level a plan's `waf` value names, or null when nobody defined that word. */
    public static function levelOf(?string $waf): ?string
    {
        $value = mb_strtolower(trim((string) $waf));
        if ($value === '') {
            return null;
        }
        foreach (['pro', 'advanced', 'custom', 'standard', 'basic'] as $level) { // strongest first: „pro + CDN“ is pro
            if (str_contains($value, $level)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * What this level promises.
     *
     * @return list<string>
     */
    public static function promised(?string $waf): array
    {
        $level = self::levelOf($waf);

        return $level === null ? [] : self::LEVELS[$level];
    }

    /**
     * What the level promises and the site's own server cannot do.
     *
     * @param  list<string>  $supports  what the panel answered it supports
     * @return list<string>
     */
    public static function missing(?string $waf, array $supports): array
    {
        return array_values(array_diff(self::promised($waf), $supports));
    }

    /**
     * Plans on sale whose WAF level names a rule their executor cannot apply, and plans whose level nobody defined —
     * for the doctor and the guard test.
     *
     * @param  array<string, list<string>>  $supportsByExecutor  executor key => the rules it applies
     * @return array<string, list<string>> plan key => what is promised and not delivered (`?level` = an unknown word)
     */
    public static function onSaleProblems(array $supportsByExecutor): array
    {
        $out = [];
        $products = Product::query()->where('state', 'active')->get(['id', 'key', 'family', 'meta']);
        foreach (Plan::query()->whereIn('product_id', $products->pluck('id'))->where('state', 'active')->get() as $plan) {
            $product = $products->firstWhere('id', $plan->product_id);
            $version = $plan->currentVersion();
            $waf = $version === null ? null : (string) data_get($version->entitlements, 'waf', '');
            if ($product === null || $waf === null || trim($waf) === '') {
                continue; // a plan that promises no WAF cannot break the promise
            }
            $executor = (string) (data_get($product->meta, 'executor') ?: ($product->family === 'managed' ? 'aapanel' : 'ispconfig'));
            $supports = $supportsByExecutor[$executor] ?? null;
            if ($supports === null) {
                continue; // a family with no web panel behind it (domains, certificates, …)
            }
            $problems = self::levelOf($waf) === null ? ['?'.$waf] : self::missing($waf, $supports);
            if ($problems !== []) {
                $out[$product->key.'/'.$plan->key] = $problems;
            }
        }

        return $out;
    }
}
