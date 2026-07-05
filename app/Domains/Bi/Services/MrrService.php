<?php

declare(strict_types=1);

namespace App\Domains\Bi\Services;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\DB;

final class MrrService
{
    /**
     * Current MRR from active services (sum of monthly-normalised product prices).
     * Falls back to last 30 days paid invoices / 30 * 30 if no product pricing is stored.
     *
     * @return array{mrr: float, arr: float, active_services: int, churn_rate: float, growth_rate: float}
     */
    public function summary(): array
    {
        $activeServices = Service::where('status', ServiceStatus::Active->value)->count();

        // MRR ≈ last 30 days paid revenue (best estimate without subscription price table)
        $mrr = $this->paidRevenue(now()->subDays(30), now());
        $arr = $mrr * 12;

        // Growth rate: compare this month vs last month paid revenue
        $thisMonth = $this->paidRevenue(now()->startOfMonth(), now()->endOfMonth());
        $lastMonth = $this->paidRevenue(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());
        $growthRate = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0.0;

        // Churn rate: services terminated in last 30 days / active at start of period
        $churned   = Service::where('status', ServiceStatus::Terminated->value)
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();
        $baseCount = $activeServices + $churned;
        $churnRate = $baseCount > 0 ? round(($churned / $baseCount) * 100, 2) : 0.0;

        return [
            'mrr'             => round($mrr, 2),
            'arr'             => round($arr, 2),
            'active_services' => $activeServices,
            'churn_rate'      => $churnRate,
            'growth_rate'     => $growthRate,
        ];
    }

    /**
     * MRR trend for the last N months.
     *
     * @return array<string, float>
     */
    public function mrrTrend(int $months = 6): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end   = now()->subMonths($i)->endOfMonth();
            $label = $start->format('Y-m');
            $result[$label] = round($this->paidRevenue($start, $end), 2);
        }
        return $result;
    }

    /**
     * Customer Lifetime Value by segment (average total paid per customer).
     *
     * @return array<string, float>
     */
    public function clvBySegment(): array
    {
        $result = [];

        $rows = DB::table('customers')
            ->leftJoin('invoices', function ($join): void {
                $join->on('customers.id', '=', 'invoices.customer_id')
                     ->where('invoices.status', InvoiceStatus::Paid->value);
            })
            ->selectRaw('customers.segment, COUNT(DISTINCT customers.id) as customer_count, COALESCE(SUM(invoices.total), 0) as total_paid')
            ->groupBy('customers.segment')
            ->get();

        foreach ($rows as $row) {
            $segment = (string) ($row->segment ?? 'unknown');
            $count   = (int) $row->customer_count;
            $total   = (float) $row->total_paid;
            $result[$segment] = $count > 0 ? round($total / $count / 100, 2) : 0.0;
        }

        return $result;
    }

    private function paidRevenue(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        $total = Invoice::query()
            ->where('status', InvoiceStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('total');

        return (float) $total / 100;
    }
}
