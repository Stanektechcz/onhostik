<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Services;

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Loyalty\Models\CustomerLoyaltyReward;
use App\Domains\Loyalty\Models\LoyaltyMilestone;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(private readonly CreditLedger $creditLedger) {}
    /**
     * Check all active milestones for the given customer and award any not yet granted.
     * Returns the number of newly awarded milestones.
     */
    public function checkAndAward(Customer $customer): int
    {
        $milestones = LoyaltyMilestone::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $awarded = 0;

        foreach ($milestones as $milestone) {
            if ($this->alreadyAwarded($customer, $milestone)) {
                continue;
            }

            if ($this->meetsCondition($customer, $milestone)) {
                $this->award($customer, $milestone);
                $awarded++;
            }
        }

        return $awarded;
    }

    private function alreadyAwarded(Customer $customer, LoyaltyMilestone $milestone): bool
    {
        return CustomerLoyaltyReward::where('customer_id', $customer->id)
            ->where('loyalty_milestone_id', $milestone->id)
            ->exists();
    }

    private function meetsCondition(Customer $customer, LoyaltyMilestone $milestone): bool
    {
        return match ($milestone->trigger_type) {
            'account_age_days' => $this->accountAgeDays($customer) >= $milestone->trigger_value,
            'order_count'      => $this->completedOrderCount($customer) >= $milestone->trigger_value,
            'total_spent_czk'  => $this->totalSpentHalere($customer) >= $milestone->trigger_value,
        };
    }

    private function award(Customer $customer, LoyaltyMilestone $milestone): void
    {
        CustomerLoyaltyReward::create([
            'customer_id'         => $customer->id,
            'loyalty_milestone_id' => $milestone->id,
            'awarded_at'          => now(),
        ]);

        // Apply credit reward immediately via the credit ledger
        if ($milestone->reward_type === 'credit_czk') {
            $currency = $customer->preferred_currency->value;
            $money    = Money::ofMinor($milestone->reward_value, $currency);
            $this->creditLedger->deposit($customer, $money, "Věrnostní odměna: {$milestone->name}");
        }
    }

    // ── Metric helpers ─────────────────────────────────────────────────────────

    private function accountAgeDays(Customer $customer): int
    {
        return (int) now()->diffInDays($customer->created_at);
    }

    private function completedOrderCount(Customer $customer): int
    {
        return (int) DB::table('orders')
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['cancelled', 'fraud'])
            ->count();
    }

    /** Returns total spent in haléře (CZK × 100). */
    private function totalSpentHalere(Customer $customer): int
    {
        return (int) DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum('total');
    }

    // ── Progress helpers (for panel display) ──────────────────────────────────

    /**
     * @return array{milestone: LoyaltyMilestone, current: int, target: int, percent: int}|null
     */
    public function nextMilestone(Customer $customer): ?array
    {
        $awarded = CustomerLoyaltyReward::where('customer_id', $customer->id)
            ->pluck('loyalty_milestone_id')
            ->all();

        $next = LoyaltyMilestone::where('is_active', true)
            ->whereNotIn('id', $awarded)
            ->orderBy('sort_order')
            ->first();

        if ($next === null) {
            return null;
        }

        $current = match ($next->trigger_type) {
            'account_age_days' => $this->accountAgeDays($customer),
            'order_count'      => $this->completedOrderCount($customer),
            'total_spent_czk'  => $this->totalSpentHalere($customer),
        };

        $target  = $next->trigger_value;
        $percent = $target > 0 ? (int) min(100, round($current / $target * 100)) : 100;

        return [
            'milestone' => $next,
            'current'   => $current,
            'target'    => $target,
            'percent'   => $percent,
        ];
    }
}
