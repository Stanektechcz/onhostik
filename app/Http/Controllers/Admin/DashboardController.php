<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Ai\Enums\ApprovalStatus;
use App\Domains\Ai\Models\AiActionApproval;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Models\Payment;
use App\Domains\Customer\Models\Customer;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(): View
    {
        $isSqlite  = DB::getDriverName() === 'sqlite';
        $monthExpr = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        // ── Revenue (CZK, completed payments) ───────────────────────────────
        $revenueMinor = (int) Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->sum('amount');

        // Revenue per month last 6 months
        $revenueRaw = DB::table('payments')
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');

        // Revenue last month vs 2 months ago (trend %)
        $revLastMonth  = (int) ($revenueRaw[now()->subMonths(1)->format('Y-m')] ?? 0);
        $revPrevMonth  = (int) ($revenueRaw[now()->subMonths(2)->format('Y-m')] ?? 0);
        $revTrendPct   = $revPrevMonth > 0 ? round(($revLastMonth - $revPrevMonth) / $revPrevMonth * 100) : 0;

        // Fill 6-month revenue array
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months[$key] = (int) ($revenueRaw[$key] ?? 0);
        }
        $chartLabels = $months->keys()->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y'))->values();
        $chartData   = $months->values()->map(fn ($v) => round($v / 100, 2))->values();

        // ── Order counts per month (visitor_chart) ───────────────────────────
        $orderCountsRaw = DB::table('orders')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');
        $orderCountsFilled = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $orderCountsFilled[$key] = (int) ($orderCountsRaw[$key] ?? 0);
        }
        $orderCountsData = $orderCountsFilled->values();

        // ── Monthly target (% paid invoices this month) ───────────────────────
        $totalThisMonth  = Invoice::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $paidThisMonth   = Invoice::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->where('status', InvoiceStatus::Paid->value)->count();
        $monthlyTargetPct = $totalThisMonth > 0 ? round($paidThisMonth / $totalThisMonth * 100, 1) : 0;

        // ── Sales report data (orders, revenue, refunds last 6 months) ────────
        $failedPerMonth = DB::table('payments')
            ->where('status', PaymentStatus::Failed->value)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(DB::raw("$monthExpr as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')->orderBy('month')
            ->pluck('total', 'month');
        $saleReportRevenue = $chartData->values();
        $saleReportOrders  = $orderCountsData->values();
        $saleReportRefunds = $months->keys()->map(fn ($m) => (int) ($failedPerMonth[$m] ?? 0))->values();

        // ── KPIs ─────────────────────────────────────────────────────────────
        $customerCount     = Customer::count();
        $customerLastMonth = Customer::where('created_at', '>=', now()->subMonth()->startOfMonth())->count();
        $activeServices    = Service::query()->where('status', ServiceStatus::Active->value)->count();
        $suspendedServices = Service::query()->where('status', ServiceStatus::Suspended->value)->count();
        $unpaidInvoices    = Invoice::query()->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])->count();
        $overdueInvoices   = Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count();

        // ── Top customers (by total completed payments) ────────────────────
        $topCustomers = Customer::query()
            ->withSum(['payments as total_paid' => fn ($q) => $q->where('status', PaymentStatus::Completed->value)->where('currency', 'CZK')], 'amount')
            ->withCount('orders')
            ->orderByDesc('total_paid')
            ->limit(7)
            ->get();

        // ── Upcoming renewals (14 days) ────────────────────────────────────
        $upcomingRenewals = Service::with('customer')
            ->where('status', ServiceStatus::Active->value)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '>=', now()->toDateString())
            ->whereDate('next_due_date', '<=', now()->addDays(14)->toDateString())
            ->orderBy('next_due_date')
            ->limit(4)
            ->get();

        return view('admin.dashboard', [
            // KPI counters
            'revenueCzkMinor'   => $revenueMinor,
            'revTrendPct'       => $revTrendPct,
            'customerCount'     => $customerCount,
            'customerLastMonth' => $customerLastMonth,
            'activeServices'    => $activeServices,
            'suspendedServices' => $suspendedServices,
            'unpaidInvoices'    => $unpaidInvoices,
            'overdueInvoices'   => $overdueInvoices,
            // system health
            'pendingOrders'     => Order::query()->where('status', OrderStatus::Pending->value)->count(),
            'processingOrders'  => Order::query()->where('status', OrderStatus::Processing->value)->count(),
            'failedPayments'    => Payment::query()->where('status', PaymentStatus::Failed->value)->count(),
            'failedTasks'       => ProvisioningTask::query()->whereIn('status', [TaskStatus::Failed->value, TaskStatus::ManualReview->value])->count(),
            'activeDomains'     => DomainRegistration::query()->whereNotNull('wedos_domain_id')->count(),
            'failedDomainTasks' => ProvisioningTask::query()->where('operation', 'register_domain')->whereIn('status', [TaskStatus::Failed->value, TaskStatus::ManualReview->value])->count(),
            'openTickets'       => SupportTicket::query()->where('status', '!=', TicketStatus::Closed->value)->count(),
            'aiApprovals'       => AiActionApproval::query()->where('status', ApprovalStatus::Pending->value)->count(),
            'pendingJobs'       => (int) DB::table('jobs')->count(),
            // tables
            'servers'           => Server::query()->withCount('services')->get(),
            'recentOrders'      => Order::with('customer')->latest()->limit(7)->get(),
            'recentAudit'       => Activity::query()->with('causer')->latest('id')->limit(8)->get(),
            'topCustomers'      => $topCustomers,
            'upcomingRenewals'  => $upcomingRenewals,
            'integrations'      => IntegrationSetting::orderBy('provider')->get(),
            // chart data
            'chartLabels'       => $chartLabels,
            'chartData'         => $chartData,
            'orderCountsData'   => $orderCountsData,
            'monthlyTargetPct'  => $monthlyTargetPct,
            'monthlyPaid'       => $paidThisMonth,
            'monthlyTotal'      => $totalThisMonth,
            'saleReportRevenue' => $saleReportRevenue,
            'saleReportOrders'  => $saleReportOrders,
            'saleReportRefunds' => $saleReportRefunds,
            // visitor_chart "this month orders"
            'ordersThisMonth'   => $orderCountsData->last() ?? 0,
        ]);
    }
}
