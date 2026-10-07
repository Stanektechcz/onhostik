<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PenpotOffer;
use Onhost\Domain\Orders\Models\Quote;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Penpot\PenpotParents;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * The quote line of a Penpot (owner decision H-R7): ordered for one service — a web hosting line of the same cart
 * (`config.parent_line_id`) or a running service of the customer (`config.parent_service_id`) — and priced by that service's
 * tariff (PenpotOffer): included at 0 in a web hosting tariff that includes it, the tariff's own price, or the catalogue
 * add-on price next to any other service. No commitment, promo, loyalty or regional adjustment: the price is the configured
 * price (owner's rule "no implicit discounts"), and an included Penpot shows as included, not as a discount.
 *
 * Nothing but the parent reference and the period is read from the cart: the parent's tariff, the price, the entitlements and
 * the executor are the server's.
 */
final class PenpotLine
{
    public function __construct(private readonly CatalogService $catalog, private readonly PenpotOffer $offer) {}

    /**
     * @param  array<string,mixed>  $item  the cart line
     * @param  array<string, array<string,mixed>>  $cartLines  line id → cart line of this cart (after quantities were expanded)
     * @param  list<string>  $claimed  parents this cart already gives a Penpot
     * @return array<string,mixed> a quote line (money as `Money`)
     */
    public function build(?Organization $organization, array $item, array $cartLines, Currency $currency, string $lineId, string $defaultPeriod, array &$claimed, string $locale = 'cs'): array
    {
        $config = (array) ($item['config'] ?? []);
        $parentLine = trim((string) ($config['parent_line_id'] ?? ''));
        $parentServiceId = trim((string) ($config['parent_service_id'] ?? ''));
        if (($parentLine === '') === ($parentServiceId === '')) {
            throw new DomainError('penpot_parent_required', 'Penpot se objednává ke službě: v ceně webhostingu, k ostatním službám jako doplněk. Uveďte řádek košíku (parent_line_id) nebo svou službu (parent_service_id).', 422, ['field' => 'items']);
        }
        if ($parentLine !== '') {
            $parent = $cartLines[$parentLine] ?? throw new DomainError('addon_parent_missing', 'The add-on refers to a cart line that does not exist.', 422, ['field' => 'items']);
            $parentConfig = (array) ($parent['config'] ?? []);
            $parentProduct = Product::query()->where('key', (string) ($parent['product_key'] ?? ''))->first();
            if ($parentProduct === null || ! empty($parentConfig['upgrade_of']) || ! empty($parentConfig['parent_line_id']) || in_array($parentProduct->family, ['addon', 'domain', PenpotOffer::PRODUCT], true)) {
                throw new DomainError('penpot_parent_invalid', 'Penpot se objednává k řádku se službou (webhosting, server …), ne k doplňku, doméně ani ke změně tarifu.', 422, ['field' => 'items', 'parent' => $parentLine]);
            }
            $parentPlan = (string) ($parent['plan_key'] ?? '');
            $period = (string) ($item['period'] ?? $parent['period'] ?? $defaultPeriod);
            $claim = 'line:'.$parentLine;
            $parentName = $parentProduct->localizedName($locale);
            $reference = ['parent_line_id' => $parentLine];
        } else {
            if ($organization === null) {
                throw new DomainError('penpot_requires_account', 'Penpot ke své službě objednáte po přihlášení.', 422, ['field' => 'items']);
            }
            $service = PenpotParents::parent($organization->id, $parentServiceId); // the customer's own running service, or 404/422/409
            if (PenpotParents::taken($service)) {
                throw new DomainError('penpot_exists', 'Tato služba už Penpot má (nebo je objednaný). Jedna služba má jeden Penpot.', 409, ['field' => 'items', 'service_id' => $service->id]);
            }
            $parentProduct = Product::query()->where('key', (string) $service->product_key)->first() ?? throw DomainError::notFound('product');
            $parentPlan = (string) Plan::query()->whereKey(PlanVersion::query()->whereKey((string) $service->plan_version_id)->value('plan_id'))->value('key');
            $billed = Subscription::query()->where('service_id', $service->id)->where('state', Subscription::ACTIVE)->value('period');
            $period = (string) ($item['period'] ?? (in_array($billed, ['month', 'year'], true) ? $billed : $defaultPeriod));
            $claim = 'service:'.$service->id;
            $parentName = (string) ($service->label ?: ($service->hostname ?: $service->name));
            $reference = ['parent_service_id' => $service->id];
        }
        if (! in_array($period, ['month', 'year'], true)) {
            throw new DomainError('penpot_period_invalid', 'Penpot se platí měsíčně nebo ročně.', 422, ['field' => 'period']);
        }
        if (in_array($claim, $claimed, true)) {
            throw new DomainError('penpot_exists', 'Jedna služba má jeden Penpot; v košíku je k ní dvakrát.', 409, ['field' => 'items']);
        }
        $resolved = $this->catalog->resolve(PenpotOffer::PRODUCT, PenpotOffer::PLAN, $currency, $period); // not on sale → product_not_sellable
        if (! PenpotParents::deliverable($organization?->id, (array) $resolved['version']->entitlements)) {
            throw PenpotParents::unavailable(); // before anybody pays (owner: never sell what cannot be delivered)
        }
        $price = $this->offer->priceFor($parentProduct, $parentPlan, $currency, $period, $resolved['price']);
        $claimed[] = $claim;

        $first = Money::minor($price['first_minor'], $currency);
        $renewal = Money::minor($price['renewal_minor'], $currency);
        $name = $resolved['product']->localizedName($locale).' '.$resolved['plan']->localizedName($locale).' · '.$parentName.($price['included'] ? ' (v ceně tarifu)' : '');

        return [
            'line_id' => $lineId, 'sku' => PenpotOffer::PRODUCT.'-'.PenpotOffer::PLAN.($price['included'] ? '-included' : ''), 'product_key' => PenpotOffer::PRODUCT, 'plan_key' => PenpotOffer::PLAN,
            'plan_version_id' => $resolved['version']->id, 'price_id' => $resolved['price']->id,
            'name' => mb_substr($name, 0, 190),
            'qty' => 1, 'period' => $period, 'unit_net' => $first, 'discount' => Money::zero($currency), 'net' => $first, 'renewal_net' => $renewal,
            'product_class' => 'esd', 'family' => (string) $resolved['product']->family,
            'config' => $reference + array_filter(['label' => is_string($config['label'] ?? null) && trim($config['label']) !== '' ? mb_substr(trim($config['label']), 0, 60) : null]) + [
                'line_id' => $lineId, 'periods_billed' => 1, 'executor' => $resolved['product']->executor, 'sla_class' => $resolved['plan']->sla_class,
                'penpot' => ['included' => $price['included'], 'source' => $price['source'], 'parent_product' => $parentProduct->key, 'parent_plan' => $parentPlan, 'price_minor' => $price['first_minor']],
            ],
            'entitlements' => $resolved['version']->entitlements,
        ];
    }

    /**
     * The checkout's second look at a quote's Penpot lines: a quote lives two hours, and in between the last node may have gone
     * (or filled up), the parent may have ended, or another order may have given the parent its Penpot.
     */
    public static function assertStillOrderable(Quote $quote, Organization $organization): void
    {
        $lines = array_values(array_filter((array) $quote->lines, fn ($line) => is_array($line) && ($line['product_key'] ?? null) === PenpotOffer::PRODUCT));
        if ($lines === []) {
            return;
        }
        if (! PenpotParents::deliverable($organization->id, (array) ($lines[0]['entitlements'] ?? []))) {
            throw PenpotParents::unavailable();
        }
        foreach ($lines as $line) {
            $serviceId = (string) data_get($line, 'config.parent_service_id', '');
            if ($serviceId === '') {
                continue; // a parent ordered on the same order: it does not exist yet, and the cart allowed one Penpot for it
            }
            if (PenpotParents::taken(PenpotParents::parent($organization->id, $serviceId))) {
                throw new DomainError('penpot_exists', 'Tato služba už Penpot má (nebo je objednaný). Jedna služba má jeden Penpot.', 409, ['field' => 'items', 'service_id' => $serviceId]);
            }
        }
    }
}
