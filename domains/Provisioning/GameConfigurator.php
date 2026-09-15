<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * The game configurator (audit §5v): the customer picks a game first, then sizes the server with sliders — RAM, vCPU,
 * NVMe, backups, ports, databases. One catalogue plan (`game-custom`) is the base that covers the minimum; every unit
 * above it is a priced option of the `game` product, so the public web, the wizard and the quote price the same way.
 * Each game lifts the minimum through its floors (`GameTemplates::floors`), and "from" prices are the floors priced.
 */
final class GameConfigurator
{
    public function __construct(private readonly CatalogService $catalog, private readonly GameTemplates $templates) {}

    public function productKey(): string
    {
        return (string) config('onhost.content.game_product', 'game');
    }

    public function planKey(): string
    {
        return (string) config('onhost.game.configurator.plan', 'game-custom');
    }

    /** The base plan and its priced sliders; null until the catalogue carries the configurator plan. @return array{product:Product, base:array<string,mixed>, options:list<ProductOption>}|null */
    public function definition(): ?array
    {
        $product = Product::query()->where('key', $this->productKey())->with('options')->first();
        if ($product === null) {
            return null;
        }
        $plan = Plan::query()->where('product_id', $product->id)->where('key', $this->planKey())->first();
        $version = $plan === null ? null : PlanVersion::query()->where('plan_id', $plan->id)->where('version', $plan->current_version)->first();
        if ($plan === null || $plan->state !== 'active' || $version === null) {
            return null;
        }

        return ['product' => $product, 'base' => (array) $version->entitlements, 'options' => $product->options->sortBy('sort')->values()->all()];
    }

    /**
     * The smallest configuration a game runs with: every slider at its own minimum lifted to the game's floor.
     *
     * @return array<string,int> option key → value
     */
    public function minimumOptions(string $egg): array
    {
        $definition = $this->definition();
        if ($definition === null) {
            return [];
        }
        $floors = $this->templates->floors($egg);
        $floorFor = ['ram_gb' => (int) ceil($floors['min_ram_mb'] / 1024), 'vcpu' => $floors['min_vcpu'], 'nvme_gb' => $floors['min_nvme_gb']];
        $out = [];
        foreach ($definition['options'] as $option) {
            $min = (int) ($option->default_value ?? $option->min ?? 0);
            $out[$option->key] = self::snap(max($min, $floorFor[$option->key] ?? 0), $option);
        }

        return $out;
    }

    /**
     * A customer's selection clamped to the game's floors and the sliders' bounds/steps (what the web sends is never
     * trusted to be at least the minimum).
     *
     * @param  array<string,mixed>  $selections
     * @return array<string,int>
     */
    public function clamp(string $egg, array $selections): array
    {
        $definition = $this->definition();
        $out = $this->minimumOptions($egg);
        foreach ($definition['options'] ?? [] as $option) {
            if (! isset($selections[$option->key]) || ! is_numeric($selections[$option->key])) {
                continue;
            }
            $out[$option->key] = self::snap(max($out[$option->key] ?? 0, (int) $selections[$option->key]), $option);
        }

        return $out;
    }

    /** Monthly net price of a configuration (the base plan plus the options above their defaults), the quote's arithmetic. @param array<string,mixed> $selections */
    public function price(string $egg, array $selections, Currency|string $currency = 'CZK'): Money
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $resolved = $this->catalog->resolve($this->productKey(), $this->planKey(), $currency, 'month');

        return $this->catalog->configure($resolved['product'], $resolved['price']->firstPeriodAmount(), $this->clamp($egg, $selections))['net'];
    }

    /** "Již od" — the game's minimum configuration priced. */
    public function fromPrice(string $egg, Currency|string $currency = 'CZK'): Money
    {
        return $this->price($egg, [], $currency);
    }

    /**
     * What the public web and the wizard render (`ONHOST_DATA.gameConfig`): the base, the sliders with their unit prices
     * and the games the panel really offers with floors, slots, versions, inputs and from-prices.
     *
     * @return array<string,mixed>|null
     */
    public function offer(string $locale = 'cs', Currency|string $currency = 'CZK'): ?array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $definition = $this->definition();
        if ($definition === null) {
            return null;
        }
        try {
            $resolved = $this->catalog->resolve($this->productKey(), $this->planKey(), $currency, 'month');
        } catch (DomainError) {
            return null;
        }
        $product = $definition['product'];
        $options = [];
        foreach ($definition['options'] as $option) {
            $options[] = [
                'key' => $option->key, 'kind' => $option->kind, 'label' => (string) ($option->label[$locale] ?? $option->label['cs'] ?? $option->key), 'desc' => (string) (data_get($option->meta, "desc.{$locale}") ?? data_get($option->meta, 'desc.cs') ?? ''), 'unit' => $option->unit,
                'min' => $option->min !== null ? (float) $option->min : null, 'max' => $option->max !== null ? (float) $option->max : null, 'step' => $option->step !== null ? (float) $option->step : null, 'default' => $option->default_value !== null ? (float) $option->default_value : null,
                'price' => ((int) ($option->price_per_unit_minor[$currency->value] ?? 0)) / 100, 'entitlement' => (string) data_get($option->meta, 'entitlement.key', $option->key),
            ];
        }
        $games = [];
        foreach (array_values(array_map('strval', (array) data_get($product->meta, 'eggs', []))) as $egg) {
            if (! $this->templates->availability($egg)['available']) {
                continue; // §5s: a template no panel can create is not offered
            }
            $preset = (array) config("onhost.game.eggs.{$egg}", []);
            $floors = $this->templates->floors($egg);
            $minimum = $this->minimumOptions($egg);
            $games[] = [
                'key' => $egg, 'label' => (string) ($preset['label'] ?? $egg), 'note' => (string) ($preset['note'] ?? ''), 'minecraft' => str_starts_with($egg, 'minecraft-'),
                'min' => $minimum, 'slot_mb' => $floors['slot_mb'], 'slots_from' => $this->templates->slots($egg, ($minimum['ram_gb'] ?? 1) * 1024),
                'versions' => array_values(array_map('strval', (array) ($preset['versions'] ?? []))), 'inputs' => $this->templates->inputForms($egg),
                'from' => (float) $this->fromPrice($egg, $currency)->toDecimal(),
            ];
        }

        return [
            'product_key' => $product->key, 'plan_key' => $this->planKey(), 'name' => (string) ($product->name[$locale] ?? $product->name['cs'] ?? $product->key),
            'base_price' => (float) $resolved['price']->firstPeriodAmount()->toDecimal(), 'base' => $definition['base'], 'options' => $options, 'games' => $games, 'currency' => $currency->value,
        ];
    }

    private static function snap(int $value, ProductOption $option): int
    {
        $step = max(1, (int) ($option->step ?? 1));
        $min = (int) ($option->min ?? 0);
        $value = $min + (int) (ceil(max(0, $value - $min) / $step) * $step);
        if ($option->max !== null) {
            $value = min((int) $option->max, $value);
        }

        return $value;
    }
}
