<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Illuminate\Support\Carbon;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Settings\SettingsStore;

/**
 * Commercial rules staff set in *Nastavení systému → Slevy a doplňky* — nothing here is hard-coded in the web:
 *  - commitment discounts (12 / 24 months) per product family — none unless staff approves one,
 *  - domain discounts per TLD (registration / renewal / transfer, optional validity window) — none by default,
 *  - which add-on products can be attached to a product's cart line (falls back to the product's `meta.addon_products`).
 * The quote, the public cart and the checkout all read the same rules, so the customer never sees a discount the order
 * would not honour.
 */
final class PricingRules
{
    public const COMMIT_MONTHS = [1, 12, 24];

    public const KEY_COMMIT = 'pricing.commit_discounts';

    public const KEY_DOMAIN = 'pricing.domain_discounts';

    public const KEY_ADDONS = 'pricing.addon_products';

    /** @var array<string, Product> */
    private array $products = [];

    public function __construct(private readonly SettingsStore $settings) {}

    // ── commitment discounts ───────────────────────────────────────────────────────────────────────────────────

    /** @return array{default: array<string,float>, families: array<string, array<string,float>>} */
    public function commitDiscounts(): array
    {
        $cfg = (array) $this->settings->get(self::KEY_COMMIT, []);
        $families = [];
        foreach ((array) ($cfg['families'] ?? []) as $family => $row) {
            $families[(string) $family] = self::percents((array) $row);
        }

        return ['default' => self::percents((array) ($cfg['default'] ?? [])), 'families' => $families];
    }

    /** Percent off the first-period price for a commitment; 0 unless staff configured one for the family (or a default). */
    public function commitDiscountPercent(?string $family, int $months): float
    {
        if ($months <= 1) {
            return 0.0;
        }
        $cfg = $this->commitDiscounts();
        $key = (string) $months;
        if ($family !== null && isset($cfg['families'][$family][$key])) {
            return (float) $cfg['families'][$family][$key];
        }

        return (float) ($cfg['default'][$key] ?? 0);
    }

    /** What the cart shows next to each commitment: `[[1, 0], [12, pct], [24, pct]]`. @return list<array{0:int,1:float}> */
    public function commitTable(?string $family = null): array
    {
        return array_map(fn (int $m) => [$m, $this->commitDiscountPercent($family, $m)], self::COMMIT_MONTHS);
    }

    /** @param array{default?:array<string|int,mixed>, families?:array<string,array<string|int,mixed>>} $cfg */
    public function setCommitDiscounts(array $cfg, ?string $updatedBy = null): array
    {
        $normalized = $this->normalizeCommitDiscounts($cfg);
        $this->settings->set(self::KEY_COMMIT, $normalized, $updatedBy);

        return $normalized;
    }

    /**
     * What `setCommitDiscounts` would store, or the refusal it would give — without storing anything (the pre-flight check
     * before a second person is asked, domains/Catalog/CatalogPreflight.php).
     *
     * @param  array{default?:array<string|int,mixed>, families?:array<string,array<string|int,mixed>>}  $cfg
     * @return array{default: array<string,float>, families: array<string, array<string,float>>}
     */
    public function normalizeCommitDiscounts(array $cfg): array
    {
        $normalized = ['default' => self::percents((array) ($cfg['default'] ?? []), true), 'families' => []];
        foreach ((array) ($cfg['families'] ?? []) as $family => $row) {
            $family = strtolower(trim((string) $family));
            if (! preg_match('/^[a-z_]{2,24}$/', $family)) {
                throw new DomainError('family_invalid', "Unknown product family {$family}.", 422, ['field' => 'families']);
            }
            $percents = self::percents((array) $row, true);
            if ($percents !== []) {
                $normalized['families'][$family] = $percents;
            }
        }

        return $normalized;
    }

    // ── domain discounts ───────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, array{register:float, renew:float, transfer:float, valid_from:?string, valid_to:?string, label:?string}> */
    public function domainDiscounts(): array
    {
        $out = [];
        foreach ((array) $this->settings->get(self::KEY_DOMAIN, []) as $tld => $row) {
            $out[(string) $tld] = self::domainRow((array) $row);
        }
        ksort($out);

        return $out;
    }

    /** @return array{percent: float, label: ?string} discount for `register|renew|transfer` of a TLD, 0 unless staff set one that is valid now */
    public function domainDiscount(string $tld, string $action = 'register'): array
    {
        $row = $this->domainDiscounts()[strtolower(ltrim($tld, '.'))] ?? null;
        if ($row === null) {
            return ['percent' => 0.0, 'label' => null];
        }
        $now = Carbon::now();
        if (($row['valid_from'] !== null && Carbon::parse($row['valid_from'])->isAfter($now)) || ($row['valid_to'] !== null && Carbon::parse($row['valid_to'])->isBefore($now))) {
            return ['percent' => 0.0, 'label' => null];
        }
        $percent = (float) ($row[in_array($action, ['register', 'renew', 'transfer'], true) ? $action : 'register'] ?? 0);

        return ['percent' => $percent, 'label' => $percent > 0 ? $row['label'] : null];
    }

    /** @param array<string,mixed> $row */
    public function setDomainDiscount(string $tld, array $row, ?string $updatedBy = null): array
    {
        [$tld, $normalized] = $this->normalizeDomainDiscount($tld, $row);
        $all = (array) $this->settings->get(self::KEY_DOMAIN, []);
        if ($normalized['register'] <= 0 && $normalized['renew'] <= 0 && $normalized['transfer'] <= 0) {
            unset($all[$tld]);
        } else {
            $all[$tld] = $normalized;
        }
        $this->settings->set(self::KEY_DOMAIN, $all, $updatedBy);

        return $normalized;
    }

    /**
     * The TLD and the row `setDomainDiscount` would store, or its refusal — without storing anything.
     *
     * @param  array<string,mixed>  $row
     * @return array{0: string, 1: array{register:float, renew:float, transfer:float, valid_from:?string, valid_to:?string, label:?string}}
     */
    public function normalizeDomainDiscount(string $tld, array $row): array
    {
        $tld = strtolower(ltrim(trim($tld), '.'));
        if (! preg_match('/^[a-z0-9.-]{2,32}$/', $tld)) {
            throw new DomainError('tld_invalid', 'TLD must be like cz or co.uk.', 422, ['field' => 'tld']);
        }

        return [$tld, self::domainRow($row)];
    }

    public function deleteDomainDiscount(string $tld, ?string $updatedBy = null): void
    {
        $all = (array) $this->settings->get(self::KEY_DOMAIN, []);
        unset($all[strtolower(ltrim(trim($tld), '.'))]);
        $this->settings->set(self::KEY_DOMAIN, $all, $updatedBy);
    }

    // ── add-on products per product ────────────────────────────────────────────────────────────────────────────

    /** Product keys that may be attached to a cart line of this product (staff mapping, else the seeded `meta.addon_products`). @return list<string> */
    public function addonProducts(Product|string $product): array
    {
        $product = $product instanceof Product ? $product : $this->product($product);
        if ($product === null) {
            return [];
        }
        $map = (array) $this->settings->get(self::KEY_ADDONS, []);
        $keys = array_key_exists($product->key, $map) ? (array) $map[$product->key] : (array) (($product->meta ?? [])['addon_products'] ?? []);
        $keys = array_values(array_unique(array_filter(array_map(fn ($k) => is_string($k) ? trim($k) : '', $keys), fn ($k) => $k !== '' && $k !== $product->key)));
        if ($keys === []) {
            return [];
        }
        // a product that is not on sale is not offered as an add-on either: the mapping outlives a product being taken off sale
        $onSale = Product::query()->whereIn('key', $keys)->where('state', 'active')->pluck('key')->all();

        return array_values(array_filter($keys, fn (string $k) => in_array($k, $onSale, true)));
    }

    /** @param list<string> $keys @return list<string> */
    public function setAddonProducts(string $productKey, array $keys, ?string $updatedBy = null): array
    {
        $clean = $this->normalizeAddonProducts($productKey, $keys);
        $map = (array) $this->settings->get(self::KEY_ADDONS, []);
        $map[$productKey] = $clean;
        $this->settings->set(self::KEY_ADDONS, $map, $updatedBy);

        return $map[$productKey];
    }

    /** @param list<string> $keys @return list<string> the mapping `setAddonProducts` would store, or its refusal — without storing anything */
    public function normalizeAddonProducts(string $productKey, array $keys): array
    {
        $product = $this->product($productKey);
        if ($product === null) {
            throw DomainError::notFound("Product {$productKey}");
        }
        $clean = [];
        foreach ($keys as $key) {
            $key = strtolower(trim((string) $key));
            if ($key === '' || $key === $productKey) {
                continue;
            }
            if ($this->product($key) === null) {
                throw new DomainError('addon_product_unknown', "Add-on product {$key} does not exist.", 422, ['field' => 'addon_products']);
            }
            $clean[] = $key;
        }

        return array_values(array_unique($clean));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────────────────────────

    // ── regional pricing (audit §5j-8) ────────────────────────────────────────

    public const KEY_REGIONS = 'pricing.regions';

    /** @return array<string, array{key:string,label:string,countries:list<string>,currency:string,adjust_pct:float}> country groups with a suggested currency and a percentage on the list price */
    public function regions(): array
    {
        $cfg = $this->settings->get(self::KEY_REGIONS);
        $rows = is_array($cfg) ? $cfg : (array) config('onhost.pricing.regions', []);
        $out = [];
        foreach ($rows as $key => $row) {
            $key = strtolower((string) ($row['key'] ?? $key));
            if (! preg_match('/^[a-z0-9-]{2,20}$/', $key)) {
                continue;
            }
            $out[$key] = [
                'key' => $key, 'label' => (string) ($row['label'] ?? strtoupper($key)), 'countries' => array_values(array_map(fn ($c) => strtoupper((string) $c), (array) ($row['countries'] ?? []))),
                'currency' => in_array(strtoupper((string) ($row['currency'] ?? 'CZK')), ['CZK', 'EUR'], true) ? strtoupper((string) ($row['currency'] ?? 'CZK')) : 'CZK', 'adjust_pct' => round(max(-50.0, min(100.0, (float) ($row['adjust_pct'] ?? 0))), 2),
            ];
        }

        return $out;
    }

    /** @return array{key:string,label:string,countries:list<string>,currency:string,adjust_pct:float} the group of a country; the home group (no adjustment, CZK) when none matches */
    public function regionFor(?string $country): array
    {
        $country = strtoupper((string) $country);
        foreach ($this->regions() as $region) {
            if (in_array($country, $region['countries'], true)) {
                return $region;
            }
        }

        return ['key' => 'home', 'label' => 'Česko', 'countries' => ['CZ'], 'currency' => 'CZK', 'adjust_pct' => 0.0];
    }

    /** @param  list<array<string,mixed>>  $regions  Replaces the table; an empty list returns to the configured defaults. */
    public function setRegions(array $regions, ?string $updatedBy = null): array
    {
        $clean = $this->normalizeRegions($regions);
        if ($clean === []) {
            $this->settings->forget(self::KEY_REGIONS);
        } else {
            $this->settings->set(self::KEY_REGIONS, array_values($clean), $updatedBy);
        }

        return $this->regions();
    }

    /**
     * The table `setRegions` would store (empty = back to the defaults), or its refusal — without storing anything.
     *
     * @param  list<array<string,mixed>>  $regions
     * @return array<string, array{key:string,label:string,countries:list<string>,currency:string,adjust_pct:float}>
     */
    public function normalizeRegions(array $regions): array
    {
        $clean = [];
        foreach ($regions as $row) {
            $key = strtolower(trim((string) ($row['key'] ?? '')));
            if (! preg_match('/^[a-z0-9-]{2,20}$/', $key)) {
                throw new DomainError('region_key_invalid', 'A region key is 2–20 lowercase letters, digits and dashes.', 422, ['field' => 'key']);
            }
            $countries = array_values(array_unique(array_map(fn ($c) => strtoupper(trim((string) $c)), (array) ($row['countries'] ?? []))));
            foreach ($countries as $c) {
                if (! preg_match('/^[A-Z]{2}$/', $c)) {
                    throw new DomainError('region_country_invalid', "{$c} is not an ISO 3166-1 alpha-2 country code.", 422, ['field' => 'countries']);
                }
            }
            $pct = (float) ($row['adjust_pct'] ?? 0);
            if ($pct < -50 || $pct > 100) {
                throw new DomainError('region_adjust_invalid', 'The adjustment is between −50 and +100 %.', 422, ['field' => 'adjust_pct']);
            }
            $currency = strtoupper((string) ($row['currency'] ?? 'CZK'));
            if (! in_array($currency, ['CZK', 'EUR'], true)) {
                throw new DomainError('region_currency_invalid', 'Currency must be CZK or EUR.', 422, ['field' => 'currency']);
            }
            $clean[$key] = ['key' => $key, 'label' => mb_substr(trim((string) ($row['label'] ?? strtoupper($key))), 0, 40), 'countries' => $countries, 'currency' => $currency, 'adjust_pct' => round($pct, 2)];
        }

        return $clean;
    }

    private function product(string $key): ?Product
    {
        if (! array_key_exists($key, $this->products)) {
            $this->products[$key] = Product::query()->where('key', $key)->first();
        }

        return $this->products[$key];
    }

    /** @param array<string|int,mixed> $in @return array<string,float> months => percent (12 / 24 only, 0–90) */
    private static function percents(array $in, bool $strict = false): array
    {
        $out = [];
        foreach ($in as $months => $pct) {
            $months = (int) $months;
            if (! in_array($months, [12, 24], true)) {
                if ($strict) {
                    throw new DomainError('commitment_invalid', 'Commitment discounts exist for 12 and 24 months only.', 422, ['field' => 'months']);
                }

                continue;
            }
            $pct = (float) $pct;
            if ($strict && ($pct < 0 || $pct > 90)) {
                throw new DomainError('percent_invalid', 'A discount is between 0 and 90 %.', 422, ['field' => (string) $months]);
            }
            $pct = max(0.0, min(90.0, $pct));
            if ($pct > 0) {
                $out[(string) $months] = round($pct, 2);
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $row @return array{register:float, renew:float, transfer:float, valid_from:?string, valid_to:?string, label:?string} */
    private static function domainRow(array $row): array
    {
        $pct = function (mixed $v) {
            $v = (float) ($v ?? 0);
            if ($v < 0 || $v > 100) {
                throw new DomainError('percent_invalid', 'A domain discount is between 0 and 100 %.', 422, ['field' => 'register']);
            }

            return round($v, 2);
        };
        $date = fn (mixed $v) => is_string($v) && trim($v) !== '' ? Carbon::parse($v)->toIso8601String() : null;

        return [
            'register' => $pct($row['register'] ?? 0), 'renew' => $pct($row['renew'] ?? 0), 'transfer' => $pct($row['transfer'] ?? 0),
            'valid_from' => $date($row['valid_from'] ?? null), 'valid_to' => $date($row['valid_to'] ?? null),
            'label' => isset($row['label']) && trim((string) $row['label']) !== '' ? mb_substr(trim((string) $row['label']), 0, 120) : null,
        ];
    }
}
