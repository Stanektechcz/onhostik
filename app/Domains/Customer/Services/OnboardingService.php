<?php

declare(strict_types=1);

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerOnboardingStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes onboarding step completion for a customer.
 *
 * Steps are evaluated lazily from already-loaded relationships where possible.
 * Call loadMissing(['addresses', 'user', 'services']) before invoking if
 * you need to avoid N+1 queries.
 */
final class OnboardingService
{
    /** Human-readable labels for each step. */
    public const STEP_LABELS = [
        'profile'         => 'Doplnění profilu',
        'billing_address' => 'Fakturační adresa',
        'two_factor'      => 'Dvoufaktorové přihlášení',
        'first_service'   => 'První objednávka',
    ];

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

    /**
     * Persist completed steps to customer_onboarding_steps table.
     * Safe to call repeatedly — uses updateOrCreate.
     */
    public function syncToDB(Customer $customer): void
    {
        foreach ($this->steps($customer) as $step => $done) {
            if ($done) {
                CustomerOnboardingStep::updateOrCreate(
                    ['customer_id' => $customer->id, 'step' => $step],
                    ['completed_at' => now()]
                );
            }
        }
    }

    /**
     * Returns checklist with completion status and timestamps from DB.
     *
     * @return Collection<int, array{step: string, label: string, completed: bool, completed_at: mixed}>
     */
    public function checklist(Customer $customer): Collection
    {
        $this->syncToDB($customer);

        $done = CustomerOnboardingStep::where('customer_id', $customer->id)
            ->whereNotNull('completed_at')
            ->pluck('completed_at', 'step');

        return collect(self::STEP_LABELS)->map(fn (string $label, string $step) => [
            'step'         => $step,
            'label'        => $label,
            'completed'    => isset($done[$step]),
            'completed_at' => $done[$step] ?? null,
        ])->values();
    }

    /**
     * Global onboarding stats across all customers.
     *
     * @return array{total: int, fullyComplete: int, inProgress: int, notStarted: int, stepRates: array<string, int>}
     */
    public function globalStats(): array
    {
        $stepCount   = count(self::STEP_LABELS);
        $customerIds = Customer::pluck('id');
        $total       = $customerIds->count();

        $completedCounts = CustomerOnboardingStep::whereNotNull('completed_at')
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, COUNT(*) as cnt')
            ->groupBy('customer_id')
            ->pluck('cnt', 'customer_id');

        $fullyComplete = $completedCounts->filter(fn (int $c): bool => $c >= $stepCount)->count();
        $inProgress    = $completedCounts->filter(fn (int $c): bool => $c > 0 && $c < $stepCount)->count();
        $notStarted    = max(0, $total - $completedCounts->count());

        // Per-step completion rates
        $stepRates = [];
        if ($total > 0) {
            $perStep = CustomerOnboardingStep::whereNotNull('completed_at')
                ->whereIn('customer_id', $customerIds)
                ->selectRaw('step, COUNT(*) as cnt')
                ->groupBy('step')
                ->pluck('cnt', 'step');

            foreach (array_keys(self::STEP_LABELS) as $step) {
                $stepRates[$step] = (int) round(($perStep[$step] ?? 0) / $total * 100);
            }
        }

        return compact('total', 'fullyComplete', 'inProgress', 'notStarted', 'stepRates');
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
