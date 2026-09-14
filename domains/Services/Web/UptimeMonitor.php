<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\UptimeIncident;
use Onhost\Domain\Services\Models\UptimeMonitor as Monitor;
use Onhost\Domain\Services\Models\UptimeSample;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Uptime monitoring of customer sites from the control plane: HTTP checks on a schedule, samples kept for the
 * retention window, incidents opened after N consecutive failures and closed on recovery, both published as events
 * (`monitoring.down` / `monitoring.up`) so mail, panel and webhooks tell the customer. Every web service gets a
 * default monitor on its main address; plans with `monitoring` sell more checks (keyword, other URLs).
 */
final class UptimeMonitor
{
    public function __construct(private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit, private readonly ServiceFeatures $features) {}

    /** @return array{monitors:list<array<string,mixed>>, limit:?int, threshold:int} */
    public function status(Service $service): array
    {
        $this->ensureDefault($service);
        $limit = $this->features->features($service)['monitoring']['limit'] ?? null;
        $out = [];
        foreach (Monitor::query()->where('service_id', $service->id)->orderBy('created_at')->get() as $monitor) {
            $out[] = $this->present($monitor);
        }

        return ['monitors' => $out, 'limit' => $limit, 'threshold' => (int) config('onhost.monitoring.failures_before_down', 3)];
    }

    /** @return array<string, list<array{t:string, ok:bool, ms:?int, status:?int}>> monitor id → samples of the last 24 h (at most 288 points) */
    public function samples(Service $service, int $hours = 24): array
    {
        $out = [];
        foreach (Monitor::query()->where('service_id', $service->id)->get() as $monitor) {
            $rows = UptimeSample::query()->where('monitor_id', $monitor->id)->where('checked_at', '>=', now()->subHours($hours))->orderBy('checked_at')->get();
            $step = max(1, (int) ceil($rows->count() / 288));
            $points = [];
            foreach ($rows->values() as $i => $row) {
                if ($i % $step === 0) {
                    $points[] = ['t' => $row->checked_at->toIso8601String(), 'ok' => (bool) $row->ok, 'ms' => $row->ms, 'status' => $row->status];
                }
            }
            $out[$monitor->id] = $points;
        }

        return $out;
    }

    /** Create or update a monitor within the plan's limit. @param array<string,mixed> $input */
    public function configure(Service $service, array $input, CommandContext $context): Monitor
    {
        $feature = $this->features->features($service)['monitoring'] ?? ['enabled' => false];
        if (empty($feature['enabled'])) {
            throw new DomainError('feature_unavailable', 'Monitoring is not available for this service.', 422);
        }
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            $url = 'https://'.$this->siteDomain($service).'/';
        }
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#', $url) || strlen($url) > 500) {
            throw new DomainError('action_param_invalid', 'url must be an http(s) address.', 422, ['field' => 'url']);
        }
        $monitor = isset($input['id']) ? Monitor::query()->where('service_id', $service->id)->find((string) $input['id']) : null;
        if ($monitor === null) {
            $limit = (int) ($feature['limit'] ?? 1);
            if ($limit > 0 && Monitor::query()->where('service_id', $service->id)->count() >= $limit) {
                throw new DomainError('feature_limit_reached', "The plan allows {$limit} monitors.", 422, ['limit' => $limit]);
            }
            $monitor = new Monitor(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'state' => 'pending', 'consecutive_failures' => 0]);
        }
        $monitor->forceFill([
            'url' => $url, 'interval_seconds' => max(60, min(3600, (int) ($input['interval_seconds'] ?? $monitor->interval_seconds ?? 300))),
            'expected_status' => max(100, min(599, (int) ($input['expected_status'] ?? $monitor->expected_status ?? 200))), 'keyword' => isset($input['keyword']) ? substr(trim((string) $input['keyword']), 0, 120) ?: null : $monitor->keyword,
            'timeout_seconds' => max(3, min(30, (int) ($input['timeout_seconds'] ?? $monitor->timeout_seconds ?? 10))), 'enabled' => array_key_exists('enabled', $input) ? filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN) : ($monitor->enabled ?? true),
            'notify' => array_key_exists('notify', $input) ? filter_var($input['notify'], FILTER_VALIDATE_BOOLEAN) : ($monitor->notify ?? true), 'next_check_at' => now(),
        ])->save();
        $this->audit->record($context->withScope($service->organization_id), 'service.monitoring.configure', 'succeeded', ['monitor_id' => $monitor->id, 'url' => $url], 'service', $service->id);

        return $monitor;
    }

    public function delete(Service $service, string $monitorId, CommandContext $context): void
    {
        $monitor = Monitor::query()->where('service_id', $service->id)->find($monitorId);
        if ($monitor === null) {
            throw DomainError::notFound('monitor');
        }
        UptimeSample::query()->where('monitor_id', $monitor->id)->delete();
        UptimeIncident::query()->where('monitor_id', $monitor->id)->delete();
        $monitor->delete();
        $this->audit->record($context->withScope($service->organization_id), 'service.monitoring.delete', 'succeeded', ['monitor_id' => $monitorId], 'service', $service->id);
    }

    /** Every active web service watches its main address unless the customer removed the check. */
    public function ensureDefault(Service $service): ?Monitor
    {
        if (! in_array($service->family, ['web', 'managed'], true) || $service->state !== ServiceStateMachine::ACTIVE) {
            return null;
        }
        if (Monitor::query()->where('service_id', $service->id)->exists() || ! empty($service->meta['monitoring_default_removed'])) {
            return Monitor::query()->where('service_id', $service->id)->first();
        }

        return Monitor::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'url' => 'https://'.$this->siteDomain($service).'/', 'interval_seconds' => 300, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'pending', 'consecutive_failures' => 0, 'next_check_at' => now()]);
    }

    /** Run the checks that are due; returns how many ran. */
    public function checkDue(int $limit = 200): int
    {
        $due = Monitor::query()->where('enabled', true)->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()))->orderBy('next_check_at')->limit($limit)->get();
        foreach ($due as $monitor) {
            $this->check($monitor);
        }

        return $due->count();
    }

    public function check(Monitor $monitor): UptimeSample
    {
        $started = hrtime(true);
        $ok = false;
        $status = null;
        $error = null;
        try {
            $response = Http::withUserAgent((string) config('onhost.monitoring.user_agent', 'ONhost-Uptime/1.0'))->timeout((int) $monitor->timeout_seconds)->connectTimeout(5)->withoutVerifying()->get($monitor->url);
            $status = $response->status();
            $ok = $status === (int) $monitor->expected_status;
            if ($ok && $monitor->keyword !== null && $monitor->keyword !== '' && ! str_contains($response->body(), $monitor->keyword)) {
                $ok = false;
                $error = 'keyword not found';
            } elseif (! $ok) {
                $error = "HTTP {$status}";
            }
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
        }
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);
        $sample = UptimeSample::query()->create(['monitor_id' => $monitor->id, 'checked_at' => now(), 'ok' => $ok, 'status' => $status, 'ms' => $ms, 'error' => $error]);
        $threshold = (int) config('onhost.monitoring.failures_before_down', 3);
        $failures = $ok ? 0 : (int) $monitor->consecutive_failures + 1;
        $previous = (string) $monitor->state;
        $state = $ok ? 'up' : ($failures >= $threshold ? 'down' : ($previous === 'down' ? 'down' : ($previous === 'pending' ? 'pending' : 'up')));
        $monitor->forceFill(['last_checked_at' => now(), 'next_check_at' => now()->addSeconds((int) $monitor->interval_seconds), 'last_status' => $status, 'last_ms' => $ms, 'last_error' => $error, 'consecutive_failures' => $failures, 'state' => $state])->save();

        if ($state === 'down' && $previous !== 'down') {
            $incident = UptimeIncident::query()->create(['monitor_id' => $monitor->id, 'service_id' => $monitor->service_id, 'started_at' => now(), 'cause' => $error, 'notified' => (bool) $monitor->notify]);
            $this->outbox->publish(GenericEvent::of('monitoring.down', 'service', $monitor->service_id, ['monitor_id' => $monitor->id, 'incident_id' => $incident->id, 'url' => $monitor->url, 'error' => $error, 'notify' => (bool) $monitor->notify], $monitor->organization_id));
        }
        if ($state === 'up' && $previous === 'down') {
            $incident = UptimeIncident::query()->where('monitor_id', $monitor->id)->whereNull('resolved_at')->orderByDesc('started_at')->first();
            $minutes = $incident !== null ? (int) now()->diffInMinutes($incident->started_at, true) : 0;
            $incident?->forceFill(['resolved_at' => now()])->save();
            $this->outbox->publish(GenericEvent::of('monitoring.up', 'service', $monitor->service_id, ['monitor_id' => $monitor->id, 'incident_id' => $incident?->id, 'url' => $monitor->url, 'minutes' => $minutes, 'notify' => (bool) $monitor->notify], $monitor->organization_id));
        }

        return $sample;
    }

    /** Samples older than the retention window and stale incidents are removed; returns rows deleted. */
    public function prune(): int
    {
        $days = (int) config('onhost.monitoring.retention_days', 30);

        return UptimeSample::query()->where('checked_at', '<', now()->subDays($days))->delete() + UptimeIncident::query()->whereNotNull('resolved_at')->where('resolved_at', '<', now()->subDays($days * 3))->delete();
    }

    /** @return array<string,mixed> */
    private function present(Monitor $monitor): array
    {
        $windows = [];
        foreach ([24 => '24h', 168 => '7d', 720 => '30d'] as $hours => $label) {
            $row = DB::table('uptime_samples')->where('monitor_id', $monitor->id)->where('checked_at', '>=', now()->subHours($hours))->selectRaw('count(*) as total, sum(case when ok then 1 else 0 end) as up, avg(ms) as avg_ms')->first();
            $total = (int) ($row->total ?? 0);
            $windows[$label] = ['uptime_pct' => $total > 0 ? round(((int) $row->up) / $total * 100, 3) : null, 'avg_ms' => $total > 0 ? (int) round((float) $row->avg_ms) : null, 'checks' => $total];
        }
        $incidents = UptimeIncident::query()->where('monitor_id', $monitor->id)->orderByDesc('started_at')->limit(10)->get()->map(fn (UptimeIncident $i) => ['id' => $i->id, 'started_at' => $i->started_at?->toIso8601String(), 'resolved_at' => $i->resolved_at?->toIso8601String(), 'cause' => $i->cause, 'minutes' => $i->resolved_at !== null ? (int) $i->resolved_at->diffInMinutes($i->started_at, true) : (int) now()->diffInMinutes($i->started_at, true)])->all();

        return [
            'id' => $monitor->id, 'url' => $monitor->url, 'interval_seconds' => $monitor->interval_seconds, 'expected_status' => $monitor->expected_status, 'keyword' => $monitor->keyword, 'timeout_seconds' => $monitor->timeout_seconds,
            'enabled' => (bool) $monitor->enabled, 'notify' => (bool) $monitor->notify, 'state' => $monitor->state, 'consecutive_failures' => $monitor->consecutive_failures, 'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
            'last_status' => $monitor->last_status, 'last_ms' => $monitor->last_ms, 'last_error' => $monitor->last_error, 'windows' => $windows, 'incidents' => $incidents,
        ];
    }

    private function siteDomain(Service $service): string
    {
        return strtolower((string) ($service->spec('domain') ?: ($service->hostname ?: $service->name)));
    }
}
