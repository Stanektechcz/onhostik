<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class SlaController extends Controller
{
    public function index(): View
    {
        $now    = Carbon::now();
        $ago30  = $now->copy()->subDays(30);
        $ago90  = $now->copy()->subDays(90);

        // All monitors with eager-loaded relations
        $monitors = Monitor::query()
            ->with(['service.customer', 'incidents' => function ($q) use ($ago90): void {
                $q->where('started_at', '>=', $ago90)->orderBy('started_at');
            }])
            ->get();

        // Per-monitor SLA stats
        $stats = $monitors->map(function (Monitor $monitor) use ($now, $ago30, $ago90): array {
            $incidents    = $monitor->incidents;
            $window30     = $incidents->filter(fn ($i) => $i->started_at >= $ago30);
            $window90     = $incidents;

            $downtimeMins30 = $this->totalDowntimeMinutes($window30, $ago30, $now);
            $downtimeMins90 = $this->totalDowntimeMinutes($window90, $ago90, $now);

            $total30    = 30 * 24 * 60;
            $total90    = 90 * 24 * 60;
            $uptime30   = round(max(0, ($total30 - $downtimeMins30) / $total30 * 100), 3);
            $uptime90   = round(max(0, ($total90 - $downtimeMins90) / $total90 * 100), 3);

            $resolvedIn30 = $window30->filter(fn ($i) => $i->resolved_at !== null);
            $mttr = $resolvedIn30->isNotEmpty()
                ? round($resolvedIn30->avg(fn ($i) => $i->started_at->diffInMinutes($i->resolved_at)))
                : null;

            return [
                'monitor'        => $monitor,
                'service'        => $monitor->service,
                'customer'       => $monitor->service?->customer,
                'uptime30'       => $uptime30,
                'uptime90'       => $uptime90,
                'incidents30'    => $window30->count(),
                'openIncidents'  => $window30->filter(fn ($i) => $i->resolved_at === null)->count(),
                'mttr'           => $mttr, // in minutes
            ];
        });

        // All open incidents for the banner
        $openIncidents = MonitorIncident::query()
            ->with('monitor.service.customer')
            ->whereNull('resolved_at')
            ->orderBy('started_at')
            ->get();

        // Aggregate platform SLA
        $allUptimes30 = $stats->pluck('uptime30')->filter(fn ($v) => $v > 0);
        $platformSla30 = $allUptimes30->isNotEmpty() ? round($allUptimes30->avg(), 3) : null;
        $allUptimes90 = $stats->pluck('uptime90')->filter(fn ($v) => $v > 0);
        $platformSla90 = $allUptimes90->isNotEmpty() ? round($allUptimes90->avg(), 3) : null;

        // Recent closed incidents (30d, for timeline)
        $recentClosed = MonitorIncident::query()
            ->with('monitor.service.customer')
            ->whereNotNull('resolved_at')
            ->where('started_at', '>=', $ago30)
            ->orderByDesc('started_at')
            ->limit(30)
            ->get();

        return view('admin.sla', [
            'stats'         => $stats,
            'openIncidents' => $openIncidents,
            'recentClosed'  => $recentClosed,
            'platformSla30' => $platformSla30,
            'platformSla90' => $platformSla90,
        ]);
    }

    /**
     * Calculates total downtime minutes from a collection of incidents within a window.
     *
     * @param \Illuminate\Support\Collection<int, MonitorIncident> $incidents
     */
    private function totalDowntimeMinutes(
        \Illuminate\Support\Collection $incidents,
        Carbon $windowStart,
        Carbon $windowEnd
    ): float {
        $total = 0.0;

        foreach ($incidents as $incident) {
            $start = $incident->started_at < $windowStart ? $windowStart : $incident->started_at;
            $end   = $incident->resolved_at === null
                ? $windowEnd
                : ($incident->resolved_at > $windowEnd ? $windowEnd : $incident->resolved_at);

            if ($end > $start) {
                $total += $start->diffInMinutes($end);
            }
        }

        return $total;
    }
}
