<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Services;

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Loyalty\Enums\LoyaltyTier;
use App\Domains\Loyalty\Models\LoyaltyPointTransaction;
use App\Domains\Loyalty\Models\LoyaltyReward;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty points: a signed ledger the customer earns (paid invoices) and spends
 * (redeeming catalog rewards for credit). Separate from the milestone-based
 * auto-rewards in LoyaltyService.
 */
final class LoyaltyPointsService
{
    public function __construct(private readonly CreditLedger $creditLedger) {}

    public function balance(Customer $customer): int
    {
        return (int) LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->sum('points');
    }

    /**
     * Lifetime EARNED points (positive entries only). Tier is derived from this
     * rather than the balance, so spending points on rewards never demotes a
     * customer — otherwise redeeming would be punished.
     */
    public function lifetimeEarned(Customer $customer): int
    {
        return (int) LoyaltyPointTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('points', '>', 0)
            ->sum('points');
    }

    public function tier(Customer $customer): LoyaltyTier
    {
        return LoyaltyTier::forPoints($this->lifetimeEarned($customer));
    }

    /**
     * Tier progress for the panel: current tier, the next one and how far it is.
     *
     * @return array{tier: LoyaltyTier, next: LoyaltyTier|null, lifetime: int, to_next: int, percent: int}
     */
    public function tierProgress(Customer $customer): array
    {
        $lifetime = $this->lifetimeEarned($customer);
        $tier     = LoyaltyTier::forPoints($lifetime);
        $next     = $tier->next();

        if ($next === null) {
            return ['tier' => $tier, 'next' => null, 'lifetime' => $lifetime, 'to_next' => 0, 'percent' => 100];
        }

        $span     = max(1, $next->threshold() - $tier->threshold());
        $progress = max(0, $lifetime - $tier->threshold());

        return [
            'tier'     => $tier,
            'next'     => $next,
            'lifetime' => $lifetime,
            'to_next'  => max(0, $next->threshold() - $lifetime),
            'percent'  => (int) min(100, round($progress / $span * 100)),
        ];
    }

    /** How many points a paid amount earns (config: czk_per_point). */
    public function pointsForAmount(Money $amount): int
    {
        $perPoint   = max(1, (int) config('loyalty.czk_per_point', 10));
        $majorUnits = intdiv($amount->getMinorAmount()->toInt(), 100);

        return intdiv($majorUnits, $perPoint);
    }

    public function award(Customer $customer, int $points, string $reason, ?Model $reference = null): ?LoyaltyPointTransaction
    {
        if ($points === 0) {
            return null;
        }

        return LoyaltyPointTransaction::create([
            'customer_id'    => $customer->id,
            'points'         => $points,
            'reason'         => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id'   => $reference?->getKey(),
        ]);
    }

    /**
     * Spend points on a catalog reward, granting the reward's credit. Returns
     * false (without charging) when the reward is inactive or the balance is
     * insufficient.
     */
    public function redeem(Customer $customer, LoyaltyReward $reward): bool
    {
        if (! $reward->is_active) {
            return false;
        }

        if ($this->balance($customer) < $reward->points_cost) {
            return false;
        }

        DB::transaction(function () use ($customer, $reward): void {
            $this->award($customer, -$reward->points_cost, "Uplatnění odměny: {$reward->name}", $reward);

            if ($reward->reward_type === 'credit_czk' && $reward->reward_value_halere > 0) {
                $this->creditLedger->deposit(
                    $customer,
                    Money::ofMinor($reward->reward_value_halere, $customer->preferred_currency->value),
                    "Věrnostní odměna: {$reward->name}",
                    $reward,
                );
            }
        });

        return true;
    }
}
