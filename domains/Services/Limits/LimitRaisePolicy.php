<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Limits;

use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;

/**
 * What may be raised, and what may not be raised without paying (owner decision 8, TASK-0022 limit-raise).
 *
 * A raise is one number of one service, sold at the parent product's own option price. Only a number the platform actually
 * enforces for the service's family can be sold (`MetricRegistry`: a hard limit, per service, applied at the panel or checked
 * by the platform before every create) — selling more of a number nothing enforces would be selling nothing. And only where the
 * product prices it: the option is the price list, there is no second one.
 *
 * v1 leaves out what takes a dedicated share of one node (vCPU, RAM, the disk of a VPS or a game server): a resize has no
 * node-capacity check yet, so a raise there could promise what the node does not have. Cloud is not in the families at all.
 *
 * The other half: the two staff paths that used to hand out more for nothing (`resize` with any numbers, `service.create`
 * with its own entitlements) now refuse to go above what the service holds or its plan sells — a raise is an order.
 */
final class LimitRaisePolicy
{
    /** Order sources that are staff at work (assisted orders, the CLI): they may order a raise while customers cannot yet. */
    public const STAFF_SOURCES = ['staff', 'cli'];

    /** An option whose unit is not the entitlement's own: the configurator's GB slider sells MB of RAM. */
    private const OPTION_TARGETS = ['ram_gb' => ['ram_mb', 1024], 'disk_gb' => ['nvme_gb', 1]];

    /** A dedicated share of one node, on the families whose node has to have it (no capacity check for a resize yet). */
    private const CAPACITY_KEYS = ['vcpu', 'ram_mb', 'nvme_gb'];

    private const CAPACITY_FAMILIES = ['cloud', 'data', 'game'];

    /**
     * How a number of this service is raised, or the reason it cannot be.
     *
     * @return array{metric: string, option_key: string, label: array<string,string>, unit: string, unit_price_minor: array<string,int>, scale: int, mode: string, step: int, ceiling: int|null}
     */
    public static function raisable(Service $parent, string $metric): array
    {
        $family = (string) $parent->family;
        $entry = MetricRegistry::get($metric);
        $enforced = $entry !== null && MetricRegistry::isKept($metric, $family) && $entry['scope'] === 'service' && $entry['limit_kind'] === MetricRegistry::HARD
            && ($entry['status'] === MetricRegistry::ENFORCED_ONLY || $entry['drives_guard']);
        if (! $enforced) {
            throw new DomainError('limit_raise_not_enforced', "Limit {$metric} u této služby platforma nehlídá, takže jeho navýšení by nic nezměnilo.", 422, ['field' => 'metric', 'metric' => $metric]);
        }
        if ($family === 'addon' || ! in_array($family, (array) config('onhost.limit_raise.families', []), true)) {
            throw new DomainError('limit_raise_family', 'U tohoto druhu služby se limity zatím navyšovat nedají.', 422, ['field' => 'service_id', 'family' => $family]);
        }
        if (in_array($family, self::CAPACITY_FAMILIES, true) && in_array($metric, self::CAPACITY_KEYS, true)) {
            throw new DomainError('limit_raise_capacity', 'Výkon a prostor serveru se zatím navyšují změnou tarifu, ne samostatným navýšením.', 422, ['field' => 'metric', 'metric' => $metric]);
        }
        $option = self::optionFor($parent, $metric);
        $prices = [];
        foreach (Currency::cases() as $currency) { // the currencies the platform sells in; an option priced in another is not a price here
            $minor = $option === null ? null : data_get($option->price_per_unit_minor, $currency->value);
            if (is_numeric($minor)) {
                $prices[$currency->value] = (int) $minor;
            }
        }
        if ($option === null || $prices === [] || max($prices) <= 0) {
            throw new DomainError('limit_raise_unpriced', "Produkt služby nemá pro {$metric} cenu, za kterou by se dal navýšit.", 422, ['field' => 'metric', 'metric' => $metric]);
        }
        [, $scale, $mode] = self::targetOf($option);
        $max = $option->max !== null ? (int) floor((float) $option->max) : null;
        $version = $mode === 'absolute' ? null : PlanVersion::query()->find($parent->plan_version_id);
        $base = $version === null ? 0 : (int) (((array) $version->entitlements)[$metric] ?? 0);
        $label = ['cs' => (string) data_get($option->label, 'cs', $option->key)];
        $label['en'] = (string) data_get($option->label, 'en', $label['cs']);

        return [
            'metric' => $metric, 'option_key' => (string) $option->key, 'label' => $label, 'unit' => (string) ($option->unit ?? 'ks'),
            'unit_price_minor' => $prices, 'scale' => $scale, 'mode' => $mode, 'step' => max(1, (int) round((float) ($option->step ?? 1))),
            // an extra is sold on top of the plan (up to its max), an absolute slider is the whole number
            'ceiling' => $max === null ? null : $base + $max * $scale,
        ];
    }

    /**
     * A staff resize may repair or lower what the service holds; a number above it is a raise, and a raise is an order.
     *
     * @param  array<string,mixed>  $target
     */
    public static function assertNoUnbilledRaise(Service $service, array $target): void
    {
        self::assertNotAbove($target, (array) $service->entitlements, 'what the service holds');
    }

    /**
     * A service created without an order (staff quick action) gets its plan, not more.
     *
     * @param  array<string,mixed>  $target
     * @param  array<string,mixed>  $plan
     */
    public static function assertWithinPlan(array $target, array $plan): void
    {
        self::assertNotAbove($target, $plan, 'what its plan sells');
    }

    /** Customers order a raise only once `onhost.limit_raise.customer_orders` is on; a raise at no charge is staff's alone. */
    public static function assertOrderable(Quote $quote, string $source): void
    {
        $staff = in_array($source, self::STAFF_SOURCES, true);
        foreach ((array) $quote->lines as $line) {
            if (($line['product_key'] ?? '') !== LimitRaises::PRODUCT) {
                continue;
            }
            if (data_get($line, 'config.limit_raise.waived') !== null && $source !== 'staff') {
                throw new DomainError('limit_raise_staff_only', 'Navýšení zdarma zadává jen obsluha se schválením druhé osoby.', 403, ['field' => 'items']);
            }
            if (! $staff && ! (bool) config('onhost.limit_raise.customer_orders', false)) {
                throw new DomainError('limit_raise_staff_only', 'Navýšení limitu zatím objednává obsluha; napište nám prosím do podpory.', 403, ['field' => 'items']);
            }
        }
    }

    /**
     * The entitlement an option delivers, how many of its units one option unit is, and whether it is the whole number.
     *
     * @return array{0: string, 1: int, 2: string}
     */
    public static function targetOf(ProductOption $option): array
    {
        $rule = (array) data_get($option->meta, 'entitlement', []);
        $fallback = self::OPTION_TARGETS[(string) $option->key] ?? [(string) $option->key, 1];
        $target = (string) ($rule['key'] ?? $fallback[0]);
        $scale = max(1, (int) ($rule['scale'] ?? $fallback[1]));

        return [$target, $scale, ($rule['mode'] ?? 'extra') === 'absolute' ? 'absolute' : 'extra'];
    }

    private static function optionFor(Service $parent, string $metric): ?ProductOption
    {
        $productId = Product::query()->where('key', (string) $parent->product_key)->value('id');
        if ($productId === null) {
            return null;
        }

        return ProductOption::query()->where('product_id', $productId)->where('kind', 'slider')->orderBy('sort')->get()
            ->first(fn (ProductOption $option) => self::targetOf($option)[0] === $metric);
    }

    /**
     * @param  array<string,mixed>  $target
     * @param  array<string,mixed>  $allowed
     */
    private static function assertNotAbove(array $target, array $allowed, string $what): void
    {
        $above = [];
        foreach ($target as $key => $value) {
            if (is_int($value) || (is_string($value) && is_numeric($value)) || is_float($value)) {
                if ((float) $value > (float) (is_numeric($allowed[$key] ?? null) ? $allowed[$key] : 0)) {
                    $above[] = (string) $key;
                }
            }
        }
        if ($above !== []) {
            throw new DomainError('limit_raise_required', 'Vyšší limit než '.$what.' je navýšení a to se objednává (produkt limit-raise), nebo schvaluje zdarma druhou osobou: '.implode(', ', $above).'.', 403, ['keys' => $above]);
        }
    }
}
