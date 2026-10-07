<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Settings\SettingsStore;

/**
 * What Penpot costs next to the service it is ordered for (owner decision H-R7, 2026-10-07).
 *
 *  - A web hosting tariff (a plan of a product of family `web` or `managed`) carries its own rule, set by staff per tariff:
 *    Penpot included (0 Kč) or a monthly price per currency. A tariff without a rule of its own follows `web_default`.
 *  - Any other service gets Penpot as an add-on at the catalogue price of `penpot/penpot-team` (29 Kč a month when the
 *    revision `2026-10-penpot-on-sale` published it) — the plan editor changes that price like any other.
 *
 * The rules live in the setting `pricing.penpot` and change only through `CatalogCommand pricing.penpot.set` (a price: a
 * step-up and a second person, audited). Nothing here is a discount: an included Penpot is part of the tariff staff priced,
 * and a year is twelve months of the monthly price (no term discount is implied).
 */
final class PenpotOffer
{
    public const KEY = 'pricing.penpot';

    public const PRODUCT = 'penpot';

    public const PLAN = 'penpot-team';

    /** The product families whose tariffs are web hosting (managed WordPress and e-shop hosting are web hosting too). */
    public const WEB_FAMILIES = ['web', 'managed'];

    public const CURRENCIES = ['CZK', 'EUR'];

    /** 100 000 Kč a month: a typo guard, not a business limit. */
    private const MAX_MINOR = 10_000_000;

    public function __construct(private readonly SettingsStore $settings) {}

    /** Whether staff (or the revision) ever wrote the rules; until then the defaults apply and the doctor says so. */
    public function configured(): bool
    {
        return $this->settings->get(self::KEY) !== null;
    }

    /**
     * The rules as stored, read leniently (a value staff wrote by hand never breaks a quote: it reads as "not included, no price").
     *
     * @return array{web_default: array{included: bool, price_minor: array<string,int>}, plans: array<string, array{included: bool, price_minor: array<string,int>}>}
     */
    public function rules(): array
    {
        $raw = $this->settings->get(self::KEY);
        if (! is_array($raw)) {
            return $this->defaults();
        }
        $plans = [];
        foreach ((array) ($raw['plans'] ?? []) as $target => $entry) {
            $plans[(string) $target] = self::read((array) $entry);
        }
        ksort($plans);

        return ['web_default' => self::read((array) ($raw['web_default'] ?? ['included' => true])), 'plans' => $plans];
    }

    /**
     * The owner's defaults (H-R7): every web hosting tariff on file includes Penpot, and so does a tariff added later.
     *
     * @return array{web_default: array{included: bool, price_minor: array<string,int>}, plans: array<string, array{included: bool, price_minor: array<string,int>}>}
     */
    public function defaults(): array
    {
        $plans = [];
        foreach (array_keys($this->webPlans()) as $target) {
            $plans[$target] = ['included' => true, 'price_minor' => []];
        }

        return ['web_default' => ['included' => true, 'price_minor' => []], 'plans' => $plans];
    }

    /**
     * The rules staff send, checked the way they are stored, or the refusal. A whole replacement: a tariff left out follows
     * `web_default`. A price of 0 in every currency means "included" (owner: "0 = v ceně").
     *
     * @param  array<string,mixed>  $in  {web_default?: entry, plans?: {product/plan: entry}}
     * @return array{web_default: array{included: bool, price_minor: array<string,int>}, plans: array<string, array{included: bool, price_minor: array<string,int>}>}
     */
    public function normalize(array $in): array
    {
        $web = $this->webPlans();
        $plans = [];
        foreach ((array) ($in['plans'] ?? []) as $target => $entry) {
            $target = strtolower(trim((string) $target));
            if (! isset($web[$target])) {
                throw new DomainError('penpot_offer_plan_invalid', "{$target} is not a web hosting tariff (product/plan of family ".implode(' or ', self::WEB_FAMILIES).'); other services take Penpot at the catalogue price of penpot/penpot-team.', 422, ['field' => "plans.{$target}"]);
            }
            if (! is_array($entry)) {
                throw new DomainError('penpot_offer_invalid', "The rule of {$target} must be {included, price_minor}.", 422, ['field' => "plans.{$target}"]);
            }
            $plans[$target] = self::strict($entry, "plans.{$target}");
        }
        ksort($plans);
        $default = array_key_exists('web_default', $in) && is_array($in['web_default']) ? self::strict($in['web_default'], 'web_default') : ['included' => true, 'price_minor' => []];

        return ['web_default' => $default, 'plans' => $plans];
    }

    /** @param array<string,mixed> $in @return array{web_default: array<string,mixed>, plans: array<string,mixed>} */
    public function set(array $in, ?string $updatedBy = null): array
    {
        $rules = $this->normalize($in);
        $this->settings->set(self::KEY, $rules, $updatedBy);

        return $rules;
    }

    /**
     * What one Penpot costs next to a service of this product and tariff, for one period.
     *
     * @param  Price|null  $catalogue  the catalogue price of penpot/penpot-team in this currency and period (the add-on price)
     * @return array{included: bool, first_minor: int, renewal_minor: int, source: string}
     */
    public function priceFor(Product $parent, ?string $planKey, Currency $currency, string $period, ?Price $catalogue): array
    {
        $months = $period === 'year' ? 12 : 1;
        if (in_array($parent->family, self::WEB_FAMILIES, true)) {
            $rules = $this->rules();
            $target = $parent->key.'/'.(string) $planKey;
            $entry = $rules['plans'][$target] ?? $rules['web_default'];
            $source = isset($rules['plans'][$target]) ? 'tariff' : 'web_default';
            if ($entry['included']) {
                return ['included' => true, 'first_minor' => 0, 'renewal_minor' => 0, 'source' => $source];
            }
            $monthly = $entry['price_minor'][$currency->value] ?? null;
            if ($monthly === null || $monthly <= 0) {
                throw new DomainError('penpot_unpriced', "Penpot nemá k tarifu {$target} cenu v {$currency->value}; objednejte v jiné měně nebo se obraťte na podporu.", 409, ['field' => 'currency', 'currency' => $currency->value]);
            }

            return ['included' => false, 'first_minor' => $monthly * $months, 'renewal_minor' => $monthly * $months, 'source' => $source];
        }
        if ($catalogue === null || $catalogue->amount_minor <= 0) { // never sold for nothing by accident (CatalogPreflight::assertPriced)
            throw new DomainError('penpot_unpriced', "Penpot nemá v ceníku cenu v {$currency->value} za období {$period}.", 409, ['field' => 'currency', 'currency' => $currency->value]);
        }

        return ['included' => false, 'first_minor' => $catalogue->firstPeriodAmount()->minor, 'renewal_minor' => $catalogue->renewalAmount()->minor, 'source' => 'addon'];
    }

    /**
     * Every web hosting tariff with the rule that applies to it, the add-on price and whether a node can run Penpot: what the
     * staff editor shows.
     *
     * @return array<string,mixed>
     */
    public function overview(): array
    {
        $rules = $this->rules();
        $tariffs = [];
        foreach ($this->webPlans() as $target => $names) {
            $explicit = isset($rules['plans'][$target]);
            $tariffs[] = ['target' => $target] + $names + ['explicit' => $explicit] + ($rules['plans'][$target] ?? $rules['web_default']);
        }
        $product = Product::query()->where('key', self::PRODUCT)->first();
        $addon = [];
        if ($product !== null) {
            $plan = Plan::query()->where('product_id', $product->id)->where('key', self::PLAN)->first();
            $version = $plan?->currentVersion();
            foreach ($version === null ? [] : Price::query()->where('plan_version_id', $version->id)->where('state', 'active')->orderBy('currency')->orderBy('period')->get() as $price) {
                $addon[] = ['currency' => $price->currency, 'period' => $price->period, 'amount_minor' => (int) $price->amount_minor];
            }
        }

        return [
            'configured' => $this->configured(), 'web_families' => self::WEB_FAMILIES, 'currencies' => self::CURRENCIES,
            'web_default' => $rules['web_default'], 'tariffs' => $tariffs, 'stale' => array_values(array_diff(array_keys($rules['plans']), array_keys($this->webPlans()))),
            'addon' => ['product' => self::PRODUCT, 'plan' => self::PLAN, 'state' => $product?->state, 'prices' => $addon],
        ];
    }

    /** @return array<string, array{product: string, plan: string, family: string, product_state: string}> 'product/plan' => names, of every active web hosting tariff */
    public function webPlans(): array
    {
        $out = [];
        foreach (Product::query()->whereIn('family', self::WEB_FAMILIES)->orderBy('sort')->get() as $product) {
            foreach (Plan::query()->where('product_id', $product->id)->where('state', 'active')->orderBy('sort')->get() as $plan) {
                $out[$product->key.'/'.$plan->key] = ['product' => $product->localizedName('cs'), 'plan' => $plan->localizedName('cs'), 'family' => (string) $product->family, 'product_state' => (string) $product->state];
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $entry @return array{included: bool, price_minor: array<string,int>} */
    private static function read(array $entry): array
    {
        $prices = [];
        foreach ((array) ($entry['price_minor'] ?? []) as $currency => $minor) {
            if (in_array(strtoupper((string) $currency), self::CURRENCIES, true) && is_numeric($minor)) {
                $prices[strtoupper((string) $currency)] = max(0, (int) $minor);
            }
        }

        return ['included' => ($entry['included'] ?? false) === true, 'price_minor' => $prices];
    }

    /** @param array<string,mixed> $entry @return array{included: bool, price_minor: array<string,int>} */
    private static function strict(array $entry, string $field): array
    {
        $included = $entry['included'] ?? null;
        if ($included !== null && ! is_bool($included)) {
            throw new DomainError('penpot_offer_invalid', 'included is true or false.', 422, ['field' => "{$field}.included"]);
        }
        $prices = [];
        foreach ((array) ($entry['price_minor'] ?? []) as $currency => $minor) {
            $currency = strtoupper((string) $currency);
            if (! in_array($currency, self::CURRENCIES, true)) {
                throw new DomainError('penpot_offer_invalid', "Currency {$currency} is not sold (".implode(', ', self::CURRENCIES).').', 422, ['field' => "{$field}.price_minor"]);
            }
            if (! is_int($minor) || $minor < 0 || $minor > self::MAX_MINOR) {
                throw new DomainError('penpot_offer_invalid', 'A price is a whole number of minor units (haléře, cents) between 0 and '.self::MAX_MINOR.'.', 422, ['field' => "{$field}.price_minor.{$currency}"]);
            }
            $prices[$currency] = $minor;
        }
        $positive = array_filter($prices, fn (int $minor) => $minor > 0);
        if ($included === true || ($included === null && $positive === [])) {
            return ['included' => true, 'price_minor' => []]; // 0 = v ceně
        }
        if ($positive === []) {
            throw new DomainError('penpot_offer_price_required', 'A tariff that does not include Penpot needs a monthly price (0 means included).', 422, ['field' => "{$field}.price_minor"]);
        }
        ksort($positive);

        return ['included' => false, 'price_minor' => $positive];
    }
}
