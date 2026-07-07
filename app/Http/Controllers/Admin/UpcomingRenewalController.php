<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class UpcomingRenewalController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 14, 30, 60], true) ? $days : 30;

        $services = Service::with(['customer.user', 'product'])
            ->where('status', ServiceStatus::Active)
            ->where('auto_renew', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->addDays($days))
            ->whereDate('next_due_date', '>=', now())
            ->orderBy('next_due_date')
            ->paginate(50);

        $counts = [
            7  => Service::where('status', ServiceStatus::Active)->where('auto_renew', true)
                ->whereDate('next_due_date', '<=', now()->addDays(7))->whereDate('next_due_date', '>=', now())->count(),
            14 => Service::where('status', ServiceStatus::Active)->where('auto_renew', true)
                ->whereDate('next_due_date', '<=', now()->addDays(14))->whereDate('next_due_date', '>=', now())->count(),
            30 => Service::where('status', ServiceStatus::Active)->where('auto_renew', true)
                ->whereDate('next_due_date', '<=', now()->addDays(30))->whereDate('next_due_date', '>=', now())->count(),
        ];

        return view('admin.upcoming-renewals', [
            'services' => $services,
            'days'     => $days,
            'counts'   => $counts,
        ]);
    }
}
