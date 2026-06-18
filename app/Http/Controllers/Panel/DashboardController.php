<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        // ── Chart labels (last 6 months) ─────────────────────────────────
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months[$key] = 0;
        }
        $chartLabels = $months->keys()->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y'))->values();

        // ── Monthly payments (completed, CZK) ────────────────────────────
        $paymentRaw = DB::table('payments')
            ->where('customer_id', $customer->id)
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        $paymentMonths = $months->map(fn ($_, $key) => (int) ($paymentRaw[$key] ?? 0));
        $paymentChartData = $paymentMonths->values()->map(fn ($v) => round($v / 100, 2));

        // ── Monthly order counts ─────────────────────────────────────────
        $orderCountsRaw = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        $orderCountsData = $months->map(fn ($_, $key) => (int) ($orderCountsRaw[$key] ?? 0))->values();

        // ── Monthly target (% paid invoices this month) ──────────────────
        $totalThisMonth = Invoice::where('customer_id', $customer->id)
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $paidThisMonth  = Invoice::where('customer_id', $customer->id)
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)
            ->where('status', InvoiceStatus::Paid->value)->count();
        $monthlyTargetPct = $totalThisMonth > 0 ? round($paidThisMonth / $totalThisMonth * 100, 1) : 0;

        // ── Failed payments per month ────────────────────────────────────
        $failedPerMonth = DB::table('payments')
            ->where('customer_id', $customer->id)
            ->where('status', PaymentStatus::Failed->value)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        $saleReportRevenue = $paymentChartData->values();
        $saleReportOrders  = $orderCountsData->values();
        $saleReportRefunds = $months->keys()->map(fn ($m) => (int) ($failedPerMonth[$m] ?? 0))->values();

        // ── Unpaid invoices (for table + widget) ─────────────────────────
        $unpaidInvoices = $customer->invoices()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->latest('id')
            ->get();

        $overdueInvoices = $customer->invoices()
            ->where('status', InvoiceStatus::Overdue->value)
            ->count();

        // ── Recent invoices (for cuba main-customer-table slot) ──────────
        $recentInvoices = $customer->invoices()
            ->latest('id')
            ->limit(7)
            ->get();

        // ── Upcoming renewals (14 days) ───────────────────────────────────
        $upcomingRenewals = Service::with('customer')
            ->where('customer_id', $customer->id)
            ->where('status', ServiceStatus::Active->value)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '>=', now()->toDateString())
            ->whereDate('next_due_date', '<=', now()->addDays(14)->toDateString())
            ->orderBy('next_due_date')
            ->limit(4)
            ->get();

        // ── KPIs ─────────────────────────────────────────────────────────
        $activeServices = $customer->services()->where('status', ServiceStatus::Active->value)->count();
        $activeDomains  = DomainRegistration::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id))
            ->whereNotNull('wedos_domain_id')
            ->count();
        $openTickets = $customer->supportTickets()
            ->where('status', '!=', TicketStatus::Closed->value)
            ->count();
        $creditBalance = $ledger->getBalance($customer);

        // ── Latest incident ──────────────────────────────────────────────
        $latestIncident = MonitorIncident::query()
            ->whereHas('monitor.service', fn ($q) => $q->where('customer_id', $customer->id))
            ->latest('started_at')
            ->first();

        // ── Payment trend (last month vs 2 months ago) ───────────────────
        $payLast  = (int) ($paymentRaw[now()->subMonths(1)->format('Y-m')] ?? 0);
        $payPrev  = (int) ($paymentRaw[now()->subMonths(2)->format('Y-m')] ?? 0);
        $payTrend = $payPrev > 0 ? round(($payLast - $payPrev) / $payPrev * 100) : 0;

        return view('panel.dashboard', [
            // KPI
            'creditBalance'    => $creditBalance,
            'payTrend'         => $payTrend,
            'activeServices'   => $activeServices,
            'activeDomains'    => $activeDomains,
            'unpaidInvoices'   => $unpaidInvoices,
            'unpaidCount'      => $unpaidInvoices->count(),
            'overdueInvoices'  => $overdueInvoices,
            'openTickets'      => $openTickets,
            // tables
            'recentInvoices'   => $recentInvoices,
            'recentOrders'     => $customer->orders()->latest('id')->limit(7)->get(),
            'recentPayments'   => $customer->payments()->latest('id')->limit(5)->get(),
            'upcomingRenewals' => $upcomingRenewals,
            // monthly target
            'monthlyTargetPct' => $monthlyTargetPct,
            'monthlyPaid'      => $paidThisMonth,
            'monthlyTotal'     => $totalThisMonth,
            // chart data
            'chartLabels'      => $chartLabels,
            'paymentChartData' => $paymentChartData,
            'orderCountsData'  => $orderCountsData,
            'saleReportRevenue' => $saleReportRevenue,
            'saleReportOrders'  => $saleReportOrders,
            'saleReportRefunds' => $saleReportRefunds,
            'ordersThisMonth'  => $orderCountsData->last() ?? 0,
            // incident
            'latestIncident'   => $latestIncident,
        ]);
    }
}
