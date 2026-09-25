<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Limits;

use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Services\Metering\MetricRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
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
        // an extra is sold on top of the PLAN (the product's max is "this much more than the plan"), not on top of whatever the
        // service holds now — raises already bought count against the max, they do not move it
        $version = $mode === 'absolute' ? null : PlanVersion::query()->find($parent->plan_version_id);
        if ($mode !== 'absolute' && $version === null) { // a dangling plan reference would silently shrink the ceiling to the option alone
            throw new DomainError('limit_raise_plan_version_missing', 'Služba nemá dohledatelnou verzi tarifu, ke které by se navýšení měřilo; ozvěte se prosím podpoře.', 409, ['plan_version_id' => $parent->plan_version_id]);
        }
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
     * The fair-use limits a staff resize sends along: no more than the service was given (its desired spec) or, when it
     * carries none, what its plan sets (review round 2).
     *
     * @param  array<string,mixed>  $limits
     */
    public static function assertNoUnbilledLimits(Service $service, array $limits): void
    {
        if ($limits === []) {
            return;
        }
        $held = data_get($service->desired_spec, 'limits');
        if (! is_array($held)) {
            $version = PlanVersion::query()->find($service->plan_version_id);
            $held = $version === null ? [] : (array) $version->limits;
        }
        self::assertNotAbove($limits, $held, 'what the service holds');
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
    public static function assertOrderable(Quote $quote, string $source, ?CommandContext $context = null): void
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
            if (! $staff) {
                self::assertCustomerMayRaise((string) data_get($line, 'config.limit_raise.service_id', ''), $context);
            }
        }
    }

    /**
     * A customer's raise is billed to the organization, but it is a change of ONE service: whoever orders it must be allowed to
     * order for that service — the project they work in is the service's, and their grant covers the service's project
     * (a member of one project raised, and billed the organization for, a service of another; review round 2). Fails closed:
     * without an actor there is no raise.
     */
    private static function assertCustomerMayRaise(string $serviceId, ?CommandContext $context): void
    {
        $service = $serviceId === '' ? null : Service::query()->find($serviceId);
        if ($context === null || $service === null) {
            throw new DomainError('limit_raise_scope', 'Navýšení limitu této služby objednat nemůžete.', 403, ['field' => 'items']);
        }
        if ($context->actorType === 'system') {
            return;
        }
        $principal = match ($context->actorType) {
            'user', 'ai' => User::query()->find($context->onBehalfOfUserId ?? $context->actorId),
            'service_account' => ServiceAccount::query()->find($context->actorId),
            default => null,
        };
        $inAnotherProject = $context->projectId !== null && $context->projectId !== $service->project_id;
        $scope = CommandScope::resource($service->id, (string) $service->organization_id, $service->project_id !== null ? (string) $service->project_id : null);
        if ($principal === null || $inAnotherProject || ! app(Authorizer::class)->can($principal, 'catalog.order.create', $scope)) {
            throw new DomainError('limit_raise_scope', 'Navýšení limitu této služby objednat nemůžete: služba patří do projektu, pro který objednávat nesmíte.', 403, ['field' => 'items', 'service_id' => $service->id]);
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
            if (! self::notMore($value, $allowed[$key] ?? null)) {
                $above[] = (string) $key;
            }
        }
        if ($above !== []) {
            throw new DomainError('limit_raise_required', 'Vyšší limit než '.$what.' je navýšení a to se objednává (produkt limit-raise), nebo schvaluje zdarma druhou osobou: '.implode(', ', $above).'.', 403, ['keys' => $above]);
        }
    }

    /**
     * Whether a value gives no more than the one held (review round 2: a plain "greater than" let every sentinel through).
     * Fail closed: what is not provably the same or less is a raise. -1 is "unlimited" at ISPConfig; 0 or no number at all is
     * "not counted" to the platform's own check (ServiceService's plan limit) — so a positive number goes to neither; a word,
     * a list or a switch counts only when it is exactly what is held, except a switch turned off; a key the service does not
     * hold takes only an off switch, a zero or nothing.
     */
    private static function notMore(mixed $value, mixed $held): bool
    {
        $number = self::number($value);
        $heldNumber = self::number($held);
        if ($number !== null && $heldNumber !== null && $number === $heldNumber) {
            return true; // a repair to exactly what is held, whatever it is
        }
        if ($value === $held) {
            return true;
        }
        if ($value === false || $value === null || $number === 0.0) {
            // switching off is a lowering; clearing or zeroing a number the service counts is not (the count stops)
            return $held === null || is_bool($held) || ($heldNumber !== null && $heldNumber <= 0);
        }
        if ($number !== null) {
            return $number > 0 && $heldNumber !== null && $heldNumber > 0 && $number <= $heldNumber;
        }

        return false; // true for anything not already true, a word, a list, anything else: only as held
    }

    private static function number(mixed $value): ?float
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : null;
    }
}
