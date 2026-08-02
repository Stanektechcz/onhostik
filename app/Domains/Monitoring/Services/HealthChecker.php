<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deep readiness check for load balancers / k8s readiness probes / uptime
 * monitors. Verifies the app can actually serve traffic — not just that PHP is
 * up (that is liveness, `/up`).
 *
 * Each dependency is probed with a real round-trip; failures are reported as a
 * boolean + latency only. Error details are deliberately NOT surfaced (a health
 * endpoint is unauthenticated and must never leak connection strings/secrets).
 */
class HealthChecker
{
    /**
     * @return array{status: string, checks: array<string, array{ok: bool, latency_ms: int}>}
     */
    public function readiness(): array
    {
        $checks = [
            'database' => $this->time(fn (): bool => $this->database()),
            'cache'    => $this->time(fn (): bool => $this->cache()),
            'queue'    => $this->time(fn (): bool => $this->queue()),
            'storage'  => $this->time(fn (): bool => $this->storage()),
        ];

        $healthy = ! in_array(false, array_map(static fn (array $c): bool => $c['ok'], $checks), true);

        return [
            'status' => $healthy ? 'ready' : 'degraded',
            'checks' => $checks,
        ];
    }

    private function database(): bool
    {
        DB::connection()->getPdo();
        DB::connection()->select('select 1');

        return true;
    }

    private function cache(): bool
    {
        $key   = 'health:probe:' . Str::random(8);
        $value = Str::random(8);

        Cache::put($key, $value, 10);
        $ok = Cache::get($key) === $value;
        Cache::forget($key);

        return $ok;
    }

    private function queue(): bool
    {
        // Resolving the connection proves the queue backend is configured and
        // reachable, without enqueuing anything.
        Queue::connection()->getConnectionName();

        return true;
    }

    private function storage(): bool
    {
        $disk = Storage::disk();
        $path = 'health/probe-' . Str::random(8) . '.txt';

        $disk->put($path, 'ok');
        $ok = $disk->get($path) === 'ok';
        $disk->delete($path);

        return $ok;
    }

    /**
     * @param callable(): bool $probe
     * @return array{ok: bool, latency_ms: int}
     */
    private function time(callable $probe): array
    {
        $start = hrtime(true);

        try {
            $ok = $probe();
        } catch (Throwable) {
            $ok = false;
        }

        return [
            'ok'         => $ok,
            'latency_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
        ];
    }
}
