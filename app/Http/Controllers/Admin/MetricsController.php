<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function index(): View
    {
        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        // ── MRR (Monthly Recurring Revenue in CZK) ────────────────────────────
        // Sum of paid invoices this month ÷ 1 (they are already monthly)
        $currentMonth = now()->format('Y-m');
        $mrrMinor = (int) DB::table('payments')
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        $prevMonthMinor = (int) DB::table('payments')
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->whereYear('created_at', now()->subMonth()->year)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        $mrrGrowthPct = $prevMonthMinor > 0
            ? round(($mrrMinor - $prevMonthMinor) / $prevMonthMinor * 100, 1)
            : 0;

        // ARR = MRR × 12
        $arrMinor = $mrrMinor * 12;

        // ── Monthly revenue last 12 months ────────────────────────────────────
        $revenueRaw = DB::table('payments')
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months[$key] = (int) ($revenueRaw[$key] ?? 0);
        }
        $chartLabels = $months->keys()
            ->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y'))
            ->values();
        $chartRevenue = $months->values()->map(fn ($v) => round($v / 100, 2))->values();

        // ── Churn ─────────────────────────────────────────────────────────────
        // Approximation: services that moved to Terminated/Cancelled this month
        $churnedThisMonth = Service::query()
            ->where('status', ServiceStatus::Terminated->value)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $activeServices = Service::query()->where('status', ServiceStatus::Active->value)->count();
        $churnRatePct   = $activeServices > 0
            ? round($churnedThisMonth / ($activeServices + $churnedThisMonth) * 100, 2)
            : 0;

        // ── Customer growth ───────────────────────────────────────────────────
        $customerGrowthRaw = DB::table('customers')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $customerGrowth = $months->keys()
            ->map(fn ($m) => (int) ($customerGrowthRaw[$m] ?? 0))
            ->values();

        // ── LTV (avg revenue per customer) ───────────────────────────────────
        $totalCustomers = max(1, Customer::count());
        $totalRevMinor  = (int) Payment::where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->sum('amount');
        $ltvCzk = round($totalRevMinor / 100 / $totalCustomers, 2);

        // ── Outstanding invoices ──────────────────────────────────────────────
        $outstandingMinor = (int) Invoice::whereIn('status', [
            InvoiceStatus::Sent->value,
            InvoiceStatus::Overdue->value,
        ])->sum('total_czk');

        // ── New customers last 30 days ─────────────────────────────────────────
        $newCustomers30 = Customer::where('created_at', '>=', now()->subDays(30))->count();

        return view('admin.metrics', [
            'mrrCzk'          => round($mrrMinor / 100, 2),
            'mrrGrowthPct'    => $mrrGrowthPct,
            'arrCzk'          => round($arrMinor / 100, 2),
            'churnRatePct'    => $churnRatePct,
            'churnedThisMonth'=> $churnedThisMonth,
            'ltvCzk'          => $ltvCzk,
            'totalCustomers'  => $totalCustomers,
            'newCustomers30'  => $newCustomers30,
            'activeServices'  => $activeServices,
            'outstandingCzk'  => round($outstandingMinor / 100, 2),
            'chartLabels'     => $chartLabels,
            'chartRevenue'    => $chartRevenue,
            'customerGrowth'  => $customerGrowth,
        ]);
    }
}
