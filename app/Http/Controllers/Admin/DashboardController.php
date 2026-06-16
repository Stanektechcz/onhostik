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
        $revenueMinor = (int) Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->sum('amount');

        // Revenue per month (last 6 months) for Chart.js
        $monthExpr = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $revenueChart = Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->where('currency', 'CZK')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Fill in missing months with 0
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $months[$key] = (int) ($revenueChart[$key] ?? 0);
        }

        $suspendedServices = Service::query()
            ->where('status', ServiceStatus::Suspended->value)
            ->count();

        return view('admin.dashboard', [
            'revenueCzkMinor'   => $revenueMinor,
            'pendingOrders'     => Order::query()->where('status', OrderStatus::Pending->value)->count(),
            'processingOrders'  => Order::query()->where('status', OrderStatus::Processing->value)->count(),
            'unpaidInvoices'    => Invoice::query()
                ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
                ->count(),
            'failedPayments'    => Payment::query()->where('status', PaymentStatus::Failed->value)->count(),
            'activeServices'    => Service::query()->where('status', ServiceStatus::Active->value)->count(),
            'suspendedServices' => $suspendedServices,
            'failedTasks'       => ProvisioningTask::query()
                ->whereIn('status', [TaskStatus::Failed->value, TaskStatus::ManualReview->value])
                ->count(),
            'activeDomains'     => DomainRegistration::query()->whereNotNull('wedos_domain_id')->count(),
            'failedDomainTasks' => ProvisioningTask::query()
                ->where('operation', 'register_domain')
                ->whereIn('status', [TaskStatus::Failed->value, TaskStatus::ManualReview->value])
                ->count(),
            'servers'           => Server::query()->withCount('services')->get(),
            'openTickets'       => SupportTicket::query()->where('status', '!=', TicketStatus::Closed->value)->count(),
            'aiApprovals'       => AiActionApproval::query()->where('status', ApprovalStatus::Pending->value)->count(),
            'recentAudit'       => Activity::query()->with('causer')->latest('id')->limit(8)->get(),
            // chart data
            'chartLabels'       => $months->keys()->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y'))->values(),
            'chartData'         => $months->values()->map(fn ($v) => round($v / 100, 2))->values(),
        ]);
    }
}
