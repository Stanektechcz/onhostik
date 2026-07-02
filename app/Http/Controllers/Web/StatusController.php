<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function __invoke(): View
    {
        $monitors = Monitor::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'label', 'target', 'type', 'status', 'uptime_percent', 'ssl_expires_at', 'last_check_at']);

        $upCount   = $monitors->where('status', MonitorStatus::Up->value)->count();
        $downCount = $monitors->where('status', MonitorStatus::Down->value)->count();
        $total     = $monitors->count();
        $avgUptime = $total > 0
            ? round((float) $monitors->whereNotNull('uptime_percent')->avg('uptime_percent'), 2)
            : 100.0;

        $recentIncidents = MonitorIncident::query()
            ->with('monitor:id,label')
            ->whereNotNull('resolved_at')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $openIncidents = MonitorIncident::query()
            ->with('monitor:id,label')
            ->whereNull('resolved_at')
            ->orderByDesc('started_at')
            ->get();

        $overallStatus = match (true) {
            $downCount === 0 => 'operational',
            $downCount < $total => 'degraded',
            default => 'outage',
        };

        return view('front.status', compact(
            'monitors', 'upCount', 'downCount', 'avgUptime',
            'recentIncidents', 'openIncidents', 'overallStatus',
        ));
    }
}
