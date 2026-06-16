<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class MonitoringController extends Controller
{
    public function index(): View
    {
        $sslWarnDate = now()->addDays(30);

        return view('admin.monitoring', [
            'monitors' => Monitor::query()
                ->with('service.customer')
                ->latest('id')
                ->paginate(25),
            'downCount'    => Monitor::query()->where('status', MonitorStatus::Down->value)->count(),
            'upCount'      => Monitor::query()->where('status', MonitorStatus::Up->value)->count(),
            'totalCount'   => Monitor::query()->count(),
            'sslExpiring'  => Monitor::query()->whereNotNull('ssl_expires_at')->whereDate('ssl_expires_at', '<=', $sslWarnDate)->count(),
            'avgUptime'    => round((float) Monitor::query()->whereNotNull('uptime_percent')->avg('uptime_percent'), 2),
            'incidents'    => MonitorIncident::query()
                ->with('monitor.service')
                ->latest('started_at')
                ->limit(20)
                ->get(),
            'openIncidents' => MonitorIncident::query()->whereNull('resolved_at')->count(),
        ]);
    }
}
