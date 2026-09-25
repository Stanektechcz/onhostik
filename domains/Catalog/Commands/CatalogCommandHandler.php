<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Commands;

use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\CatalogPreflight;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Catalog\PlanVersioning;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class CatalogCommandHandler implements CommandHandler
{
    public function __construct(private readonly PricingRules $rules, private readonly CatalogPreflight $preflight) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof CatalogCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $by = $context->actorId;
        // an approval given against one value of a setting is not spent on another (a plan checks its version under its own lock)
        $this->preflight->assertUnchanged($command);
        $result = match ($command->op()) {
            'pricing.commit_discounts.set' => ['commit_discounts' => $this->rules->setCommitDiscounts((array) $command->get('config', []), $by)],
            'pricing.domain_discount.set' => ['tld' => strtolower(ltrim((string) $command->get('tld'), '.')), 'domain_discount' => $this->rules->setDomainDiscount((string) $command->get('tld'), (array) $command->get('discount', []), $by)],
            'pricing.domain_discount.delete' => (function () use ($command, $by) {
                $this->rules->deleteDomainDiscount((string) $command->get('tld'), $by);

                return ['deleted' => true];
            })(),
            'pricing.regions.set' => ['regions' => $this->rules->setRegions((array) $command->get('regions', []), $by)],
            'pricing.addon_products.set' => ['product_key' => (string) $command->get('product_key'), 'addon_products' => $this->rules->setAddonProducts((string) $command->get('product_key'), (array) $command->get('addon_products', []), $by)],
            'promo.upsert' => ['promo' => $this->upsertPromo((array) $command->get('promo', []))],
            'promo.delete' => (function () use ($command) {
                $deleted = PromoCode::query()->where('code', strtoupper((string) $command->get('code')))->delete();

                return ['deleted' => $deleted > 0];
            })(),
            'option.upsert' => ['option' => $this->upsertOption((string) $command->get('product_key'), (array) $command->get('option', []))],
            // versions of a plan (H01): a change is a new version, those who bought keep theirs
            'plan.publish' => ['version' => app(PlanVersioning::class)->publish((string) $command->get('product_key'), (string) $command->get('plan_key'), $command->payload, $context)->version, 'plan' => app(PlanVersioning::class)->history((string) $command->get('product_key'), (string) $command->get('plan_key'))],
            'plan.activate_version' => (function () use ($command, $context) {
                app(PlanVersioning::class)->activate((string) $command->get('product_key'), (string) $command->get('plan_key'), (int) $command->get('version'), $command->payload, $context);

                return ['plan' => app(PlanVersioning::class)->history((string) $command->get('product_key'), (string) $command->get('plan_key'))];
            })(),
            'product.state' => ['products' => $this->productState((string) $command->get('state'), (array) $command->get('products', []))], // on sale or off sale (audit §5z)
            // the product's public description (a catalogue revision withdraws a promise from it, CatalogRevisions)
            'product.describe' => (function () use ($command) {
                ['product' => $product, 'description' => $description] = CatalogPreflight::description((string) $command->get('product_key'), (array) $command->get('description', []));
                $product->forceFill(['description' => $description])->save();

                return ['product_key' => $product->key, 'description' => $description];
            })(),
            // the deletion lifecycle: how long a cancelled service can come back, how long the archive lives, what its download costs (audit §5ab)
            'lifecycle.set' => ['lifecycle' => app(DeletionPolicy::class)->set((array) $command->get('config', []), $by)],
            // the customer panel's sidebar: category switches, order and labels (domains/Catalog/PanelNavigation.php)
            'panel_nav.set' => ['panel_nav' => app(PanelNavigation::class)->save((array) $command->get('config', []), $by)],
            'option.delete' => (function () use ($command) {
                $product = $this->product((string) $command->get('product_key'));
                $deleted = ProductOption::query()->where('product_id', $product->id)->where('key', (string) $command->get('key'))->delete();

                return ['deleted' => $deleted > 0];
            })(),
            default => throw new DomainError('op_unknown', 'Unknown catalog operation.', 422, ['field' => 'op']),
        };
        Cache::forget('surfaces:onhost-data.js'); // the public web reflects the change on the next request

        return $result;
    }

    /** @param array<string,mixed> $in */
    private function upsertPromo(array $in): array
    {
        ['code' => $code, 'attributes' => $attributes] = CatalogPreflight::promo($in);
        $promo = PromoCode::query()->updateOrCreate(['code' => $code], $attributes);

        return ['code' => $promo->code, 'kind' => $promo->kind, 'value' => (float) $promo->value, 'applies_to' => $promo->applies_to ?? [], 'state' => $promo->state];
    }

    /** @param array<string,mixed> $in */
    private function upsertOption(string $productKey, array $in): array
    {
        ['product' => $product, 'key' => $key, 'attributes' => $attributes] = CatalogPreflight::option($productKey, $in);
        $option = ProductOption::query()->updateOrCreate(['product_id' => $product->id, 'key' => $key], $attributes);

        return ['product_key' => $product->key, 'key' => $option->key, 'kind' => $option->kind, 'label' => $option->label, 'price_per_unit_minor' => $option->price_per_unit_minor];
    }

    private function product(string $key): Product
    {
        $product = Product::query()->where('key', $key)->first();
        if ($product === null) {
            throw DomainError::notFound("Product {$key}");
        }

        return $product;
    }

    /** @param list<string> $keys @return array<string,string> product key → new state */
    private function productState(string $state, array $keys): array
    {
        if (! in_array($state, ['active', 'draft'], true)) {
            throw new DomainError('state_invalid', 'State must be active or draft.', 422, ['field' => 'state']);
        }
        $out = [];
        foreach ($keys as $key) {
            $product = $this->product((string) $key);
            $product->forceFill(['state' => $state])->save();
            $out[$product->key] = $state;
        }
        Cache::forget('surfaces:onhost-data.js');

        return $out;
    }
}
