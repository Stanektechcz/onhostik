<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Services;

use App\Domains\Monitoring\Models\SlaIncident;
use App\Domains\Monitoring\Models\SlaIncidentUpdate;
use App\Domains\Monitoring\Models\SlaTier;
use App\Domains\Monitoring\Models\SlaUptimeCheck;
use App\Domains\Provisioning\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

final class SlaService
{
    public function recordCheck(Service $service, string $status, ?int $responseMs = null, ?string $error = null): SlaUptimeCheck
    {
        return SlaUptimeCheck::create([
            'service_id'   => $service->id,
            'status'       => $status,
            'response_ms'  => $responseMs,
            'check_url'    => $service->sla_check_url,
            'error_message'=> $error,
            'checked_at'   => now(),
        ]);
    }

    /**
     * @return array{uptime_percent: float, total_checks: int, down_checks: int, avg_response_ms: float|null}
     */
    public function uptimeStats(Service $service, Carbon $from, Carbon $to): array
    {
        $checks = SlaUptimeCheck::where('service_id', $service->id)
            ->whereBetween('checked_at', [$from, $to])
            ->get();

        $total = $checks->count();
        if ($total === 0) {
            return ['uptime_percent' => 100.0, 'total_checks' => 0, 'down_checks' => 0, 'avg_response_ms' => null];
        }

        $down    = $checks->where('status', 'down')->count();
        $uptime  = round((($total - $down) / $total) * 100, 4);
        $avgMs   = $checks->whereNotNull('response_ms')->avg('response_ms');

        return [
            'uptime_percent' => $uptime,
            'total_checks'   => $total,
            'down_checks'    => $down,
            'avg_response_ms'=> $avgMs ? round((float) $avgMs, 1) : null,
        ];
    }

    public function openIncident(
        Service $service,
        string $title,
        string $severity,
        ?string $description = null,
        ?User $creator = null,
    ): SlaIncident {
        $incident = SlaIncident::create([
            'service_id'  => $service->id,
            'title'       => $title,
            'description' => $description,
            'severity'    => $severity,
            'status'      => 'open',
            'started_at'  => now(),
            'created_by'  => $creator?->id,
        ]);

        SlaIncidentUpdate::create([
            'incident_id' => $incident->id,
            'message'     => 'Incident byl otevřen.',
            'status'      => 'investigating',
            'created_by'  => $creator?->id,
        ]);

        return $incident;
    }

    public function addUpdate(SlaIncident $incident, string $message, string $status, ?User $updater = null): SlaIncidentUpdate
    {
        return SlaIncidentUpdate::create([
            'incident_id' => $incident->id,
            'message'     => $message,
            'status'      => $status,
            'created_by'  => $updater?->id,
        ]);
    }

    public function resolve(SlaIncident $incident, string $message, ?User $resolver = null): void
    {
        $downtimeMinutes = (int) $incident->started_at->diffInMinutes(now());

        $incident->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'downtime_minutes' => $downtimeMinutes,
        ]);

        $this->evaluateSlaBreached($incident);

        SlaIncidentUpdate::create([
            'incident_id' => $incident->id,
            'message'     => $message,
            'status'      => 'resolved',
            'created_by'  => $resolver?->id,
        ]);
    }

    private function evaluateSlaBreached(SlaIncident $incident): void
    {
        $service = $incident->service;
        if (! $service->sla_tier_id) {
            return;
        }

        /** @var SlaTier $tier */
        $tier    = SlaTier::find($service->sla_tier_id);
        $allowed = $tier->allowedDowntimeMinutes();

        if ($incident->downtime_minutes > $allowed) {
            $excessHours = ceil(($incident->downtime_minutes - $allowed) / 60);
            $creditPct   = min(
                $tier->credit_percent_per_hour * $excessHours,
                $tier->max_credit_percent
            );
            $incident->update([
                'sla_breached' => true,
                'credit_haler' => (int) $creditPct,
            ]);
        }
    }

    /**
     * @return array{total: int, open: int, resolved: int, breached: int, total_downtime_minutes: int}
     */
    public function incidentStats(): array
    {
        $incidents = SlaIncident::all();
        return [
            'total'                  => $incidents->count(),
            'open'                   => $incidents->whereIn('status', ['open', 'investigating'])->count(),
            'resolved'               => $incidents->where('status', 'resolved')->count(),
            'breached'               => $incidents->where('sla_breached', true)->count(),
            'total_downtime_minutes' => (int) $incidents->sum('downtime_minutes'),
        ];
    }
}
