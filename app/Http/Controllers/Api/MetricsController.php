<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Monitoring\Services\SchedulerHeartbeat;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Prometheus metrics (/api/metrics) — operational gauges for scraping.
 *
 * Disabled (404) until METRICS_TOKEN is set, so it is never exposed by accident;
 * once set, the scraper authenticates with that token (Bearer or ?token=). Only
 * operational signals are exposed (job queue, scheduler, integration health) —
 * no revenue or customer data.
 */
final class MetricsController extends Controller
{
    public function __invoke(Request $request, SchedulerHeartbeat $heartbeat): Response
    {
        $token = (string) config('metrics.token', '');

        abort_if($token === '', 404);

        $provided = $request->bearerToken() ?? (string) $request->query('token', '');
        abort_unless(hash_equals($token, $provided), 403);

        return response($this->render($heartbeat), 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }

    private function render(SchedulerHeartbeat $heartbeat): string
    {
        $failed  = (int) DB::table('failed_jobs')->count();
        $pending = (int) DB::table('jobs')->count();

        $integrations   = IntegrationSetting::query()->get();
        $healthy        = $integrations->filter(fn (IntegrationSetting $i): bool => $i->healthStatus() === 'healthy')->count();
        $errored        = $integrations->filter(fn (IntegrationSetting $i): bool => $i->healthStatus() === 'error')->count();
        $schedulerStale = $heartbeat->isStale() ? 1 : 0;

        $gauges = [
            'onhost_up'                     => ['Application is serving.', 1],
            'onhost_database_up'            => ['Database reachable.', 1], // reached here → the queries above worked
            'onhost_failed_jobs_total'      => ['Jobs in the failed_jobs table.', $failed],
            'onhost_pending_jobs_total'     => ['Jobs waiting in the queue table.', $pending],
            'onhost_scheduler_stale'        => ['1 when the scheduler heartbeat is stale (cron likely down).', $schedulerStale],
            'onhost_integrations_healthy'   => ['Integrations with a healthy last check.', $healthy],
            'onhost_integrations_errored'   => ['Integrations whose last check errored.', $errored],
        ];

        $lines = [];

        foreach ($gauges as $name => [$help, $value]) {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";
            $lines[] = "{$name} {$value}";
        }

        return implode("\n", $lines) . "\n";
    }
}
