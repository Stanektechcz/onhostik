<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\BillingPeriod;
use Onhost\Domain\Billing\Models\RatedUsage;
use Onhost\Domain\Billing\Models\UsageEvent;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Rating + wallet charging with the monthly cap (blueprint §49.3):
 *   bill = min(sum(active_hours × price_hour), monthly_cap)
 * The cap is enforced per service inside the calendar-month billing period; an
 * unpayable charge is kept as unpaid rated usage and opens a dunning case.
 */
final class RatingService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
        private readonly DunningService $dunning,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @return array{rated:int, charged:int, deferred:int, capped:int} */
    public function rate(int $limit = 500, ?CommandContext $context = null): array
    {
        $context ??= CommandContext::system('usage rating');
        $stats = ['rated' => 0, 'charged' => 0, 'deferred' => 0, 'capped' => 0];
        $events = UsageEvent::query()->where('rated', false)->orderBy('period_start')->limit($limit)->get();
        foreach ($events as $event) {
            $service = Service::query()->withTrashed()->find($event->service_id);
            if ($service === null) {
                $event->forceFill(['rated' => true])->save();

                continue;
            }
            $rated = DB::transaction(function () use ($event, $service, &$stats) {
                return $this->rateEvent($event, $service, $stats);
            });
            $stats['rated']++;
            if ($rated !== null && $rated->amount_minor > 0) {
                $this->charge($rated, $service, $context) ? $stats['charged']++ : $stats['deferred']++;
            }
        }

        return $stats;
    }

    /** Retry unpaid rated usage of one organization (after a top-up). @return int charged */
    public function chargeDeferred(string $organizationId, ?CommandContext $context = null): int
    {
        $context ??= CommandContext::system('usage rating retry');
        $count = 0;
        foreach (RatedUsage::query()->where('organization_id', $organizationId)->whereNull('charged_transaction_id')->where('amount_minor', '>', 0)->orderBy('created_at')->get() as $rated) {
            $service = Service::query()->withTrashed()->find($rated->service_id);
            if ($service !== null && $this->charge($rated, $service, $context)) {
                $count++;
            }
        }

        return $count;
    }

    /** Unit price (per hour or per day) for a service: plan hourly/daily price + configured options + IPv4. */
    public function unitPrice(Service $service, string $metric, string $currency): Money
    {
        $product = Product::query()->with('options')->where('key', $service->product_key)->first();
        if ($metric === 'ipv4_hours') {
            $ipv4 = Product::query()->where('key', 'ipv4')->first();
            $plan = $ipv4?->plans()->where('state', 'active')->orderBy('sort')->first();
            $price = $plan ? Price::query()->where('plan_version_id', $plan->currentVersion()?->id)->where('currency', $currency)->where('period', 'month')->where('state', 'active')->first() : null;

            return Money::minor((int) round(($price?->amount_minor ?? 0) / 720), $currency);
        }
        $period = $metric === 'game_days' ? 'day' : 'hour';
        $divisor = $period === 'day' ? 30 : 720;
        $price = $service->plan_version_id ? Price::query()->where('plan_version_id', $service->plan_version_id)->where('currency', $currency)->where('period', $period)->where('state', 'active')->first() : null;
        $base = $price?->amount_minor;
        if ($base === null) {
            $monthly = $service->plan_version_id ? Price::query()->where('plan_version_id', $service->plan_version_id)->where('currency', $currency)->where('period', 'month')->where('state', 'active')->first() : null;
            $base = (int) ceil(($monthly?->amount_minor ?? 0) / $divisor);
        }
        $options = (array) ($service->entitlements['options'] ?? []);
        $optionMonthly = $product !== null && $options !== [] ? $this->catalog->configure($product, Money::zero($currency), $options)['net']->minor : 0;

        return Money::minor((int) $base + (int) round($optionMonthly / $divisor), $currency);
    }

    /** Monthly cap for a service (plan cap + option monthly prices), null when uncapped. */
    public function monthlyCap(Service $service, string $currency): ?Money
    {
        $price = $service->plan_version_id ? Price::query()->where('plan_version_id', $service->plan_version_id)->where('currency', $currency)->whereIn('period', ['hour', 'day'])->where('state', 'active')->orderByRaw("case when period = 'hour' then 0 else 1 end")->first() : null;
        $cap = $price?->monthlyCap();
        if ($cap === null) {
            $monthly = $service->plan_version_id ? Price::query()->where('plan_version_id', $service->plan_version_id)->where('currency', $currency)->where('period', 'month')->where('state', 'active')->first() : null;
            $cap = $monthly?->amount();
        }
        if ($cap === null) {
            return null;
        }
        $product = Product::query()->with('options')->where('key', $service->product_key)->first();
        $options = (array) ($service->entitlements['options'] ?? []);
        $optionMonthly = $product !== null && $options !== [] ? $this->catalog->configure($product, Money::zero($currency), $options)['net'] : Money::zero($currency);

        return $cap->add($optionMonthly);
    }

    private function rateEvent(UsageEvent $event, Service $service, array &$stats): ?RatedUsage
    {
        $organization = Organization::query()->findOrFail($event->organization_id);
        $currency = $organization->currency;
        $unit = $this->unitPrice($service, $event->metric, $currency);
        $amount = $unit->multiply((string) $event->quantity);
        $period = BillingPeriod::forMonth($organization->id, $currency, $event->period_start);
        $cap = $event->metric === 'ipv4_hours' ? null : $this->monthlyCap($service, $currency);
        if ($cap !== null) {
            $already = (int) RatedUsage::query()->where('service_id', $service->id)->where('billing_period_id', $period->id)->whereIn('usage_event_id', UsageEvent::query()->where('service_id', $service->id)->where('metric', '!=', 'ipv4_hours')->select('id'))->sum('amount_minor');
            if ($already + $amount->minor > $cap->minor) {
                $amount = Money::minor(max(0, $cap->minor - $already), $currency);
                $applied = (array) $period->cap_applied;
                $applied[$service->id] = ['cap' => $cap->minor, 'hit_at' => now()->toIso8601String()];
                $period->forceFill(['cap_applied' => $applied])->save();
                $stats['capped'] = ($stats['capped'] ?? 0) + 1;
            }
        }
        $rated = RatedUsage::query()->create(['usage_event_id' => $event->id, 'organization_id' => $organization->id, 'service_id' => $service->id, 'price_id' => null, 'unit_price_minor' => $unit->minor, 'amount_minor' => $amount->minor, 'currency' => $currency, 'billing_period_id' => $period->id]);
        $event->forceFill(['rated' => true])->save();
        $period->forceFill(['total_minor' => $period->total_minor + $amount->minor])->save();

        return $rated;
    }

    private function charge(RatedUsage $rated, Service $service, CommandContext $context): bool
    {
        $organization = Organization::query()->findOrFail($rated->organization_id);
        $net = $rated->amount();
        $calc = $this->tax->calculate(['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status], [['key' => 'usage', 'net' => $net, 'product_class' => 'esd']], $rated->currency, $organization->id);
        $line = $calc['lines'][0];
        try {
            $this->wallets->charge($organization, $line['total'], $service->family, "usage:{$rated->id}", $context->withScope($organization->id), 'rated_usage', $rated->id, $line['tax']);
        } catch (DomainError $e) {
            if ($e->error !== 'insufficient_funds') {
                throw $e;
            }
            $this->dunning->open($organization->id, null, $service->id, now());
            $this->outbox->publish(GenericEvent::of('usage.charge_deferred', 'service', $service->id, ['rated_usage_id' => $rated->id, 'required' => $line['total']], $organization->id));

            return false;
        }
        $rated->forceFill(['charged_transaction_id' => "ledger:usage:{$rated->id}"])->save();
        $this->dunning->resolve($organization->id, null, $service->id, $context);

        return true;
    }
}
