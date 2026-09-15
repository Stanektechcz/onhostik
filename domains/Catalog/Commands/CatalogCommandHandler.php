<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class CatalogCommandHandler implements CommandHandler
{
    public function __construct(private readonly PricingRules $rules) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof CatalogCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $by = $context->actorId;
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
            'product.state' => ['products' => $this->productState((string) $command->get('state'), (array) $command->get('products', []))], // on sale or off sale (audit §5z)
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
        $promo = PromoCode::query()->updateOrCreate(['code' => $code], [
            'kind' => $kind, 'value' => $value, 'currency' => $kind === 'fixed' ? strtoupper((string) ($in['currency'] ?? 'CZK')) : null,
            'valid_from' => isset($in['valid_from']) && $in['valid_from'] !== '' ? Carbon::parse((string) $in['valid_from']) : null,
            'valid_to' => isset($in['valid_to']) && $in['valid_to'] !== '' ? Carbon::parse((string) $in['valid_to']) : null,
            'max_uses' => isset($in['max_uses']) && $in['max_uses'] !== '' ? (int) $in['max_uses'] : null,
            'applies_to' => $applies === [] ? null : $applies, 'first_period_only' => (bool) ($in['first_period_only'] ?? true), 'state' => in_array($in['state'] ?? 'active', ['active', 'paused', 'retired'], true) ? ($in['state'] ?? 'active') : 'active',
        ]);

        return ['code' => $promo->code, 'kind' => $promo->kind, 'value' => (float) $promo->value, 'applies_to' => $promo->applies_to ?? [], 'state' => $promo->state];
    }

    /** @param array<string,mixed> $in */
    private function upsertOption(string $productKey, array $in): array
    {
        $product = $this->product($productKey);
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
        $choices = null;
        if ($kind === 'select') {
            $choices = [];
            foreach ((array) ($in['choices'] ?? []) as $choice) {
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
        }
        $num = fn (string $k) => isset($in[$k]) && $in[$k] !== '' ? (float) $in[$k] : null;
        $meta = array_filter(['cfg_key' => $key, 'desc' => array_filter(['cs' => trim((string) ($in['desc']['cs'] ?? '')), 'en' => trim((string) ($in['desc']['en'] ?? ''))]), 'entitlement' => $in['entitlement'] ?? null], fn ($v) => $v !== null && $v !== []);
        $option = ProductOption::query()->updateOrCreate(['product_id' => $product->id, 'key' => $key], [
            'kind' => $kind, 'label' => $label, 'unit' => isset($in['unit']) && $in['unit'] !== '' ? mb_substr((string) $in['unit'], 0, 24) : null,
            'min' => $kind === 'slider' ? ($num('min') ?? 0) : null, 'max' => $kind === 'slider' ? $num('max') : null, 'step' => $kind === 'slider' ? ($num('step') ?? 1) : null, 'default_value' => $kind === 'slider' ? ($num('default') ?? $num('min') ?? 0) : null,
            'price_per_unit_minor' => ['CZK' => (int) round($czk * 100), 'EUR' => (int) round($eur * 100)], 'choices' => $choices, 'meta' => $meta,
            'sort' => isset($in['sort']) && $in['sort'] !== '' ? (int) $in['sort'] : (int) (ProductOption::query()->where('product_id', $product->id)->max('sort') ?? 0) + 10,
        ]);

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
