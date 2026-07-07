<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceRenewalDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $window = (int) $request->input('days', 30);
        $window = in_array($window, [7, 14, 30, 60, 90], true) ? $window : 30;

        $baseQuery = Service::query()
            ->with(['customer', 'product'])
            ->whereIn('status', [ServiceStatus::Active->value, ServiceStatus::Suspended->value])
            ->whereNotNull('next_due_date')
            ->whereNull('terminated_at');

        $overdue = (clone $baseQuery)
            ->where('next_due_date', '<', now())
            ->orderBy('next_due_date')
            ->get();

        $dueSoon = (clone $baseQuery)
            ->whereBetween('next_due_date', [now(), now()->addDays($window)])
            ->orderBy('next_due_date')
            ->paginate(30)
            ->withQueryString();

        $totalDueSoon    = (clone $baseQuery)->whereBetween('next_due_date', [now(), now()->addDays($window)])->count();
        $totalOverdue    = (clone $baseQuery)->where('next_due_date', '<', now())->count();
        $noAutoRenew     = (clone $baseQuery)->whereBetween('next_due_date', [now(), now()->addDays($window)])->where('auto_renew', false)->count();

        return view('admin.service-renewal-dashboard', compact(
            'overdue',
            'dueSoon',
            'totalDueSoon',
            'totalOverdue',
            'noAutoRenew',
            'window',
        ));
    }
}
