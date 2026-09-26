<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Scheduling\PlacementRules;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * Plan placements: which panel (provider instance) and which server (node) a product/plan is provisioned on
 * (Nastavení systému → Umístění tarifů). Resolution picks the most specific active placement for the plan and region;
 * the executor of the service becomes the placement's provider, so one product can sell plans on different panels.
 */
final class PlacementService
{
    /** Executor families: which providers may serve a product executor. */
    public const COMPATIBLE = [
        'ispconfig' => ['ispconfig', 'aapanel'], 'aapanel' => ['aapanel', 'ispconfig'], 'proxmox' => ['proxmox'], 'pterodactyl' => ['pterodactyl'], 'kubernetes' => ['kubernetes'],
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * A one-order placement on a named instance (staff pin, audit §5z): unsaved, only when the instance is usable and compatible with the product
     * — and with what the plan sells (`PlacementRules`: dedicated PHP workers never go to a node-wide pool).
     *
     * @param  array<string,mixed>  $entitlements  the plan's entitlements
     */
    public function pinned(Product $product, string $instanceKey, array $entitlements = []): PlanPlacement
    {
        $instance = ProviderInstance::query()->where('key', $instanceKey)->orWhere('id', $instanceKey)->first();
        if ($instance === null || ! $instance->isUsable()) {
            throw new DomainError('placement_instance_unusable', "The instance {$instanceKey} does not exist or is not active.", 422, ['field' => 'placement_instance']);
        }
        $allowed = self::COMPATIBLE[(string) $product->executor] ?? [(string) $product->executor];
        if (! in_array($instance->provider, $allowed, true)) {
            throw new DomainError('placement_incompatible', "Product {$product->key} ({$product->executor}) cannot run on a {$instance->provider} instance.", 422, ['field' => 'placement_instance']);
        }
        self::assertPlanAllows($product, $instance, $entitlements, 'placement_instance');
        $placement = new PlanPlacement(['product_key' => $product->key, 'provider_instance_id' => $instance->id, 'priority' => 0, 'state' => 'active', 'note' => 'pinned for one order']);
        $placement->setRelation('providerInstance', $instance);
        $placement->setRelation('node', null);

        return $placement;
    }

    /**
     * The placement that applies to a plan in a region, or null when scheduling should fall back to roles. A placement on a panel the plan may not
     * run on (`PlacementRules`, e.g. a product-wide aaPanel placement for a plan with dedicated PHP workers) does not apply to it.
     *
     * @param  array<string,mixed>  $entitlements  the plan's entitlements
     */
    public function resolve(string $productKey, ?string $planKey, ?string $regionCode, array $entitlements = []): ?PlanPlacement
    {
        $executor = PlacementRules::dedicatedPhp($entitlements) ? (string) Product::query()->where('key', $productKey)->value('executor') : '';

        return PlanPlacement::query()->with(['providerInstance', 'node'])->where('product_key', $productKey)->where('state', 'active')
            ->where(fn ($q) => $q->whereNull('plan_key')->when($planKey !== null, fn ($q) => $q->orWhere('plan_key', $planKey)))
            ->where(fn ($q) => $q->whereNull('region_code')->when($regionCode !== null, fn ($q) => $q->orWhere('region_code', $regionCode)))
            ->get()
            ->filter(fn (PlanPlacement $p) => $p->providerInstance !== null && $p->providerInstance->isUsable() && ($p->node_id === null || ($p->node !== null && $p->node->isSchedulable())))
            ->filter(fn (PlanPlacement $p) => $executor === '' || PlacementRules::allows($executor, $p->providerInstance instanceof ProviderInstance ? (string) $p->providerInstance->provider : '', $entitlements))
            ->sortBy([fn ($a, $b) => $b->specificity() <=> $a->specificity(), fn ($a, $b) => $a->priority <=> $b->priority])
            ->first();
    }

    /** @param array{product_key:string, plan_key?:?string, region_code?:?string, provider_instance_key:string, node_id?:?string, priority?:int, state?:string, note?:?string} $input */
    public function upsert(array $input, CommandContext $context): PlanPlacement
    {
        $product = Product::query()->where('key', (string) ($input['product_key'] ?? ''))->first();
        if ($product === null) {
            throw new DomainError('placement_product_unknown', 'Unknown product.', 422, ['field' => 'product_key']);
        }
        $planKey = isset($input['plan_key']) && $input['plan_key'] !== '' ? (string) $input['plan_key'] : null;
        if ($planKey !== null && ! $product->plans()->where('key', $planKey)->exists()) {
            throw new DomainError('placement_plan_unknown', "Product {$product->key} has no plan {$planKey}.", 422, ['field' => 'plan_key']);
        }
        $instance = ProviderInstance::query()->where('key', (string) ($input['provider_instance_key'] ?? ''))->orWhere('id', (string) ($input['provider_instance_key'] ?? ''))->first();
        if ($instance === null) {
            throw new DomainError('placement_instance_unknown', 'Unknown provider instance.', 422, ['field' => 'provider_instance_key']);
        }
        $allowed = self::COMPATIBLE[(string) $product->executor] ?? [(string) $product->executor];
        if (! in_array($instance->provider, $allowed, true)) {
            throw new DomainError('placement_incompatible', "Product {$product->key} ({$product->executor}) cannot run on a {$instance->provider} instance; allowed: ".implode(', ', $allowed).'.', 422, ['field' => 'provider_instance_key']);
        }
        if ($planKey !== null) {
            self::assertPlanAllows($product, $instance, (array) $product->plans()->where('key', $planKey)->first()?->currentVersion()?->entitlements, 'provider_instance_key');
        }
        $node = null;
        if (! empty($input['node_id'])) {
            $node = Node::query()->where('provider_instance_id', $instance->id)->where(fn ($q) => $q->where('id', (string) $input['node_id'])->orWhere('name', (string) $input['node_id']))->first();
            if ($node === null) {
                throw new DomainError('placement_node_unknown', 'The node does not belong to that instance.', 422, ['field' => 'node_id']);
            }
        }
        $regionCode = isset($input['region_code']) && $input['region_code'] !== '' ? (string) $input['region_code'] : null;
        $placement = PlanPlacement::query()->updateOrCreate(
            ['product_key' => $product->key, 'plan_key' => $planKey, 'region_code' => $regionCode],
            ['provider_instance_id' => $instance->id, 'node_id' => $node?->id, 'priority' => (int) ($input['priority'] ?? 100), 'state' => in_array($input['state'] ?? 'active', ['active', 'disabled'], true) ? ($input['state'] ?? 'active') : 'active', 'note' => isset($input['note']) ? substr((string) $input['note'], 0, 250) : null, 'created_by' => $context->actorId],
        );
        $this->audit->record($context, 'placement.upsert', 'succeeded', ['product' => $product->key, 'plan' => $planKey, 'region' => $regionCode, 'instance' => $instance->key, 'node' => $node?->name], 'plan_placement', $placement->id);

        return $placement->load(['providerInstance', 'node']);
    }

    /** @param array<string,mixed> $entitlements */
    private static function assertPlanAllows(Product $product, ProviderInstance $instance, array $entitlements, string $field): void
    {
        if (! PlacementRules::allows((string) $product->executor, (string) $instance->provider, $entitlements)) {
            throw new DomainError('placement_requires_dedicated_php', 'This plan sells dedicated PHP workers; it can only run on a panel that gives every site a PHP pool of its own.', 422, ['field' => $field]);
        }
    }

    public function delete(string $id, CommandContext $context): void
    {
        $placement = PlanPlacement::query()->find($id);
        if ($placement === null) {
            throw DomainError::notFound('placement');
        }
        $placement->delete();
        $this->audit->record($context, 'placement.delete', 'succeeded', ['product' => $placement->product_key, 'plan' => $placement->plan_key, 'region' => $placement->region_code], 'plan_placement', $placement->id);
    }

    /** Catalogue + placements + candidate instances/nodes for the settings page. @return array<string,mixed> */
    public function overview(): array
    {
        $instances = ProviderInstance::query()->platform()->orderBy('provider')->orderBy('key')->get();
        $nodes = Node::query()->orderBy('name')->get()->groupBy('provider_instance_id');
        $placements = PlanPlacement::query()->with(['providerInstance', 'node'])->orderBy('product_key')->orderBy('plan_key')->get();
        $products = Product::query()->where('state', 'active')->orderBy('sort')->with('plans')->get()->map(fn (Product $p) => [
            'key' => $p->key, 'name' => $p->localizedName('cs'), 'family' => $p->family, 'executor' => $p->executor, 'compatible' => self::COMPATIBLE[(string) $p->executor] ?? [(string) $p->executor],
            'plans' => $p->plans->where('state', 'active')->sortBy('sort')->values()->map(fn ($pl) => ['key' => $pl->key, 'name' => $pl->localizedName('cs')])->all(),
        ])->values()->all();

        return [
            'products' => $products,
            'placements' => $placements->map(fn (PlanPlacement $p) => self::present($p))->all(),
            'instances' => $instances->map(fn (ProviderInstance $i) => ['key' => $i->key, 'provider' => $i->provider, 'name' => $i->name, 'region' => $i->region_code, 'state' => $i->state, 'usable' => $i->isUsable(), 'nodes' => ($nodes->get($i->id) ?? collect())->map(fn (Node $n) => ['id' => $n->id, 'name' => $n->name, 'role' => $n->role, 'region' => $n->region_code, 'state' => $n->state])->values()->all()])->all(),
        ];
    }

    /** @return array<string,mixed> */
    public static function present(PlanPlacement $p): array
    {
        return ['id' => $p->id, 'product_key' => $p->product_key, 'plan_key' => $p->plan_key, 'region_code' => $p->region_code, 'provider_instance_key' => $p->providerInstance?->key, 'provider' => $p->providerInstance?->provider, 'node_id' => $p->node_id, 'node_name' => $p->node?->name, 'priority' => $p->priority, 'state' => $p->state, 'note' => $p->note];
    }
}
