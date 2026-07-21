<?php

declare(strict_types=1);

namespace App\Domains\Billing\Actions;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Models\Service;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Carbon\CarbonInterface;

/**
 * Works out what a mid-period plan change actually costs (audit D60).
 *
 * The rule: the customer already paid for the whole current period, so the
 * unused remainder of the OLD plan is credited back, and the NEW plan is
 * charged only for those same remaining days. The difference is what changes
 * hands.
 *
 *     credit = oldPrice × daysRemaining / daysInPeriod
 *     charge = newPrice × daysRemaining / daysInPeriod
 *     difference = charge − credit      (>0 upgrade → invoice
 *                                        <0 downgrade → credit to wallet)
 *
 * The previous implementation credited the NEW plan's price for the days
 * ALREADY ELAPSED, which is not proration of anything — an upgrade could come
 * out cheaper than a downgrade.
 */
final class CalculatePlanChangeProrationAction
{
    /**
     * @return array{
     *   currency: string,
     *   days_remaining: int,
     *   days_in_period: int,
     *   old_plan_price: Money,
     *   new_plan_price: Money,
     *   unused_credit: Money,
     *   prorated_charge: Money,
     *   difference: Money,
     *   is_upgrade: bool
     * }
     */
    public function execute(Service $service, PricingPlan $newPlan, ?CarbonInterface $now = null): array
    {
        $now      = $now ?? now();
        $customer = $service->customer;

        // A service with no customer cannot be priced; report a no-op rather
        // than guessing a currency.
        if ($customer === null) {
            $zero = Money::zero('CZK');

            return [
                'currency'        => 'CZK',
                'days_remaining'  => 0,
                'days_in_period'  => 0,
                'old_plan_price'  => $zero,
                'new_plan_price'  => $zero,
                'unused_credit'   => $zero,
                'prorated_charge' => $zero,
                'difference'      => $zero,
                'is_upgrade'      => false,
            ];
        }

        $currency     = $customer->preferred_currency;
        $currencyCode = $currency->value;

        $oldPlan = $service->orderItem?->pricingPlan;

        $oldPrice = $oldPlan !== null && $oldPlan->supportsCurrency($currency)
            ? $oldPlan->priceFor($currency)
            : Money::zero($currencyCode);

        $newPrice = $newPlan->supportsCurrency($currency)
            ? $newPlan->priceFor($currency)
            : Money::zero($currencyCode);

        [$daysRemaining, $daysInPeriod] = $this->period($service, $oldPlan, $now);

        // Nothing left of the period → nothing to credit, full price applies
        // from the next cycle; no money moves today.
        $ratio = $daysInPeriod > 0 ? $daysRemaining / $daysInPeriod : 0.0;

        $unusedCredit   = $oldPrice->multipliedBy($ratio, RoundingMode::HALF_UP);
        $proratedCharge = $newPrice->multipliedBy($ratio, RoundingMode::HALF_UP);
        $difference     = $proratedCharge->minus($unusedCredit);

        return [
            'currency'        => $currencyCode,
            'days_remaining'  => $daysRemaining,
            'days_in_period'  => $daysInPeriod,
            'old_plan_price'  => $oldPrice,
            'new_plan_price'  => $newPrice,
            'unused_credit'   => $unusedCredit,
            'prorated_charge' => $proratedCharge,
            'difference'      => $difference,
            'is_upgrade'      => $difference->isPositive(),
        ];
    }

    /**
     * Remaining days and the length of the billing period.
     *
     * Period length comes from the plan's billing cycle rather than "days in
     * this calendar month", so an annual plan is not prorated as if it were
     * monthly.
     *
     * @return array{0: int, 1: int}
     */
    private function period(Service $service, ?PricingPlan $oldPlan, CarbonInterface $now): array
    {
        $dueDate = $service->next_due_date;

        if ($dueDate === null) {
            return [0, 0];
        }

        $months       = $oldPlan?->billing_cycle->months() ?? 1;
        $periodStart  = $dueDate->copy()->subMonths($months);
        $daysInPeriod = max(1, (int) $periodStart->diffInDays($dueDate));

        $daysRemaining = (int) $now->diffInDays($dueDate, false);
        $daysRemaining = max(0, min($daysRemaining, $daysInPeriod));

        return [$daysRemaining, $daysInPeriod];
    }
}
