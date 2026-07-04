<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorAlert;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $sslWarnDate  = now()->addDays(30);
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

        $monitorTypes = Monitor::query()->distinct()->orderBy('type')->pluck('type');

        $uptimeGroups = [
            'excellent' => Monitor::query()->where('uptime_percent', '>=', 99.9)->count(),
            'good'      => Monitor::query()->whereBetween('uptime_percent', [99.0, 99.9])->count(),
            'poor'      => Monitor::query()->where('uptime_percent', '<', 99.0)->whereNotNull('uptime_percent')->count(),
            'unknown'   => Monitor::query()->whereNull('uptime_percent')->count(),
        ];

        $openAlerts = MonitorAlert::query()
            ->with('monitor')
            ->whereNull('resolved_at')
            ->latest('triggered_at')
            ->limit(20)
            ->get();

        return view('admin.monitoring', [
            'monitors'       => $monitors,
            'monitorTypes'   => $monitorTypes,
            'filterStatus'   => $filterStatus,
            'filterType'     => $filterType,
            'uptimeGroups'   => $uptimeGroups,
            'downCount'      => Monitor::query()->where('status', MonitorStatus::Down->value)->count(),
            'upCount'        => Monitor::query()->where('status', MonitorStatus::Up->value)->count(),
            'totalCount'     => Monitor::query()->count(),
            'sslExpiring'    => Monitor::query()->whereNotNull('ssl_expires_at')->whereDate('ssl_expires_at', '<=', $sslWarnDate)->count(),
            'avgUptime'      => round((float) Monitor::query()->whereNotNull('uptime_percent')->avg('uptime_percent'), 2),
            'incidents'      => MonitorIncident::query()
                ->with('monitor.service')
                ->latest('started_at')
                ->limit(20)
                ->get(),
            'openIncidents'  => MonitorIncident::query()->whereNull('resolved_at')->count(),
            'openAlerts'     => $openAlerts,
            'openAlertCount' => MonitorAlert::query()->whereNull('resolved_at')->count(),
        ]);
    }

    public function updateThresholds(Request $request, Monitor $monitor): RedirectResponse
    {
        $validated = $request->validate([
            'response_time_threshold_ms' => ['nullable', 'integer', 'min:1', 'max:60000'],
            'uptime_threshold_percent'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ssl_warn_days'              => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $monitor->update([
            'response_time_threshold_ms' => $validated['response_time_threshold_ms'] ?? null,
            'uptime_threshold_percent'   => $validated['uptime_threshold_percent'] ?? null,
            'ssl_warn_days'              => (int) $validated['ssl_warn_days'],
        ]);

        activity('monitoring')
            ->performedOn($monitor)
            ->causedBy($request->user())
            ->withProperties($validated)
            ->log('monitoring.thresholds_updated');

        return back()->with('status', 'Prahy alertů byly uloženy.');
    }
}
