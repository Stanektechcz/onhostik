<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequestMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Domains\Models\RegistrarCreditSnapshot;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Incidents\Models\SlaProbe;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;

/**
 * Readiness (`/healthz`) for load balancers and the Prometheus exporter (`/metrics`) that feeds
 * infra/monitoring/slo-alerts.yml. Both are unauthenticated but the exporter requires the metrics
 * token (`ONHOST_METRICS_TOKEN`) unless the caller is on the allow-list.
 */
final class HealthController extends Controller
{
    public function healthz(): JsonResponse
    {
        $checks = [];
        $ok = true;
        foreach (['database' => fn () => DB::select('select 1'), 'cache' => fn () => Cache::put('healthz', now()->timestamp, 30) && Cache::get('healthz') !== null] as $name => $probe) {
            $started = microtime(true);
            try {
                $probe();
                $checks[$name] = ['ok' => true, 'ms' => (int) round((microtime(true) - $started) * 1000)];
            } catch (\Throwable $e) {
                $ok = false;
                $checks[$name] = ['ok' => false, 'error' => substr($e->getMessage(), 0, 120)];
            }
        }
        $outboxLag = $this->outboxLagSeconds();
        $checks['outbox'] = ['ok' => $outboxLag < 300, 'oldest_pending_seconds' => $outboxLag];
        $checks['scheduler'] = ['ok' => true, 'last_tick' => Cache::get('onhost:scheduler:last_tick')];

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks, 'version' => (string) config('onhost.version', '4.0'), 'at' => now()->toIso8601String()], $ok ? 200 : 503);
    }

    public function metrics(Request $request): Response
    {
        $token = (string) config('onhost.metrics.token', '');
        $provided = (string) ($request->bearerToken() ?? $request->query('token', ''));
        $allowed = in_array($request->ip(), (array) config('onhost.metrics.allow_ips', ['127.0.0.1', '::1']), true);
        if (! $allowed && ($token === '' || ! hash_equals($token, $provided))) {
            throw new DomainError('metrics_unauthorized', 'Metrics token required.', 401);
        }
        $lines = [];
        $emit = function (string $name, string $type, string $help, array $samples) use (&$lines): void {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} {$type}";
            foreach ($samples as [$labels, $value]) {
                $l = $labels === [] ? '' : '{'.implode(',', array_map(fn ($k, $v) => $k.'="'.addcslashes((string) $v, '"\\').'"', array_keys($labels), $labels)).'}';
                $lines[] = "{$name}{$l} ".(is_float($value) ? sprintf('%.6f', $value) : (string) $value);
            }
        };

        $http = [];
        $latency = [];
        foreach (RequestMetrics::FAMILIES as $family) {
            foreach (['2xx', '3xx', '4xx', '5xx'] as $class) {
                $http[] = [['family' => $family, 'status' => $class], (int) Cache::get("metrics:http:{$family}:{$class}", 0)];
            }
            $n = (int) Cache::get("metrics:http:{$family}:n", 0);
            $latency[] = [['family' => $family], $n > 0 ? round(((int) Cache::get("metrics:http:{$family}:ms", 0)) / $n, 2) : 0.0];
        }
        $emit('onhost_http_requests_total', 'counter', 'HTTP requests by family and status class', $http);
        $emit('onhost_http_request_avg_ms', 'gauge', 'Average request duration since process start', $latency);
        $emit('onhost_outbox_pending_oldest_seconds', 'gauge', 'Age of the oldest unpublished outbox message', [[[], $this->outboxLagSeconds()]]);
        $emit('onhost_outbox_pending_total', 'gauge', 'Unpublished outbox messages', [[[], (int) DB::table('outbox_messages')->whereNull('published_at')->count()]]);

        $ops = DB::table('operations')->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state');
        $emit('onhost_operations_total', 'gauge', 'Provisioning operations by state', array_map(fn ($state, $n) => [['state' => $state], (int) $n], array_keys($ops->all()), $ops->all()) ?: [[['state' => 'none'], 0]]);
        $emit('onhost_queue_jobs_pending', 'gauge', 'Jobs waiting in the database queue', [[[], (int) (DB::getSchemaBuilder()->hasTable('jobs') ? DB::table('jobs')->count() : 0)]]);
        $backlog = app(AutomationLedger::class)->backlog();
        $emit('onhost_operations_backlog', 'gauge', 'Operations due for longer than the age limit, by queue (autoscaling signal)', array_map(fn ($queue, $n) => [['queue' => $queue], (int) $n], array_keys($backlog['by_queue']), $backlog['by_queue']) ?: [[['queue' => 'none'], 0]]);
        $emit('onhost_operations_backlog_threshold', 'gauge', 'Backlog size that raises platform.queue.backlog', [[[], (int) $backlog['threshold']]]);
        $emit('onhost_queue_jobs_failed_total', 'gauge', 'Failed jobs', [[[], (int) (DB::getSchemaBuilder()->hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0)]]);

        $instances = ProviderInstance::query()->get()->keyBy('id');
        $health = IntegrationHealth::query()->get();
        $providerSamples = [];
        foreach ($health as $h) {
            $i = $instances->get($h->provider_instance_id);
            if ($i === null) {
                continue;
            }
            $providerSamples[] = [['instance_key' => $i->key, 'provider' => $i->provider, 'state' => $h->up ? 'up' : 'down'], 1];
            $providerSamples[] = [['instance_key' => $i->key, 'provider' => $i->provider, 'state' => $h->up ? 'down' : 'up'], 0];
        }
        $emit('onhost_provider_health', 'gauge', 'Provider instance health (1 for the current state)', $providerSamples ?: [[['instance_key' => 'none', 'provider' => 'none', 'state' => 'up'], 0]]);
        $emit('onhost_provider_error_rate_1h', 'gauge', 'Provider call error rate over the last hour (percent)', $health->map(fn ($h) => [['instance_key' => $instances->get($h->provider_instance_id)?->key ?? 'unknown'], (float) $h->error_rate_1h])->all() ?: [[['instance_key' => 'none'], 0.0]]);

        $creditSamples = [];
        foreach (RegistrarCreditSnapshot::query()->orderByDesc('taken_at')->get()->unique('registrar_provider') as $snapshot) {
            $czk = $snapshot->currency === 'CZK' ? $snapshot->balance_minor / 100 : RegistrarPricing::toCzkMinor((int) $snapshot->balance_minor, (string) $snapshot->currency) / 100;
            $creditSamples[] = [['registrar' => $snapshot->registrar_provider, 'currency' => $snapshot->currency], (float) $czk];
        }
        if ($creditSamples === []) {
            $credit = Cache::get('onhost:registrar:credit');
            $creditSamples[] = [['registrar' => 'none', 'currency' => 'CZK'], is_numeric($credit) ? (float) $credit : 0.0];
        }
        $emit('onhost_registrar_credit_czk', 'gauge', 'Registrar credit converted to CZK, per registrar', $creditSamples);

        $probes = SlaProbe::query()->where('state', 'active')->get();
        $emit('onhost_probe_last_seen_timestamp', 'gauge', 'Unix time of the last report per external probe', $probes->map(fn (SlaProbe $p) => [['probe' => $p->key, 'component' => $p->component_key, 'location' => $p->location], (int) ($p->last_seen_at?->timestamp ?? 0)])->all() ?: [[['probe' => 'none', 'component' => 'none', 'location' => 'none'], 0]]);
        $components = DB::table('status_components')->pluck('state', 'key');
        $emit('onhost_status_component_operational', 'gauge', 'Public status component is operational (1) or not (0)', array_map(fn ($key, $state) => [['component' => $key, 'state' => $state], $state === 'operational' ? 1 : 0], array_keys($components->all()), $components->all()) ?: [[['component' => 'none', 'state' => 'none'], 0]]);
        $emit('onhost_open_incidents', 'gauge', 'Open incidents by severity', collect(DB::table('incidents')->whereNotIn('state', ['RESOLVED', 'POSTMORTEM'])->selectRaw('severity, count(*) as n')->groupBy('severity')->get())->map(fn ($r) => [['severity' => $r->severity], (int) $r->n])->all() ?: [[['severity' => 'none'], 0]]);
        $emit('onhost_dunning_open', 'gauge', 'Open dunning cases', [[[], (int) DB::table('dunning_cases')->whereNotIn('state', ['RESOLVED', 'TERMINATED'])->count()]]);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    private function outboxLagSeconds(): int
    {
        $oldest = DB::table('outbox_messages')->whereNull('published_at')->min('available_at');

        return $oldest === null ? 0 : max(0, (int) now()->diffInSeconds(Carbon::parse($oldest), true));
    }
}
