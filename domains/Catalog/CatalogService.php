<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Illuminate\Support\Collection;
use Onhost\Domain\Catalog\Models\DomainPrice;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * Read model over the versioned catalog. Nothing here is hard-coded in UI: the
 * public web receives `catalog()` (families/plans/prices/compare), the checkout
 * receives `resolve()` snapshots that are locked into quotes.
 */
final class CatalogService
{
    /** @return Collection<int, Product> */
    public function products(?string $family = null, bool $sellableOnly = true): Collection
    {
        $query = Product::query()->with(['plans.versions.prices', 'options'])->orderBy('sort');
        if ($family !== null) {
            $query->where('family', $family);
        }
        if ($sellableOnly) {
            $query->where('state', 'active');
        }

        return $query->get();
    }

    public function product(string $key): Product
    {
        $product = Product::query()->with(['plans.versions.prices', 'options'])->where('key', $key)->first();
        if ($product === null) {
            throw DomainError::notFound("Product {$key}");
        }

        return $product;
    }

    /** @return array{product:Product, plan:Plan, version:PlanVersion, price:Price} */
    public function resolve(string $productKey, string $planKey, Currency|string $currency, string $period = 'month'): array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $product = $this->product($productKey);
        if (! $product->isSellable()) {
            throw new DomainError('product_not_sellable', "Product {$productKey} is not currently sellable.", 409);
        }
        $plan = $product->plans->firstWhere('key', $planKey);
        if ($plan === null || $plan->state !== 'active') {
            throw DomainError::notFound("Plan {$productKey}/{$planKey}");
        }
        $version = $plan->versions->firstWhere('version', $plan->current_version);
        if ($version === null) {
            throw new DomainError('plan_version_missing', "Plan {$planKey} has no current version.", 500);
        }
        $price = $version->prices->first(fn (Price $p) => $p->currency === $currency->value && $p->period === $period && $p->isCurrent());
        if ($price === null) {
            throw new DomainError('price_unavailable', "No {$period} price in {$currency->value} for {$productKey}/{$planKey}.", 409);
        }

        return ['product' => $product, 'plan' => $plan, 'version' => $version, 'price' => $price];
    }

    /**
     * The one reading of a cart line's options. Only options this product sells survive: a slider inside its range and
     * on whole steps, a select with a value the list offers, an add-on as a switch. The price and the delivered
     * resources are both computed from this result, so nothing is delivered that was not priced.
     *
     * @param  array<string, mixed>  $selections  whatever the cart sent
     * @return array<string, int|float|bool|string> only options this product sells, each within its range (H21, order-time twin)
     */
    public function normalizeOptions(Product $product, array $selections): array
    {
        $out = [];
        foreach ($product->options as $option) {
            /** @var ProductOption $option */
            if (! array_key_exists($option->key, $selections)) {
                continue;
            }
            $raw = $selections[$option->key];
            if ($option->kind === 'addon') {
                $out[$option->key] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            } elseif ($option->kind === 'select') {
                if (is_scalar($raw) && collect($option->choices ?? [])->contains(fn ($choice) => (string) ($choice['key'] ?? '') === (string) $raw)) {
                    $out[$option->key] = (string) $raw; // a value the list does not offer is not a choice
                }
            } elseif (is_numeric($raw)) {
                $min = (float) ($option->min ?? 0);
                $value = max($min, min((float) ($option->max ?? PHP_INT_MAX), (float) $raw));
                $step = (float) ($option->step ?? 0);
                if ($step > 0) {
                    $value = $min + floor(($value - $min) / $step + 1e-9) * $step; // whole steps only: half a vCPU is priced, never delivered
                }
                $out[$option->key] = floor($value) === $value ? (int) $value : $value;
            }
        }

        return $out;
    }

    /**
     * Configurator: base plan price + per-unit options (sliders/addons). Coefficients come from the catalog so the
     * configurator can never disagree with the order. The selections are expected normalized (`normalizeOptions`).
     *
     * @param  array<string, float|int|bool|string>  $selections
     * @return array{net:Money, lines:list<array{key:string,label:string,qty:float,unit_net:Money,net:Money}>}
     */
    public function configure(Product $product, Money $base, array $selections, int $periodMonths = 1): array
    {
        $lines = [];
        $net = $base;
        $periodMonths = max(1, $periodMonths); // option unit prices are monthly; a yearly base carries twelve of them
        foreach ($product->options as $option) {
            /** @var ProductOption $option */
            if (! array_key_exists($option->key, $selections)) {
                continue;
            }
            $raw = $selections[$option->key];
            $unitMinor = (int) ($option->price_per_unit_minor[$base->currency->value] ?? 0);
            $unit = Money::minor($unitMinor, $base->currency)->multiply($periodMonths);
            $qty = match ($option->kind) {
                'addon' => $raw ? 1.0 : 0.0,
                'select' => (float) (collect($option->choices ?? [])->firstWhere('key', $raw)['units'] ?? 0),
                default => max((float) ($option->min ?? 0), min((float) ($option->max ?? PHP_INT_MAX), (float) $raw)) - (float) ($option->default_value ?? 0),
            };
            if ($qty <= 0) {
                continue;
            }
            $lineNet = $unit->multiply((string) $qty);
            $net = $net->add($lineNet);
            $lines[] = ['key' => $option->key, 'label' => (string) ($option->label['cs'] ?? $option->key), 'qty' => $qty, 'unit_net' => $unit, 'net' => $lineNet];
        }

        return ['net' => $net, 'lines' => $lines];
    }

    public function tld(string $tld): TldPolicy
    {
        $policy = TldPolicy::query()->find(strtolower(ltrim($tld, '.')));
        if ($policy === null || ! $policy->registrable) {
            throw new DomainError('tld_not_supported', "TLD .{$tld} is not offered.", 409);
        }

        return $policy;
    }

    /** @return Collection<int, TldPolicy> */
    public function tlds(): Collection
    {
        return TldPolicy::query()->where('registrable', true)->orderBy('tld')->get();
    }

    public function domainPrice(string $tld, Currency|string $currency): DomainPrice
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $price = DomainPrice::query()->where('tld', strtolower(ltrim($tld, '.')))->where('currency', $currency->value)
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', now()))
            ->orderByDesc('effective_from')->first();
        if ($price === null) {
            throw new DomainError('price_unavailable', "No {$currency->value} price for .{$tld}.", 409);
        }

        return $price;
    }

    /** Shape consumed by the public web (`ONHOST_DATA.catalog/plans/compare`). */
    public function publicCatalog(string $locale, Currency|string $currency): array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $out = [];
        foreach ($this->products() as $product) {
            $plans = [];
            foreach ($product->plans->where('state', 'active') as $plan) {
                $version = $plan->versions->firstWhere('version', $plan->current_version);
                if ($version === null) {
                    continue;
                }
                $month = $version->prices->first(fn (Price $p) => $p->currency === $currency->value && $p->period === 'month' && $p->isCurrent());
                $year = $version->prices->first(fn (Price $p) => $p->currency === $currency->value && $p->period === 'year' && $p->isCurrent());
                $hour = $version->prices->first(fn (Price $p) => $p->currency === $currency->value && $p->period === 'hour' && $p->isCurrent());
                $plans[] = [
                    'key' => $plan->key,
                    'name' => $plan->localizedName($locale),
                    'description' => (string) ($plan->description[$locale] ?? $plan->description['cs'] ?? ''),
                    'highlighted' => $plan->highlighted,
                    'sla_class' => $plan->sla_class,
                    'entitlements' => $version->entitlements,
                    'limits' => $version->limits,
                    'features' => $version->features,
                    'price' => $month ? ['month' => $month->firstPeriodAmount(), 'month_renewal' => $month->renewalAmount(), 'setup' => $month->setup()] : null,
                    'price_year' => $year ? ['year' => $year->firstPeriodAmount(), 'year_renewal' => $year->renewalAmount()] : null,
                    'price_hour' => $hour ? ['hour' => $hour->amount(), 'monthly_cap' => $hour->monthlyCap()] : null,
                    'version' => $version->version,
                ];
            }
            $out[] = [
                'key' => $product->key,
                'family' => $product->family,
                'name' => $product->localizedName($locale),
                'description' => (string) ($product->description[$locale] ?? $product->description['cs'] ?? ''),
                'billing_model' => $product->billing_model,
                'executor' => $product->executor,
                'meta' => $product->meta,
                'plans' => $plans,
                'options' => $product->options->map(fn (ProductOption $o) => [
                    'key' => $o->key, 'kind' => $o->kind, 'label' => (string) ($o->label[$locale] ?? $o->label['cs'] ?? $o->key), 'unit' => $o->unit,
                    'min' => $o->min, 'max' => $o->max, 'step' => $o->step, 'default' => $o->default_value,
                    'price_per_unit' => Money::minor((int) ($o->price_per_unit_minor[$currency->value] ?? 0), $currency), 'choices' => $o->choices,
                ])->values()->all(),
            ];
        }

        return $out;
    }
}
