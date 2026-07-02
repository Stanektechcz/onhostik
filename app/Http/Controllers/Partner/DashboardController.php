<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Domains\Partner\Enums\CommissionStatus;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Domains\Partner\Models\PartnerProfile;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $profile = $user ? PartnerProfile::where('user_id', $user->id)->first() : null;

        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        // Chart labels (last 6 months)
        $chartLabels = collect();
        $months      = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key            = now()->subMonths($i)->format('Y-m');
            $chartLabels[$key] = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y');
            $months[$key]   = 0;
        }

        $emptySeries = $months->values();

        if ($profile === null) {
            // No partner profile yet — show zeros
            return view('partner.dashboard', $this->stubData($user, $chartLabels->values(), $emptySeries));
        }

        // KPIs
        $commissionEarned  = (int) $profile->commissions()->where('status', CommissionStatus::Paid->value)->sum('amount');
        $commissionPending = (int) $profile->commissions()->where('status', CommissionStatus::Pending->value)->sum('amount');
        $referredClients   = $profile->referrals()->count();
        $referredOrders    = $profile->referrals()->where('status', ReferralStatus::Customer->value)->count();

        // Monthly commission chart
        $commRaw = DB::table('partner_commissions')
            ->where('partner_profile_id', $profile->id)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        $commissionMonthly = $months->map(fn ($_, $key) => (int) ($commRaw[$key] ?? 0))
            ->values()
            ->map(fn ($v) => round($v / 100, 2));

        // Monthly referral counts
        $refRaw = DB::table('partner_referrals')
            ->where('partner_profile_id', $profile->id)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        $referralCounts = $months->map(fn ($_, $key) => (int) ($refRaw[$key] ?? 0))->values();

        // Top referrals (customers who converted)
        $topReferrals = $profile->referrals()
            ->with('referredUser')
            ->where('status', ReferralStatus::Customer->value)
            ->latest('converted_at')
            ->limit(7)
            ->get();

        // Recent referred orders (via commissions)
        $recentReferredOrders = $profile->commissions()
            ->with('order.customer', 'invoice')
            ->latest()
            ->limit(7)
            ->get();

        // Upcoming payouts (approved commissions)
        $upcomingPayouts = $profile->commissions()
            ->where('status', CommissionStatus::Approved->value)
            ->latest('approved_at')
            ->limit(4)
            ->get();

        $referralsThisMonth = (int) ($refRaw[now()->format('Y-m')] ?? 0);

        // Monthly target — approved vs pending this month
        $monthlyCommission = (int) DB::table('partner_commissions')
            ->where('partner_profile_id', $profile->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $monthlyTarget    = max($monthlyCommission * 2, 100000); // placeholder
        $monthlyTargetPct = round(($monthlyCommission / $monthlyTarget) * 100, 1);

        return view('partner.dashboard', [
            'partnerName'          => $user->name ?: 'Partner',
            'profile'              => $profile,
            'commissionEarned'     => $commissionEarned,
            'commissionPending'    => $commissionPending,
            'referredClients'      => $referredClients,
            'referredOrders'       => $referredOrders,
            'monthlyTargetPct'     => $monthlyTargetPct,
            'monthlyCommission'    => $monthlyCommission,
            'monthlyTarget'        => $monthlyTarget,
            'topReferrals'         => $topReferrals,
            'recentReferredOrders' => $recentReferredOrders,
            'upcomingPayouts'      => $upcomingPayouts,
            'chartLabels'          => $chartLabels->values(),
            'referralCounts'       => $referralCounts,
            'commissionMonthly'    => $commissionMonthly,
            'saleReportRevenue'    => $commissionMonthly,
            'saleReportOrders'     => $referralCounts,
            'saleReportRefunds'    => $emptySeries,
            'referralsThisMonth'   => $referralsThisMonth,
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection<int, string> $chartLabels
     * @param \Illuminate\Support\Collection<int, mixed> $emptySeries
     * @return array<string, mixed>
     */
    private function stubData(User $user, mixed $chartLabels, mixed $emptySeries): array
    {
        return [
            'partnerName'          => $user->name ?: 'Partner',
            'profile'              => null,
            'commissionEarned'     => 0,
            'commissionPending'    => 0,
            'referredClients'      => 0,
            'referredOrders'       => 0,
            'monthlyTargetPct'     => 0,
            'monthlyCommission'    => 0,
            'monthlyTarget'        => 0,
            'topReferrals'         => collect(),
            'recentReferredOrders' => collect(),
            'upcomingPayouts'      => collect(),
            'chartLabels'          => $chartLabels,
            'referralCounts'       => $emptySeries,
            'commissionMonthly'    => $emptySeries,
            'saleReportRevenue'    => $emptySeries,
            'saleReportOrders'     => $emptySeries,
            'saleReportRefunds'    => $emptySeries,
            'referralsThisMonth'   => 0,
        ];
    }
}
