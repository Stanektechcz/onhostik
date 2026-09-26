<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Catalog\PlanVersioning;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Platform\Commands\CommandScope;

/**
 * Pricing rules (Nastavení systému → Slevy, doplňky a konfigurátor): commitment discounts per product family,
 * domain discounts per TLD, promo codes, the add-on products each product may carry in the cart and the priced
 * options (per-item add-ons and configurator parameters) of every product. The public web and the quote read the
 * same rules, so a discount that is not approved here never appears in the cart.
 */
final class PricingController extends ApiController
{
    /** Why a price changes: the second person reads it on the approvals page. */
    private const REASON = ['nullable', 'string', 'max:250'];

    public function index(Request $request, PricingRules $rules, CatalogService $catalog): JsonResponse
    {
        $this->api->authorize($request, 'catalog.manage', CommandScope::global());
        $products = Product::query()->with(['options', 'plans'])->orderBy('sort')->get();

        return $this->ok([
            'commit_discounts' => $rules->commitDiscounts(), 'commit_months' => PricingRules::COMMIT_MONTHS, 'regions' => array_values($rules->regions()),
            'domain_discounts' => $rules->domainDiscounts(),
            'promo_codes' => PromoCode::query()->orderBy('code')->get()->map(fn (PromoCode $p) => [
                'code' => $p->code, 'kind' => $p->kind, 'value' => (float) $p->value, 'currency' => $p->currency, 'valid_from' => $p->valid_from?->toIso8601String(), 'valid_to' => $p->valid_to?->toIso8601String(),
                'max_uses' => $p->max_uses, 'uses' => (int) $p->uses, 'applies_to' => $p->applies_to ?? [], 'first_period_only' => (bool) $p->first_period_only, 'state' => $p->state, 'usable' => $p->isUsable(),
            ])->values()->all(),
            'products' => $products->map(fn (Product $p) => [
                'key' => $p->key, 'name' => $p->localizedName('cs'), 'family' => $p->family, 'state' => $p->state, 'builder' => (bool) (($p->meta ?? [])['builder'] ?? false),
                'plans' => $p->plans->where('state', 'active')->map(fn ($plan) => ['key' => $plan->key, 'name' => $plan->localizedName('cs')])->values()->all(),
                'addon_products' => $rules->addonProducts($p),
                'options' => $p->options->sortBy('sort')->map(fn (ProductOption $o) => self::option($o))->values()->all(),
            ])->values()->all(),
            'addon_candidates' => $products->where('family', 'addon')->map(fn (Product $p) => ['key' => $p->key, 'name' => $p->localizedName('cs'), 'state' => $p->state])->values()->all(), // a draft add-on is listed and never offered to a customer
            'families' => array_values(array_unique($products->pluck('family')->all())),
            'tlds' => $catalog->tlds()->pluck('tld')->values()->all(),
        ]);
    }

    public function setCommitDiscounts(Request $request): JsonResponse
    {
        $data = $request->validate(['default' => ['nullable', 'array'], 'default.*' => ['numeric', 'min:0', 'max:90'], 'families' => ['nullable', 'array'], 'families.*' => ['array'], 'families.*.*' => ['numeric', 'min:0', 'max:90'], 'reason' => self::REASON]);

        return $this->catalog($request, 'catalog.commit_discounts', ['op' => 'pricing.commit_discounts.set', 'config' => ['default' => $data['default'] ?? [], 'families' => $data['families'] ?? []]], $data['reason'] ?? null);
    }

    /** Regional pricing (audit §5j-8): country groups with a suggested currency and a percentage on the list price; an empty list restores the defaults. */
    public function setRegions(Request $request): JsonResponse
    {
        $data = $request->validate(['regions' => ['present', 'array', 'max:20'], 'regions.*.key' => ['required', 'string', 'max:20'], 'regions.*.label' => ['nullable', 'string', 'max:40'], 'regions.*.countries' => ['required', 'array', 'min:1'], 'regions.*.countries.*' => ['string', 'size:2'], 'regions.*.currency' => ['nullable', 'in:CZK,EUR'], 'regions.*.adjust_pct' => ['nullable', 'numeric', 'min:-50', 'max:100'], 'reason' => self::REASON]);

        return $this->catalog($request, 'catalog.regions', ['op' => 'pricing.regions.set', 'regions' => $data['regions']], $data['reason'] ?? null);
    }

    public function setDomainDiscount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tld' => ['required', 'string', 'max:32'], 'register' => ['nullable', 'numeric', 'min:0', 'max:100'], 'renew' => ['nullable', 'numeric', 'min:0', 'max:100'], 'transfer' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date'], 'label' => ['nullable', 'string', 'max:120'], 'reason' => self::REASON,
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        return $this->catalog($request, 'catalog.domain_discount:'.$data['tld'], ['op' => 'pricing.domain_discount.set', 'tld' => $data['tld'], 'discount' => $data], $reason);
    }

    /** A withdrawal: the TLD goes back to its list price. One person with a step-up (CatalogCommand::STEP_UP_OPS). */
    public function deleteDomainDiscount(Request $request, string $tld): JsonResponse
    {
        return $this->catalog($request, 'catalog.domain_discount.delete:'.$tld, ['op' => 'pricing.domain_discount.delete', 'tld' => $tld]);
    }

    /** An active code is a price change (second person); pausing or retiring one is a withdrawal (step-up). */
    public function upsertPromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'], 'kind' => ['required', 'in:percent,fixed'], 'value' => ['required', 'numeric', 'min:0'], 'currency' => ['nullable', 'in:CZK,EUR'],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date'], 'max_uses' => ['nullable', 'integer', 'min:1'], 'applies_to' => ['nullable', 'array', 'max:20'], 'applies_to.*' => ['string', 'max:24'],
            'first_period_only' => ['nullable', 'boolean'], 'state' => ['nullable', 'in:active,paused,retired'], 'reason' => self::REASON,
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        return $this->catalog($request, 'catalog.promo:'.strtoupper($data['code']), ['op' => 'promo.upsert', 'promo' => $data], $reason);
    }

    public function deletePromo(Request $request, string $code): JsonResponse
    {
        return $this->catalog($request, 'catalog.promo.delete:'.strtoupper($code), ['op' => 'promo.delete', 'code' => $code]);
    }

    public function upsertOption(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_key' => ['required', 'string', 'max:60'], 'key' => ['required', 'string', 'max:60'], 'kind' => ['required', 'in:slider,addon,select'],
            'label' => ['required', 'array'], 'label.cs' => ['required', 'string', 'max:120'], 'label.en' => ['nullable', 'string', 'max:120'], 'desc' => ['nullable', 'array'], 'desc.cs' => ['nullable', 'string', 'max:250'], 'desc.en' => ['nullable', 'string', 'max:250'],
            'unit' => ['nullable', 'string', 'max:24'], 'min' => ['nullable', 'numeric'], 'max' => ['nullable', 'numeric'], 'step' => ['nullable', 'numeric', 'gt:0'], 'default' => ['nullable', 'numeric'],
            'price_czk' => ['required', 'numeric', 'min:0'], 'price_eur' => ['nullable', 'numeric', 'min:0'], 'choices' => ['nullable', 'array', 'max:20'], 'choices.*.key' => ['required_with:choices', 'string', 'max:40'], 'choices.*.units' => ['nullable', 'numeric', 'min:0'],
            'entitlement' => ['nullable', 'array'], 'sort' => ['nullable', 'integer', 'min:0'], 'reason' => self::REASON,
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        return $this->catalog($request, 'catalog.option:'.$data['product_key'].':'.$data['key'], ['op' => 'option.upsert', 'product_key' => $data['product_key'], 'option' => $data], $reason);
    }

    public function deleteOption(Request $request, string $product, string $key): JsonResponse
    {
        $data = $request->validate(['reason' => self::REASON]);

        return $this->catalog($request, 'catalog.option.delete:'.$product.':'.$key, ['op' => 'option.delete', 'product_key' => $product, 'key' => $key], $data['reason'] ?? null);
    }

    public function setAddonProducts(Request $request): JsonResponse
    {
        $data = $request->validate(['product_key' => ['required', 'string', 'max:60'], 'addon_products' => ['present', 'array', 'max:20'], 'addon_products.*' => ['string', 'max:60']]);

        return $this->catalog($request, 'catalog.addon_products:'.$data['product_key'], ['op' => 'pricing.addon_products.set', 'product_key' => $data['product_key'], 'addon_products' => $data['addon_products']]);
    }

    /** Every version of a plan with its prices and who is on it (H01): the impact of a change before it is made. */
    public function planVersions(Request $request, PlanVersioning $versions, string $product, string $plan): JsonResponse
    {
        $this->api->authorize($request, 'catalog.manage', CommandScope::global());

        return $this->ok($versions->history($product, $plan));
    }

    /** Publish a new version of a plan: changed limits and/or prices for new orders; nobody's agreed version changes. Step-up and a second person, bound to the version on sale. */
    public function publishPlanVersion(Request $request, string $product, string $plan): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:250'], 'entitlements' => ['nullable', 'array'], 'limits' => ['nullable', 'array'], 'features' => ['nullable', 'array'], 'confirm_large_change' => ['nullable', 'boolean'],
            'prices' => ['nullable', 'array', 'max:12'], 'prices.*.currency' => ['required', 'string', 'size:3'], 'prices.*.period' => ['required', 'in:month,year,hour,day,once'], 'prices.*.amount' => ['required', 'numeric', 'min:0'],
            'prices.*.renewal_amount' => ['nullable', 'numeric', 'min:0'], 'prices.*.setup' => ['nullable', 'numeric', 'min:0'], 'prices.*.monthly_cap' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->catalog($request, "catalog.plan.publish:{$product}/{$plan}", ['op' => 'plan.publish', 'product_key' => $product, 'plan_key' => $plan] + $data, $data['reason'], 201);
    }

    /** Put an existing version (back) on sale — the rollback of a published version. Step-up and a second person, bound to the version on sale. */
    public function activatePlanVersion(Request $request, string $product, string $plan, int $version): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:250']]);

        return $this->catalog($request, "catalog.plan.activate:{$product}/{$plan}/{$version}", ['op' => 'plan.activate_version', 'product_key' => $product, 'plan_key' => $plan, 'version' => $version, 'reason' => $data['reason']], $data['reason']);
    }

    /** The customer panel's sidebar: category switches, order, labels, optional links — plus what the catalogue offers and who owns what. */
    public function panelNav(Request $request, PanelNavigation $nav): JsonResponse
    {
        $this->api->authorize($request, 'catalog.manage', CommandScope::global());

        return $this->ok($nav->overview());
    }

    public function setPanelNav(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['nullable', 'array'], 'categories.*' => ['array'], 'categories.*.enabled' => ['nullable', 'boolean'], 'categories.*.order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'categories.*.label' => ['nullable', 'array'], 'categories.*.label.cs' => ['nullable', 'string', 'max:40'], 'categories.*.label.en' => ['nullable', 'string', 'max:40'],
            'links' => ['nullable', 'array'], 'links.*' => ['boolean'],
        ]);

        return $this->dispatch(new CatalogCommand($this->idempotencyKey($request, 'catalog.panel_nav'), ['op' => 'panel_nav.set', 'config' => $data]), $this->api->context($request));
    }

    /** The deletion lifecycle (audit §5ab): the restore window, how long archives live and what their download costs. */
    public function lifecycle(Request $request, DeletionPolicy $policy): JsonResponse
    {
        $this->api->authorize($request, 'catalog.manage', CommandScope::global());

        return $this->ok(['lifecycle' => $policy->all()]);
    }

    /** The archive download fee is a price: step-up and a second person, bound to the whole stored policy. */
    public function setLifecycle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grace_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'retention_days' => ['nullable', 'integer', 'min:30', 'max:3650'],
            'identity_checks' => ['nullable', 'integer', 'min:5', 'max:12'],
            'download_fee_minor' => ['nullable', 'array'], 'download_fee_minor.*' => ['integer', 'min:0', 'max:10000000'],
            'reason' => self::REASON,
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        return $this->catalog($request, 'catalog.lifecycle', ['op' => 'lifecycle.set', 'config' => $data], $reason);
    }

    /**
     * Every catalogue write goes here. The permission first, so nobody without it learns anything from the check; then the
     * pre-flight check, so a change that would be refused is refused before anybody is asked to approve it; then the binding
     * to what the change was asked against (the plan version on sale, a digest of the replaced setting), which becomes part of
     * the approved payload; then the bus, where CatalogCommand says who it takes (owner decision 13: a price or plan change
     * takes a step-up and a second person). The binding is part of the idempotency key as well, and without an Idempotency-Key
     * header so is the minute (onceKey): the same body asked against another state of the catalogue, or sent again later (a code
     * deleted, recreated and deleted again), is a new command — never the replay of an old answer that would spend an approval,
     * or pull an emergency brake, for nothing.
     *
     * @param  array<string,mixed>  $payload
     */
    private function catalog(Request $request, string $keyPrefix, array $payload, ?string $reason = null, int $status = 200): JsonResponse
    {
        $this->api->authorize($request, 'catalog.manage', CommandScope::global());
        $preflight = app(CatalogPreflight::class);
        $probe = new CatalogCommand('catalog-preflight', $payload);
        $preflight->check($probe);
        $binding = $preflight->bind($probe);
        if ($reason !== null && ! array_key_exists('reason', $payload)) {
            $payload['reason'] = $reason; // the approver reads why (ApprovalService::reasonOf)
        }
        $anchor = $binding === [] ? '' : '@'.substr(hash('sha256', (string) json_encode($binding)), 0, 16);

        return $this->dispatch(new CatalogCommand($this->onceKey($request, $keyPrefix.$anchor), $payload + $binding), $this->api->context($request, null, $reason), $status);
    }

    /** @return array<string,mixed> */
    public static function option(ProductOption $o): array
    {
        return [
            'key' => $o->key, 'kind' => $o->kind, 'label' => $o->label, 'desc' => (array) data_get($o->meta, 'desc', []), 'unit' => $o->unit,
            'min' => $o->min !== null ? (float) $o->min : null, 'max' => $o->max !== null ? (float) $o->max : null, 'step' => $o->step !== null ? (float) $o->step : null, 'default' => $o->default_value !== null ? (float) $o->default_value : null,
            'price_czk' => ((int) ($o->price_per_unit_minor['CZK'] ?? 0)) / 100, 'price_eur' => ((int) ($o->price_per_unit_minor['EUR'] ?? 0)) / 100, 'choices' => $o->choices ?? [], 'entitlement' => data_get($o->meta, 'entitlement'), 'sort' => (int) $o->sort,
        ];
    }
}
