<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Limits\LimitRaisePolicy;
use Onhost\Domain\Services\Limits\LimitRaises;
use Onhost\Domain\Services\Limits\LimitRaiseWaiver;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/**
 * The quote line of a raise (owner decision 8): one number of one running, billed service, priced at the parent product's own
 * option price per unit and per period of the service's subscription — no commitment, promo, loyalty or regional adjustment
 * (the configurator does not adjust options either, and the owner's rule is "the price is the option price").
 *
 * Nothing but `config.limit_raise.{service_id, metric, units}` is read from the cart: the parent, the delta, the unit price, the
 * period and the entitlements are the server's. A price of zero exists only with a `LimitRaiseWaiver`, which no cart can send.
 */
final class LimitRaiseLine
{
    /**
     * @param  array<string,mixed>  $item  the cart line
     * @param  array<string,int>  $claimed  service:metric → units other lines of the same cart already raise
     * @return array<string,mixed> a quote line (money as `Money`)
     */
    public function build(?Organization $organization, array $item, Currency $currency, string $lineId, array &$claimed, ?LimitRaiseWaiver $waiver = null, string $locale = 'cs'): array
    {
        $raise = (array) data_get($item, 'config.limit_raise', []);
        if ($organization === null) {
            throw new DomainError('limit_raise_requires_account', 'Navýšení limitu se objednává k vlastní službě po přihlášení.', 422, ['field' => 'items']);
        }
        $service = Service::query()->where('organization_id', $organization->id)->find((string) ($raise['service_id'] ?? ''));
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        if (! in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('limit_raise_state', 'Navýšit lze limit jen běžící služby.', 409, ['state' => $service->state]);
        }
        $subscription = Subscription::query()->where('service_id', $service->id)->where('state', Subscription::ACTIVE)->first();
        if ($subscription === null) {
            throw new DomainError('limit_raise_no_subscription', 'Služba nemá aktivní předplatné, ke kterému by se navýšení účtovalo.', 409);
        }
        $units = filter_var($raise['units'] ?? null, FILTER_VALIDATE_INT);
        $maxUnits = max(1, (int) config('onhost.limit_raise.max_units', 100));
        if ($units === false || $units < 1 || $units > $maxUnits) {
            throw new DomainError('limit_raise_units', "Navýšení je 1 až {$maxUnits} jednotek.", 422, ['field' => 'units', 'max' => $maxUnits]);
        }
        $metric = (string) ($raise['metric'] ?? '');
        $spec = LimitRaisePolicy::raisable($service, $metric);
        if ($units % $spec['step'] !== 0) {
            throw new DomainError('limit_raise_step', "Tento limit se navyšuje po {$spec['step']} {$spec['unit']}.", 422, ['field' => 'units', 'step' => $spec['step']]);
        }
        $delta = $units * $spec['scale'];
        $claim = $service->id.':'.$metric;
        $current = (int) (((array) $service->entitlements)[$metric] ?? 0) + ($claimed[$claim] ?? 0);
        if ($spec['ceiling'] !== null && $current + $delta > $spec['ceiling']) {
            throw new DomainError('limit_raise_above_max', 'Tolik produkt služby neprodává: nejvýš '.$spec['ceiling'].' (teď '.$current.').', 422, ['field' => 'units', 'max' => $spec['ceiling'], 'current' => $current]);
        }
        $unitMinor = (int) ($spec['unit_price_minor'][$currency->value] ?? 0);
        if ($unitMinor <= 0) {
            throw new DomainError('limit_raise_unpriced', "Produkt služby nemá pro {$metric} cenu v {$currency->value}.", 422, ['field' => 'metric', 'metric' => $metric]);
        }
        $claimed[$claim] = ($claimed[$claim] ?? 0) + $delta;

        // the raise renews with the service's period: a service paid per year pays twelve months of the option at once
        $period = in_array($subscription->period, ['month', 'year'], true) ? (string) $subscription->period : 'month';
        $months = $period === 'year' ? 12 : 1;
        $list = Money::minor($unitMinor * $units * $months, $currency);
        $discount = $waiver !== null ? $list : Money::zero($currency); // an approved waiver shows what was given away
        $label = (string) ($spec['label'][$locale] ?? $spec['label']['cs'] ?? $metric);
        $who = (string) ($service->label ?: ($service->hostname ?: $service->name));

        return [
            'line_id' => $lineId, 'sku' => 'limit-raise-'.$metric, 'product_key' => LimitRaises::PRODUCT, 'plan_key' => null, 'plan_version_id' => null, 'price_id' => null,
            'name' => mb_substr("Navýšení limitu: +{$units} {$spec['unit']} ({$label}) · {$who}", 0, 190),
            'qty' => 1, 'period' => $period, 'unit_net' => $list, 'discount' => $discount, 'net' => $list->subtract($discount), 'renewal_net' => $waiver !== null ? Money::zero($currency) : $list,
            'product_class' => 'esd', 'family' => 'addon',
            'config' => ['line_id' => $lineId, 'parent_service_id' => $service->id, 'periods_billed' => 1, 'limit_raise' => array_filter([
                'service_id' => $service->id, 'metric' => $metric, 'units' => $units, 'delta' => $delta, 'option_key' => $spec['option_key'], 'label' => $label, 'unit_price_minor' => $unitMinor,
                'months' => $months, 'list_net_minor' => $list->minor, 'waived' => $waiver?->toArray(),
            ], fn ($value) => $value !== null)],
            'entitlements' => ['limit_raise' => ['metric' => $metric, 'delta' => $delta]],
        ];
    }
}
