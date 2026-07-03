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

        return view('reseller.dashboard', [
            'profile'         => $profile,
            'user'            => $user,
            'customerCount'   => $customerCount,
            'orderCount'      => $orderCount,
            'revenueMinor'    => $revenueMinor,
            'chartLabels'     => $chartLabels->values(),
            'chartRevenue'    => $chartRevenue,
            'recentCustomers' => $recentCustomers,
        ]);
    }
}
