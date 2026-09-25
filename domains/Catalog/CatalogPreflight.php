<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Platform\Errors\DomainError;

/**
 * Before a price or plan change is dispatched (owner decision 13: it takes a second person), two things happen here.
 *
 *  1. The change is checked the way the handler would check it, without writing anything. A change that would be refused
 *     anyway is refused now, before anybody is asked to approve it: an approver should never read a request that cannot run.
 *     The handler runs the same checks again (the same methods), as a guard and because the catalogue can move in between.
 *  2. The change is bound to what it was asked against (`bind`): the plan version on sale, or a digest of the whole setting
 *     it replaces. The binding is part of the payload and therefore of the approved hash. When somebody else changes the plan
 *     or the setting before the approved request is repeated, the repeat is another request (a new approval), and the handler
 *     refuses a stale binding with 409 `catalog_changed_since_request` instead of overwriting the newer change.
 */
final class CatalogPreflight
{
    public function __construct(
        private readonly PricingRules $rules,
        private readonly PlanVersioning $plans,
        private readonly DeletionPolicy $lifecycle,
    ) {}

    /** Refuses the change with the handler's own error, or returns quietly. */
    public function check(CatalogCommand $command): void
    {
        match ($command->op()) {
            'pricing.commit_discounts.set' => $this->rules->normalizeCommitDiscounts((array) $command->get('config', [])),
            'pricing.regions.set' => $this->rules->normalizeRegions((array) $command->get('regions', [])),
            'pricing.domain_discount.set' => $this->rules->normalizeDomainDiscount((string) $command->get('tld'), (array) $command->get('discount', [])),
            'pricing.addon_products.set' => $this->rules->normalizeAddonProducts((string) $command->get('product_key'), (array) $command->get('addon_products', [])),
            'promo.upsert' => self::promo((array) $command->get('promo', [])),
            'option.upsert' => self::option((string) $command->get('product_key'), (array) $command->get('option', [])),
            'option.delete' => self::product((string) $command->get('product_key')),
            'product.state' => self::productState((string) $command->get('state'), (array) $command->get('products', [])),
            'plan.publish' => $this->plans->check((string) $command->get('product_key'), (string) $command->get('plan_key'), $command->payload),
            'plan.activate_version' => $this->plans->checkActivate((string) $command->get('product_key'), (string) $command->get('plan_key'), (int) $command->get('version')),
            'pricing.domain_discount.delete', 'promo.delete', 'lifecycle.set', 'panel_nav.set' => null, // nothing to refuse: a withdrawal, a clamped setting, the sidebar
            default => throw new DomainError('op_unknown', 'Unknown catalog operation.', 422, ['field' => 'op']),
        };
    }

    /**
     * What the change was asked against, to put into its payload: `base_version` for a plan, `base` (a digest of what is stored
     * now) for a setting replaced as a whole or for the one row a change replaces or removes (a TLD's discount, a promo code, an
     * option, a product's add-on list). Nothing for the sidebar and the on-sale switch.
     *
     * @return array<string, int|string>
     */
    public function bind(CatalogCommand $command): array
    {
        if (in_array($command->op(), ['plan.publish', 'plan.activate_version'], true)) {
            return ['base_version' => $this->currentVersion((string) $command->get('product_key'), (string) $command->get('plan_key'))];
        }
        $digest = $this->digest($command);

        return $digest === null ? [] : ['base' => $digest];
    }

    /** The handler's side of `bind` for settings (a plan checks its own binding under its row lock in PlanVersioning). */
    public function assertUnchanged(CatalogCommand $command): void
    {
        $base = $command->get('base');
        if (! is_string($base) || $base === '') {
            return; // the CLI and other callers that did not bind
        }
        if (! hash_equals((string) $this->digest($command), $base)) {
            throw new DomainError('catalog_changed_since_request', 'The catalogue changed since the request was made; ask again against the current value.', 409, ['field' => 'base']);
        }
    }

    /**
     * A promo code as the handler stores it, or the refusal.
     *
     * @param  array<string,mixed>  $in
     * @return array{code: string, attributes: array<string,mixed>}
     */
    public static function promo(array $in): array
    {
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        if (! preg_match('/^[A-Z0-9_-]{3,40}$/', $code)) {
            throw new DomainError('promo_code_invalid', 'A promo code has 3–40 letters, digits, dashes or underscores.', 422, ['field' => 'code']);
        }
        $kind = (string) ($in['kind'] ?? 'percent');
        $value = (float) ($in['value'] ?? 0);
        if ($kind === 'percent' && ($value <= 0 || $value > 100)) {
            throw new DomainError('percent_invalid', 'A percentage discount is between 0 and 100 %.', 422, ['field' => 'value']);
        }
        if ($kind === 'fixed' && $value <= 0) {
            throw new DomainError('amount_invalid', 'A fixed discount must be positive.', 422, ['field' => 'value']);
        }
        $applies = array_values(array_filter(array_map(fn ($f) => strtolower(trim((string) $f)), (array) ($in['applies_to'] ?? [])), fn ($f) => $f !== ''));

        return ['code' => $code, 'attributes' => [
            'kind' => $kind, 'value' => $value, 'currency' => $kind === 'fixed' ? strtoupper((string) ($in['currency'] ?? 'CZK')) : null,
            'valid_from' => isset($in['valid_from']) && $in['valid_from'] !== '' ? Carbon::parse((string) $in['valid_from']) : null,
            'valid_to' => isset($in['valid_to']) && $in['valid_to'] !== '' ? Carbon::parse((string) $in['valid_to']) : null,
            'max_uses' => isset($in['max_uses']) && $in['max_uses'] !== '' ? (int) $in['max_uses'] : null,
            'applies_to' => $applies === [] ? null : $applies, 'first_period_only' => (bool) ($in['first_period_only'] ?? true), 'state' => in_array($in['state'] ?? 'active', ['active', 'paused', 'retired'], true) ? ($in['state'] ?? 'active') : 'active',
        ]];
    }

    /**
     * A priced option of a product as the handler stores it, or the refusal.
     *
     * @param  array<string,mixed>  $in
     * @return array{product: Product, key: string, attributes: array<string,mixed>}
     */
    public static function option(string $productKey, array $in): array
    {
        $product = self::product($productKey);
        $key = strtolower(trim((string) ($in['key'] ?? '')));
        if (! preg_match('/^[a-z0-9_]{1,60}$/', $key)) {
            throw new DomainError('option_key_invalid', 'An option key has letters, digits and underscores only.', 422, ['field' => 'key']);
        }
        $kind = (string) ($in['kind'] ?? 'addon');
        if (! in_array($kind, ['slider', 'addon', 'select'], true)) {
            throw new DomainError('option_kind_invalid', 'Option kind must be slider, addon or select.', 422, ['field' => 'kind']);
        }
        $label = (array) ($in['label'] ?? []);
        if (trim((string) ($label['cs'] ?? '')) === '') {
            throw new DomainError('option_label_required', 'The Czech label is required.', 422, ['field' => 'label']);
        }
        $label = ['cs' => trim((string) $label['cs']), 'en' => trim((string) ($label['en'] ?? $label['cs']))];
        $czk = (float) ($in['price_czk'] ?? 0);
        $eur = isset($in['price_eur']) && $in['price_eur'] !== '' ? (float) $in['price_eur'] : round($czk / 25, 2);
        if ($czk < 0 || $eur < 0) {
            throw new DomainError('amount_invalid', 'Unit prices cannot be negative.', 422, ['field' => 'price_czk']);
        }
        $choices = $kind === 'select' ? self::choices((array) ($in['choices'] ?? [])) : null;
        $num = fn (string $k) => isset($in[$k]) && $in[$k] !== '' ? (float) $in[$k] : null;
        $meta = array_filter(['cfg_key' => $key, 'desc' => array_filter(['cs' => trim((string) ($in['desc']['cs'] ?? '')), 'en' => trim((string) ($in['desc']['en'] ?? ''))]), 'entitlement' => $in['entitlement'] ?? null], fn ($v) => $v !== null && $v !== []);

        return ['product' => $product, 'key' => $key, 'attributes' => [
            'kind' => $kind, 'label' => $label, 'unit' => isset($in['unit']) && $in['unit'] !== '' ? mb_substr((string) $in['unit'], 0, 24) : null,
            'min' => $kind === 'slider' ? ($num('min') ?? 0) : null, 'max' => $kind === 'slider' ? $num('max') : null, 'step' => $kind === 'slider' ? ($num('step') ?? 1) : null, 'default_value' => $kind === 'slider' ? ($num('default') ?? $num('min') ?? 0) : null,
            'price_per_unit_minor' => ['CZK' => (int) round($czk * 100), 'EUR' => (int) round($eur * 100)], 'choices' => $choices, 'meta' => $meta,
            'sort' => isset($in['sort']) && $in['sort'] !== '' ? (int) $in['sort'] : (int) (ProductOption::query()->where('product_id', $product->id)->max('sort') ?? 0) + 10,
        ]];
    }

    public static function product(string $key): Product
    {
        return Product::query()->where('key', $key)->first() ?? throw DomainError::notFound("Product {$key}");
    }

    /** @param list<string> $keys @return list<Product> */
    public static function productState(string $state, array $keys): array
    {
        if (! in_array($state, ['active', 'draft'], true)) {
            throw new DomainError('state_invalid', 'State must be active or draft.', 422, ['field' => 'state']);
        }

        return array_map(fn ($key) => self::product((string) $key), array_values($keys));
    }

    /** @param array<int,mixed> $in @return list<array{key:string,label:mixed,units:float}> */
    private static function choices(array $in): array
    {
        $choices = [];
        foreach ($in as $choice) {
            $choice = (array) $choice;
            $ck = strtolower(trim((string) ($choice['key'] ?? '')));
            if ($ck === '') {
                continue;
            }
            $choices[] = ['key' => $ck, 'label' => is_array($choice['label'] ?? null) ? $choice['label'] : (string) ($choice['label'] ?? $ck), 'units' => (float) ($choice['units'] ?? 0)];
        }
        if ($choices === []) {
            throw new DomainError('option_choices_required', 'A select option needs at least one choice.', 422, ['field' => 'choices']);
        }

        return $choices;
    }

    private function currentVersion(string $productKey, string $planKey): int
    {
        $product = self::product($productKey);
        $plan = Plan::query()->where('product_id', $product->id)->where('key', $planKey)->first() ?? throw DomainError::notFound("Plan {$productKey}/{$planKey}");

        return (int) $plan->current_version;
    }

    /**
     * A digest of what the operation replaces or removes as it is stored now (null for an operation bound to nothing). What a
     * customer does to a row is left out — the uses of a promo code — so a redemption does not turn an approval stale.
     */
    private function digest(CatalogCommand $command): ?string
    {
        $value = match ($command->op()) {
            'pricing.commit_discounts.set' => $this->rules->commitDiscounts(),
            'pricing.regions.set' => $this->rules->regions(),
            'lifecycle.set' => $this->lifecycle->all(),
            'pricing.domain_discount.set', 'pricing.domain_discount.delete' => $this->rules->domainDiscounts()[strtolower(ltrim(trim((string) $command->get('tld')), '.'))] ?? null,
            'pricing.addon_products.set' => $this->rules->addonProducts((string) $command->get('product_key')),
            'promo.upsert', 'promo.delete' => self::promoRow(strtoupper(trim((string) ($command->op() === 'promo.upsert' ? $command->get('promo.code') : $command->get('code'))))),
            'option.upsert' => self::optionRow((string) $command->get('product_key'), (string) $command->get('option.key')),
            'option.delete' => self::optionRow((string) $command->get('product_key'), (string) $command->get('key')),
            default => false,
        };
        if ($value === false) {
            return null;
        }

        return hash('sha256', (string) json_encode([$command->op() => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return array<string,mixed>|null the stored columns of the code as they are in the database, without its uses */
    private static function promoRow(string $code): ?array
    {
        $promo = PromoCode::query()->where('code', $code)->first();

        return $promo === null ? null : Arr::only($promo->getAttributes(), ['kind', 'value', 'currency', 'valid_from', 'valid_to', 'max_uses', 'applies_to', 'first_period_only', 'state']);
    }

    /** @return array<string,mixed>|null */
    private static function optionRow(string $productKey, string $key): ?array
    {
        $productId = Product::query()->where('key', $productKey)->value('id');
        $option = $productId === null ? null : ProductOption::query()->where('product_id', $productId)->where('key', strtolower(trim($key)))->first();

        return $option === null ? null : [
            'kind' => $option->kind, 'label' => $option->label, 'unit' => $option->unit, 'min' => $option->min, 'max' => $option->max, 'step' => $option->step, 'default_value' => $option->default_value,
            'price_per_unit_minor' => $option->price_per_unit_minor, 'choices' => $option->choices, 'meta' => $option->meta, 'sort' => $option->sort,
        ];
    }
}
