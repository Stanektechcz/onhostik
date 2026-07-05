<?php

declare(strict_types=1);

namespace App\Domains\Bi\Services;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Customer\Models\Customer;
use Illuminate\Support\Facades\DB;

final class CohortAnalyser
{
    /**
     * Revenue cohort analysis: groups customers by their first paid invoice month,
     * then returns total paid revenue per cohort for up to $periods subsequent months.
     *
     * @param int $cohorts  Number of acquisition cohorts to look back (months)
     * @param int $periods  Number of retention periods per cohort
     * @return array<string, array<int, float>>  ['YYYY-MM' => [period0 => revenue, ...]]
     */
    public function analyse(int $cohorts = 6, int $periods = 4): array
    {
        $result = [];

        for ($c = $cohorts - 1; $c >= 0; $c--) {
            $cohortStart = now()->subMonths($c)->startOfMonth();
            $cohortEnd   = now()->subMonths($c)->endOfMonth();
            $label       = $cohortStart->format('Y-m');

            // Customers who made their first payment in this cohort month
            $customerIds = DB::table('invoices')
                ->where('status', InvoiceStatus::Paid->value)
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$cohortStart, $cohortEnd])
                ->join(
                    DB::raw('(SELECT customer_id, MIN(paid_at) AS first_paid FROM invoices WHERE status = \'' . InvoiceStatus::Paid->value . '\' AND paid_at IS NOT NULL GROUP BY customer_id) AS first_payments'),
                    function ($join) use ($cohortStart, $cohortEnd): void {
                        $join->on('invoices.customer_id', '=', 'first_payments.customer_id')
                             ->whereBetween('first_payments.first_paid', [$cohortStart, $cohortEnd]);
                    }
                )
                ->distinct()
                ->pluck('invoices.customer_id')
                ->toArray();

            if (empty($customerIds)) {
                $result[$label] = array_fill(0, $periods, 0.0);
                continue;
            }

            $periodRevenues = [];
            for ($p = 0; $p < $periods; $p++) {
                $periodStart = (clone $cohortStart)->addMonths($p)->startOfMonth();
                $periodEnd   = (clone $periodStart)->endOfMonth();

                if ($periodStart->isAfter(now())) {
                    $periodRevenues[$p] = null;  // Future period
                    continue;
                }

                $total = DB::table('invoices')
                    ->where('status', InvoiceStatus::Paid->value)
                    ->whereNotNull('paid_at')
                    ->whereBetween('paid_at', [$periodStart, $periodEnd])
                    ->whereIn('customer_id', $customerIds)
                    ->sum('total');

                $periodRevenues[$p] = round((float) $total / 100, 2);
            }

            $result[$label] = $periodRevenues;
        }

        return $result;
    }

    /**
     * New customers per month for the last N months.
     *
     * @return array<string, int>
     */
    public function newCustomersPerMonth(int $months = 6): array
    {
        $result = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = now()->subMonths($i)->endOfMonth();
            $label = $start->format('Y-m');

            $result[$label] = (int) Customer::query()
                ->whereBetween('created_at', [$start, $end])
                ->count();
        }

        return $result;
    }
}
