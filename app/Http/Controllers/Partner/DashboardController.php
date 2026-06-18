<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Partner data is stub — real commission/referral models are a future phase.
        // All values default to zero so the Cuba dashboard renders correctly.
        $chartLabels = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $chartLabels[$key] = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y');
        }

        $emptySeries = collect(array_fill(0, 6, 0));

        return view('partner.dashboard', [
            'partnerName'         => $user?->name ?? 'Partner',
            'commissionEarned'    => 0,
            'commissionPending'   => 0,
            'referredClients'     => 0,
            'referredOrders'      => 0,
            'monthlyTargetPct'    => 0,
            'monthlyCommission'   => 0,
            'monthlyTarget'       => 0,
            'topReferrals'        => collect(),
            'recentReferredOrders' => collect(),
            'upcomingPayouts'     => collect(),
            'chartLabels'         => $chartLabels->values(),
            'referralCounts'      => $emptySeries,
            'commissionMonthly'   => $emptySeries,
            'saleReportRevenue'   => $emptySeries,
            'saleReportOrders'    => $emptySeries,
            'saleReportRefunds'   => $emptySeries,
            'referralsThisMonth'  => 0,
        ]);
    }
}
