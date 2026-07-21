<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $profile = $user ? ResellerProfile::where('user_id', $user->id)->first() : null;

        if ($profile === null || $profile->status !== 'active') {
            return view('reseller.dashboard', [
                'profile'         => $profile,
                'user'            => $user,
                'customerCount'   => 0,
                'orderCount'      => 0,
                'revenueMinor'    => 0,
                'chartLabels'     => collect(),
                'chartRevenue'    => collect(),
            ]);
        }

        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', invoices.created_at)" : "DATE_FORMAT(invoices.created_at, '%Y-%m')";

        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months[$key] = 0;
        }

        $customerIds = $profile->customers()->pluck('id')->toArray();

        $customerCount = count($customerIds);

        $orderCount = $customerCount > 0
            ? DB::table('orders')->whereIn('customer_id', $customerIds)->count()
            : 0;

        $revenueMinor = $customerCount > 0
            ? (int) DB::table('invoices')
                ->whereIn('customer_id', $customerIds)
                ->where('status', InvoiceStatus::Paid->value)
                ->sum('total')
            : 0;

        $revenueRaw = $customerCount > 0
            ? DB::table('invoices')
                ->whereIn('customer_id', $customerIds)
                ->where('status', InvoiceStatus::Paid->value)
                ->where('invoices.created_at', '>=', now()->subMonths(5)->startOfMonth())
                ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(total) as total'))
                ->groupBy('month')->orderBy('month')
                ->pluck('total', 'month')
            : collect();

        $chartLabels  = $months->keys()->map(fn ($k) => \Carbon\Carbon::createFromFormat('Y-m', $k)->format('M Y'));
        $chartRevenue = $months->map(fn ($_, $k) => round((int) ($revenueRaw[$k] ?? 0) / 100, 2))->values();

        $recentCustomers = $profile->customers()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get();

        /*
         | K149 — MRR and margin.
         |
         | MRR is normalised recurring revenue: a service billed annually
         | contributes a twelfth of its price each month, so a portfolio mixing
         | monthly and annual plans is comparable at a glance. It is derived
         | from ACTIVE services (what will bill next month), not from historical
         | invoices (what already billed).
         |
         | Margin is the reseller's cut — the markup they charge on top of the
         | wholesale price — expressed against that MRR.
         */
        [$mrrMinor, $marginMinor] = $this->recurringMetrics($customerIds, $profile);

        return view('reseller.dashboard', [
            'profile'         => $profile,
            'user'            => $user,
            'customerCount'   => $customerCount,
            'orderCount'      => $orderCount,
            'revenueMinor'    => $revenueMinor,
            'mrrMinor'        => $mrrMinor,
            'marginMinor'     => $marginMinor,
            'chartLabels'     => $chartLabels->values(),
            'chartRevenue'    => $chartRevenue,
            'recentCustomers' => $recentCustomers,
        ]);
    }

    /**
     * Monthly-recurring revenue and reseller margin, in minor units.
     *
     * MRR normalises every active service to a monthly figure: an annual plan
     * counts as a twelfth of its price per month. That makes a portfolio of
     * mixed billing cycles comparable, which a raw "sum of invoices" cannot.
     *
     * Margin is the slice the reseller keeps — the difference between what the
     * end customer pays (the order-item price) and the wholesale base price,
     * summed across the same active services.
     *
     * @param  list<int>  $customerIds
     * @return array{0: int, 1: int}
     */
    private function recurringMetrics(array $customerIds, ResellerProfile $profile): array
    {
        if ($customerIds === []) {
            return [0, 0];
        }

        $services = \App\Domains\Provisioning\Models\Service::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', \App\Domains\Provisioning\Enums\ServiceStatus::Active->value)
            ->with(['orderItem.pricingPlan'])
            ->get();

        $mrrMinor    = 0;
        $marginMinor = 0;

        foreach ($services as $service) {
            $item = $service->orderItem;
            $plan = $item?->pricingPlan;

            if ($item === null || $plan === null) {
                continue;
            }

            $months = max(1, $plan->billing_cycle->months());

            $chargedMinor = $item->unit_price->getMinorAmount()->toInt();

            // Base wholesale price in the same currency the customer was billed.
            $currency = \App\Domains\Shared\Enums\Currency::from((string) $item->currency);
            $baseMinor = $plan->supportsCurrency($currency)
                ? $plan->priceFor($currency)->getMinorAmount()->toInt()
                : $chargedMinor;

            $mrrMinor    += intdiv($chargedMinor, $months);
            $marginMinor += intdiv(max(0, $chargedMinor - $baseMinor), $months);
        }

        return [$mrrMinor, $marginMinor];
    }
}
