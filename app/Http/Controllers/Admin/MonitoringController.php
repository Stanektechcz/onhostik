<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $sslWarnDate = now()->addDays(30);
        $filterStatus = $request->query('status');
        $filterType   = $request->query('type');

        $monitorsQuery = Monitor::query()->with('service.customer');

        if ($filterStatus !== null && $filterStatus !== '') {
            $monitorsQuery->where('status', $filterStatus);
        }
        if ($filterType !== null && $filterType !== '') {
            $monitorsQuery->where('type', $filterType);
        }

        $monitors = $monitorsQuery->latest('id')->paginate(25)->withQueryString();

        // Distinct types for filter dropdown
        $monitorTypes = Monitor::query()->distinct()->orderBy('type')->pluck('type');

        // Aggregate uptime breakdown
        $uptimeGroups = [
            'excellent' => Monitor::query()->where('uptime_percent', '>=', 99.9)->count(),
            'good'      => Monitor::query()->whereBetween('uptime_percent', [99.0, 99.9])->count(),
            'poor'      => Monitor::query()->where('uptime_percent', '<', 99.0)->whereNotNull('uptime_percent')->count(),
            'unknown'   => Monitor::query()->whereNull('uptime_percent')->count(),
        ];

        return view('admin.monitoring', [
            'monitors'      => $monitors,
            'monitorTypes'  => $monitorTypes,
            'filterStatus'  => $filterStatus,
            'filterType'    => $filterType,
            'uptimeGroups'  => $uptimeGroups,
            'downCount'     => Monitor::query()->where('status', MonitorStatus::Down->value)->count(),
            'upCount'       => Monitor::query()->where('status', MonitorStatus::Up->value)->count(),
            'totalCount'    => Monitor::query()->count(),
            'sslExpiring'   => Monitor::query()->whereNotNull('ssl_expires_at')->whereDate('ssl_expires_at', '<=', $sslWarnDate)->count(),
            'avgUptime'     => round((float) Monitor::query()->whereNotNull('uptime_percent')->avg('uptime_percent'), 2),
            'incidents'     => MonitorIncident::query()
                ->with('monitor.service')
                ->latest('started_at')
                ->limit(20)
                ->get(),
            'openIncidents' => MonitorIncident::query()->whereNull('resolved_at')->count(),
        ]);
    }
}
