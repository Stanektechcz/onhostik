<?php

declare(strict_types=1);

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Models\Customer;

/**
 * Computes onboarding step completion for a customer.
 *
 * Steps are evaluated lazily from already-loaded relationships where possible.
 * Call loadMissing(['addresses', 'user', 'services']) before invoking if
 * you need to avoid N+1 queries.
 */
final class OnboardingService
{
    /**
     * Returns completion map for all onboarding steps.
     *
     * @return array<string, bool>
     */
    public function steps(Customer $customer): array
    {
        return [
            'profile'         => $this->isProfileFilled($customer),
            'billing_address' => $this->hasBillingAddress($customer),
            'two_factor'      => $this->hasTwoFactor($customer),
            'first_service'   => $customer->services()->exists(),
        ];
    }

    /** Percentage of completed steps (0–100). */
    public function percent(Customer $customer): int
    {
        $steps = $this->steps($customer);
        $done  = count(array_filter($steps));

        return (int) round($done / count($steps) * 100);
    }

    /** True when all steps are done. */
    public function isComplete(Customer $customer): bool
    {
        return !in_array(false, $this->steps($customer), true);
    }

    private function isProfileFilled(Customer $customer): bool
    {
        return ($customer->company_name !== null && $customer->company_name !== '')
            || ($customer->phone !== null && $customer->phone !== '');
    }

    private function hasBillingAddress(Customer $customer): bool
    {
        return $customer->addresses()->exists();
    }

    private function hasTwoFactor(Customer $customer): bool
    {
        return $customer->user?->two_factor_confirmed_at !== null;
    }
}
