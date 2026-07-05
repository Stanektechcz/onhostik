<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Monitoring\Models\StatusPageComponent;
use App\Domains\Monitoring\Models\StatusPageMaintenance;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function __invoke(): View
    {
        // Components visible on the status page (with monitor preloaded)
        $components = StatusPageComponent::query()
            ->where('is_visible', true)
            ->with('monitor')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // If no components configured, fall back to raw monitors
        $monitors = Monitor::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'label', 'target', 'type', 'status', 'uptime_percent', 'ssl_expires_at', 'last_check_at']);

        $upCount   = $monitors->where('status', MonitorStatus::Up)->count();
        $downCount = $monitors->where('status', MonitorStatus::Down)->count();
        $total     = $monitors->count();
        $avgUptime = $total > 0
            ? round((float) $monitors->whereNotNull('uptime_percent')->avg('uptime_percent'), 2)
            : 100.0;

        $recentIncidents = MonitorIncident::query()
            ->with('monitor:id,name,label')
            ->whereNotNull('resolved_at')
            ->orderByDesc('started_at')
            ->limit(10)
            ->get();

        $openIncidents = MonitorIncident::query()
            ->with('monitor:id,name,label')
            ->whereNull('resolved_at')
            ->orderByDesc('started_at')
            ->get();

        $overallStatus = match (true) {
            $downCount === 0 => 'operational',
            $downCount < $total => 'degraded',
            default => 'outage',
        };

        // Upcoming + in-progress maintenances
        $maintenances = StatusPageMaintenance::query()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_start_at')
            ->limit(5)
            ->get();

        // 90-day daily uptime bars per monitor (indexed by monitor_id)
        $uptimeBars = $this->buildUptimeBars();

        return view('front.status', compact(
            'components', 'monitors', 'upCount', 'downCount', 'avgUptime',
            'recentIncidents', 'openIncidents', 'overallStatus',
            'maintenances', 'uptimeBars',
        ));
    }

    /** @return array<int, list<array{day: string, color: string}>> */
    private function buildUptimeBars(): array
    {
        $since = now()->subDays(90)->startOfDay();

        $rows = DB::table('monitor_checks')
            ->selectRaw('monitor_id, DATE(checked_at) as day, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as up_count, COUNT(*) as total', ['up'])
            ->where('checked_at', '>=', $since)
            ->groupBy('monitor_id', DB::raw('DATE(checked_at)'))
            ->get();

        $bars = [];
        foreach ($rows as $row) {
            $total   = (int) $row->total;
            $upCount = (int) $row->up_count;
            $color   = 'success';
            if ($total > 0 && ($upCount / $total) < 0.99) {
                $color = $upCount === 0 ? 'danger' : 'warning';
            }
            $bars[(int) $row->monitor_id][] = ['day' => (string) $row->day, 'color' => $color];
        }

        return $bars;
    }
}
