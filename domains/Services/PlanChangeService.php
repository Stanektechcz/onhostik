<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Onhost\Domain\Billing\BillingPeriod;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Plan changes of a running service: the customer picks another plan of the same product, pays the pro-rated
 * difference for the rest of the current period (an upgrade) or nothing (a downgrade), and the paid order line moves
 * the service to the new plan version — the node resizes through the ordinary `resize` action, the subscription renews
 * at the new price from the next period. The quote line carries `config.upgrade_of` (QuoteService prices it).
 */
final class PlanChangeService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly ServiceService $services,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * The plans the service may move to, with the price per its billing period and what a change costs right now.
     *
     * @return array{current_plan:?string, period:string, fraction:float, period_end:?string, changeable:bool, plans:list<array<string,mixed>>}
     */
    public function options(Service $service, Currency|string $currency): array
    {
        $currency = $currency instanceof Currency ? $currency : Currency::fromString($currency);
        $product = Product::query()->with('plans')->where('key', $service->product_key)->first();
        $subscription = Subscription::query()->where('service_id', $service->id)->first();
        $period = in_array($subscription?->period, ['month', 'year'], true) ? $subscription->period : 'month';
        $fraction = $subscription === null ? 1.0 : self::prorationFraction($subscription);
        $currentPlan = $service->plan_version_id ? PlanVersion::query()->with('plan')->find($service->plan_version_id)?->plan?->key : null;
        $oldNet = (int) ($subscription?->amount_minor ?? 0);
        $plans = [];
        foreach ($product?->plans->where('state', 'active')->sortBy('sort') ?? [] as $plan) {
            try {
                $resolved = $this->catalog->resolve($product->key, $plan->key, $currency, $period);
            } catch (DomainError) {
                continue; // no price for this period or currency: not offered here
            }
            $net = $resolved['price']->renewalAmount();
            $diff = max(0, $net->minor - $oldNet);
            $fit = $plan->key === $currentPlan ? [] : app(PlanFit::class)->shortfalls($service, (array) $resolved['version']->entitlements);
            $plans[] = [
                'product_key' => $product->key, 'plan_key' => $plan->key, 'name' => $plan->localizedName('cs'), 'name_en' => $plan->localizedName('en'), 'current' => $plan->key === $currentPlan,
                'direction' => $plan->key === $currentPlan ? 'current' : ($net->minor > $oldNet ? 'upgrade' : 'downgrade'), 'period' => $period, 'price' => $net,
                'change_now' => Money::minor((int) round($diff * $fraction), $currency), 'entitlements' => $resolved['version']->entitlements, 'sla_class' => $plan->sla_class,
                'blockers' => $fit, 'fits' => $fit === [], // what the service already holds and this plan would not cover
            ];
        }

        // the billing periods the current plan is sold in: switching starts a new period now, priced minus the unused rest of the current one
        $periods = [];
        if ($product !== null && $currentPlan !== null) {
            $monthly = null;
            foreach (['month', 'year'] as $candidate) {
                try {
                    $net = $this->catalog->resolve($product->key, $currentPlan, $currency, $candidate)['price']->renewalAmount();
                } catch (DomainError) {
                    continue;
                }
                $monthly = $candidate === 'month' ? $net : $monthly;
                $unused = $candidate === $period ? 0 : (int) round($oldNet * $fraction);
                $periods[] = [
                    'period' => $candidate, 'current' => $candidate === $period, 'price' => $net,
                    'change_now' => Money::minor($candidate === $period ? 0 : max(0, $net->minor - $unused), $currency), 'unused_credit' => Money::minor($unused, $currency),
                    'saving_per_year' => $candidate === 'year' && $monthly !== null ? Money::minor(max(0, $monthly->minor * 12 - $net->minor), $currency) : null,
                    'period_end_after' => $candidate === $period ? $subscription?->current_period_end?->toIso8601String() : BillingPeriod::end(now(), $candidate)->toIso8601String(),
                ];
            }
        }

        return [
            'current_plan' => $currentPlan, 'period' => $period, 'fraction' => round($fraction, 4), 'period_end' => $subscription?->current_period_end?->toIso8601String(),
            'changeable' => $subscription !== null && $subscription->state === Subscription::ACTIVE && in_array($service->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true) && $product !== null,
            'plans' => $plans, 'periods' => $periods,
        ];
    }

    /**
     * Places the upgrade order on the customer's behalf (usage watch with the auto-upgrade policy on): the ordinary
     * plan-change line, paid from credit at once; `insufficient_funds` and every other refusal surface as DomainError.
     */
    public function orderUpgrade(Service $service, string $planKey, CommandContext $context, string $source = 'auto'): Order
    {
        $organization = Organization::query()->findOrFail($service->organization_id);
        $quotes = app(QuoteService::class);
        $checkout = app(CheckoutService::class);
        $quote = $quotes->quote(
            [['line_id' => 'l1', 'product_key' => $service->product_key, 'plan_key' => $planKey, 'qty' => 1, 'config' => ['upgrade_of' => $service->id]]],
            (string) $organization->currency, ['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], 1, null, $organization,
        );
        $versions = $quotes->currentTermsVersions();
        $consents = [];
        foreach ($checkout->requiredDocuments($quote, $organization) as $document) {
            $consents[$document] = ['version' => $versions[$document] ?? null, 'policy' => 'auto_upgrade']; // the customer consented when switching the policy on
        }

        return $checkout->placeOrder($quote, $organization, null, $consents, ['mode' => 'wallet'], "auto-upgrade:{$service->id}:{$planKey}:".now()->format('Ymd'), $context->withScope($organization->id, $service->project_id), $source)['order'];
    }

    /** The share of the current period still ahead (0 … 1): what an upgrade is charged for now. */
    public static function prorationFraction(Subscription $subscription): float
    {
        $start = $subscription->current_period_start?->getTimestamp() ?? time();
        $end = $subscription->current_period_end?->getTimestamp() ?? $start;
        $total = max(1, $end - $start);
        $remaining = max(0, min($total, $end - time()));

        return round($remaining / $total, 6);
    }

    /** Fulfils a paid plan-change line: the node resizes, the service and its subscription move to the new plan. */
    public function apply(OrderItem $item, Order $order, CommandContext $context): Service
    {
        $config = (array) $item->config;
        $service = Service::query()->where('organization_id', $order->organization_id)->find((string) ($config['upgrade_of'] ?? ''));
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        if ($item->service_id === $service->id && $item->state === 'active') {
            return $service; // redelivered event
        }
        $version = $item->plan_version_id ? PlanVersion::query()->with('plan')->find($item->plan_version_id) : null;
        if ($version === null) {
            throw new DomainError('plan_change_version_missing', 'The order line carries no plan version.', 422);
        }
        $product = Product::query()->where('key', $item->product_key)->firstOrFail();
        if ($service->product_key !== $product->key) {
            throw new DomainError('plan_change_product_mismatch', 'The plan belongs to a different product than the service.', 422);
        }
        $entitlements = $this->services->entitlementsFor($version, (array) ($config['options'] ?? []), $product);
        app(PlanFit::class)->assertFits($service, $entitlements); // the service may have grown between the order and its payment
        $from = (string) (data_get($config, 'plan_change.from_plan') ?? '');
        $to = (string) ($version->plan?->key ?? '');
        $periodChange = (bool) data_get($config, 'plan_change.period_change', false);

        // the node first: a busy or non-active service refuses the resize and the line fails without touching billing (a period change on the same plan touches no node)
        if (! ($periodChange && $from === $to)) {
            $this->services->requestAction($service, 'resize', $context, "plan-change:{$item->id}", ['entitlements' => $entitlements, 'limits' => (array) ($version->limits ?? []), 'reason' => "plan change {$from} → {$to}"]);
        }

        $service->forceFill(['plan_version_id' => $version->id, 'sla_class' => (string) ($version->plan?->sla_class ?? $service->sla_class)])->save();
        $subscription = $this->subscriptions->changePlan($service, $item, $context);
        $item->forceFill(['service_id' => $service->id, 'state' => 'active'])->save();
        $this->audit->record($context->withScope($service->organization_id, $service->project_id), 'service.plan_change', 'succeeded', ['from' => $from, 'to' => $to, 'period' => $subscription->period, 'period_change' => $periodChange, 'order_item_id' => $item->id, 'entitlements' => $entitlements], 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.plan_changed', 'service', $service->id, [
            'product_key' => $product->key, 'from_plan' => $from, 'to_plan' => $to, 'plan_name' => $version->plan?->localizedName('cs'), 'hostname' => $service->hostname, 'label' => $service->label, 'order_item_id' => $item->id,
            'period' => $subscription->period, 'period_change' => $periodChange, 'from_period' => (string) (data_get($config, 'plan_change.from_period') ?? ''), 'current_period_end' => $subscription->current_period_end?->toIso8601String(),
        ], $service->organization_id));

        return $service;
    }
}
