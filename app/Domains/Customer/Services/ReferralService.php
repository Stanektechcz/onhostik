<?php

declare(strict_types=1);

namespace App\Domains\Customer\Services;

use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerReferral;
use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Support\Str;

final class ReferralService
{
    /** Referrer reward in CZK halere (200 Kč) */
    private const REFERRER_REWARD_HALER = 20000;

    /** Referee discount in CZK halere (100 Kč) */
    private const REFEREE_REWARD_HALER = 10000;

    public function __construct(private readonly CreditLedger $creditLedger) {}

    public function getOrCreateCode(Customer $customer): string
    {
        if ($customer->referral_code) {
            return $customer->referral_code;
        }

        $code = $this->generateUniqueCode();
        $customer->update(['referral_code' => $code]);

        return $code;
    }

    public function register(Customer $newCustomer, string $code): ?CustomerReferral
    {
        $referrer = Customer::where('referral_code', $code)->first();

        if ($referrer === null || $referrer->id === $newCustomer->id) {
            return null;
        }

        // Prevent double registration
        if (CustomerReferral::where('referee_id', $newCustomer->id)->exists()) {
            return null;
        }

        $newCustomer->update(['referred_by_customer_id' => $referrer->id]);

        return CustomerReferral::create([
            'referrer_id'           => $referrer->id,
            'referee_id'            => $newCustomer->id,
            'status'                => 'pending',
            'referrer_reward_haler' => self::REFERRER_REWARD_HALER,
            'referee_reward_haler'  => self::REFEREE_REWARD_HALER,
            'currency'              => 'CZK',
        ]);
    }

    public function qualify(CustomerReferral $referral): void
    {
        if ($referral->status !== 'pending') {
            return;
        }

        $referral->update([
            'status'       => 'qualified',
            'qualified_at' => now(),
        ]);
    }

    public function reward(CustomerReferral $referral): void
    {
        if ($referral->status !== 'qualified') {
            return;
        }

        $referrer = $referral->referrer;
        $referee  = $referral->referee;

        $currency = Currency::tryFrom($referral->currency) ?? Currency::CZK;

        if ($referral->referrer_reward_haler > 0) {
            $money = Money::ofMinor($referral->referrer_reward_haler, $currency->value);
            $this->creditLedger->deposit(
                $referrer,
                $money,
                "Referral odměna za zákazníka #{$referee->id}",
            );
        }

        if ($referral->referee_reward_haler > 0) {
            $money = Money::ofMinor($referral->referee_reward_haler, $currency->value);
            $this->creditLedger->deposit(
                $referee,
                $money,
                'Uvítací bonus za registraci s referral kódem',
            );
        }

        $referral->update([
            'status'      => 'rewarded',
            'rewarded_at' => now(),
        ]);
    }

    public function expire(CustomerReferral $referral): void
    {
        $referral->update(['status' => 'expired']);
    }

    /** @return array{total: int, qualified: int, rewarded: int, earned_haler: int} */
    public function stats(Customer $customer): array
    {
        $referrals = CustomerReferral::where('referrer_id', $customer->id)->get();

        return [
            'total'        => $referrals->count(),
            'qualified'    => $referrals->where('status', 'qualified')->count(),
            'rewarded'     => $referrals->where('status', 'rewarded')->count(),
            'earned_haler' => $referrals->where('status', 'rewarded')->sum('referrer_reward_haler'),
        ];
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Customer::where('referral_code', $code)->exists());

        return $code;
    }
}
