<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Support\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        $unpaidInvoices = $customer->invoices()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->latest('id')
            ->get();

        return view('panel.dashboard', [
            'activeServices' => $customer->services()->where('status', ServiceStatus::Active->value)->count(),
            'activeDomains'  => DomainRegistration::query()
                ->whereHas('service', fn ($query) => $query->where('customer_id', $customer->id))
                ->whereNotNull('wedos_domain_id')
                ->count(),
            'unpaidInvoices' => $unpaidInvoices,
            'recentOrders'   => $customer->orders()->latest('id')->limit(5)->get(),
            'recentPayments' => $customer->payments()->latest('id')->limit(5)->get(),
            'creditBalance'  => $ledger->getBalance($customer),
            'openTickets'    => $customer->supportTickets()
                ->where('status', '!=', TicketStatus::Closed->value)
                ->count(),
            'latestIncident' => MonitorIncident::query()
                ->whereHas('monitor.service', fn ($query) => $query->where('customer_id', $customer->id))
                ->latest('started_at')
                ->first(),
        ]);
    }
}
