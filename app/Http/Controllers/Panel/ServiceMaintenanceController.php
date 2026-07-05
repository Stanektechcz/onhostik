<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceMaintenanceWindow;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $customer   = $request->user()->customer;
        $serviceIds = $customer->services()->pluck('id');

        $upcoming = ServiceMaintenanceWindow::whereIn('service_id', $serviceIds)
            ->upcoming()
            ->with('service')
            ->get();

        $past = ServiceMaintenanceWindow::whereIn('service_id', $serviceIds)
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderByDesc('scheduled_start')
            ->with('service')
            ->limit(20)
            ->get();

        return view('panel.maintenance.index', compact('upcoming', 'past'));
    }

    public function show(Request $request, Service $service): View
    {
        $this->authorize('view', $service);

        $upcoming = $service->maintenanceWindows()
            ->upcoming()
            ->get();

        $past = $service->maintenanceWindows()
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderByDesc('scheduled_start')
            ->limit(10)
            ->get();

        return view('panel.maintenance.show', compact('service', 'upcoming', 'past'));
    }
}
