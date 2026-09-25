<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Carbon\CarbonInterface;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Errors\DomainError;

/**
 * A plan-change line is priced for one moment: the plan the service ran, its billing period, the price it renewed at and
 * the share of the period still ahead are frozen into `config.plan_change` when the order is placed. A credit order that
 * waits for the owner's approval (TASK-0021) may wait days; by then the service may run another plan (the owner or the usage
 * watch upgraded it, or a second held upgrade was approved first), its period may have renewed, or most of the period the
 * member wanted to pay for has passed. Paying the frozen line then charges the difference a second time or for time that is
 * gone (review round 2 of TASK-0021, billing lens).
 *
 * The approval asks this before any credit is reserved; a line that no longer matches refuses the whole decision with 409
 * `order_approval_stale` (nothing reserved, no document) and the member orders again at today's price.
 */
final class HeldPlanChangeCheck
{
    /** How far the share of the period still ahead may have moved since the order was placed, in seconds of that period. */
    public const FRACTION_TOLERANCE_SECONDS = 86400;

    public function assertStillCurrent(Order $order): void
    {
        $placedAt = self::moment($order->getAttribute('placed_at')) ?? self::moment($order->getAttribute('created_at'));
        foreach (OrderItem::query()->where('order_id', $order->id)->get() as $item) {
            $serviceId = data_get($item->getAttribute('config'), 'upgrade_of');
            if (! is_string($serviceId) || $serviceId === '') {
                continue;
            }
            $change = data_get($item->getAttribute('config'), 'plan_change');
            $reason = $this->staleReason($order, $serviceId, is_array($change) ? $change : [], $placedAt);
            if ($reason !== null) {
                throw new DomainError(
                    'order_approval_stale',
                    "Objednávka změny tarifu už neodpovídá stavu služby ({$reason[1]}). Nic nebylo strženo. Zamítněte ji; kdo ji zadal, ať ji objedná znovu za aktuální cenu.",
                    409,
                    ['reason' => $reason[0], 'service_id' => $serviceId, 'order_item_id' => $item->id],
                );
            }
        }
    }

    /**
     * @param  array<string,mixed>  $change  the line's frozen `config.plan_change`
     * @return array{0:string,1:string}|null [code, Czech label] of the first mismatch, null when the line still holds
     */
    private function staleReason(Order $order, string $serviceId, array $change, ?CarbonInterface $placedAt): ?array
    {
        $service = Service::query()->where('organization_id', $order->organization_id)->find($serviceId);
        $subscription = $service !== null ? Subscription::query()->where('service_id', $service->id)->first() : null;
        if ($service === null || $subscription === null || $subscription->state !== Subscription::ACTIVE) {
            return ['service_not_active', 'služba už nemá aktivní předplatné'];
        }
        if ($this->planKeyOf($service) !== (string) ($change['from_plan'] ?? '')) {
            return ['plan_changed', 'služba mezitím změnila tarif'];
        }
        $period = in_array($subscription->period, ['month', 'year'], true) ? $subscription->period : 'month';
        if ($period !== (string) ($change['from_period'] ?? '') || (int) $subscription->amount_minor !== (int) ($change['old_net_minor'] ?? -1)) {
            return ['period_changed', 'změnilo se fakturační období nebo cena předplatného'];
        }
        $start = self::moment($subscription->getAttribute('current_period_start'));
        if ($placedAt !== null && $start !== null && $start->greaterThan($placedAt)) {
            return ['period_renewed', 'předplatné se mezitím obnovilo na nové období'];
        }
        if ($this->otherChangePaidSince($order, $service->id, $placedAt)) {
            return ['competing_change', 'mezitím byla zaplacena jiná změna tarifu této služby'];
        }
        if ($this->prorationDrift($subscription, (float) ($change['fraction'] ?? 0)) > self::FRACTION_TOLERANCE_SECONDS) {
            return ['proration_expired', 'od objednání uplynula podstatná část období, cena už neplatí'];
        }

        return null;
    }

    /** The plan key the service runs now ('' when it has none), as QuoteService::planChange() froze it into `from_plan`. */
    private function planKeyOf(Service $service): string
    {
        $planId = $service->plan_version_id ? PlanVersion::query()->whereKey($service->plan_version_id)->value('plan_id') : null;
        $key = $planId !== null ? Plan::query()->whereKey($planId)->value('key') : null;

        return is_string($key) ? $key : '';
    }

    /**
     * How far, in seconds of the current period, the share still ahead (PlanChangeService::prorationFraction(), read here
     * on the application clock) has moved from the share the line was priced for.
     */
    private function prorationDrift(Subscription $subscription, float $pricedFraction): float
    {
        $now = now()->getTimestamp();
        $start = self::moment($subscription->getAttribute('current_period_start'))?->getTimestamp() ?? $now;
        $end = self::moment($subscription->getAttribute('current_period_end'))?->getTimestamp() ?? $start;
        $length = max(1, $end - $start);
        $remaining = max(0, min($length, $end - $now)) / $length;

        return abs($pricedFraction - $remaining) * $length;
    }

    private function otherChangePaidSince(Order $order, string $serviceId, ?CarbonInterface $placedAt): bool
    {
        return OrderItem::query()->where('order_id', '!=', $order->id)->where('config->upgrade_of', $serviceId)
            ->whereHas('order', fn ($q) => $q->where('organization_id', $order->organization_id)->whereNotNull('paid_at')->when($placedAt !== null, fn ($q) => $q->where('paid_at', '>=', $placedAt)))
            ->exists();
    }

    private static function moment(mixed $value): ?CarbonInterface
    {
        return $value instanceof CarbonInterface ? $value : null;
    }
}
