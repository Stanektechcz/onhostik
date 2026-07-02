<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Notifications\MonitorDownNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Polls all active monitors: HTTP(S) ping + optional SSL cert check.
 * Auto-opens/closes incidents and notifies the customer on state change.
 * Runs every 5 minutes via scheduler.
 *
 * In mock mode (PROVISIONING_MOCK_MODE=true) no real HTTP calls are made —
 * all monitors get a simulated "up" result. This keeps the test suite clean.
 */
class RunMonitorChecksCommand extends Command
{
    protected $signature   = 'monitoring:run-checks';
    protected $description = 'Poll all active monitors (HTTP ping + SSL check), manage incidents';

    public function handle(): int
    {
        $isMock  = config('provisioning.mock_mode', true) === true;
        $checked = 0;
        $downs   = 0;

        Monitor::query()
            ->where('is_active', true)
            ->with(['service.customer.user', 'incidents' => fn ($q) => $q->whereNull('resolved_at')])
            ->each(function (Monitor $monitor) use ($isMock, &$checked, &$downs): void {
                $check = $isMock
                    ? $this->mockCheck($monitor)
                    : $this->realCheck($monitor);

                $wasDown = $monitor->status === MonitorStatus::Down;
                $isDown  = $check['status'] === MonitorStatus::Down;

                $monitor->checks()->create([
                    'status'      => $check['status']->value,
                    'response_ms' => $check['response_ms'],
                    'error'       => $check['error'] ?? null,
                    'checked_at'  => now(),
                ]);

                $monitor->update([
                    'status'        => $check['status'],
                    'last_check_at' => now(),
                ]);

                $this->updateUptime($monitor);
                $this->manageSslExpiry($monitor);
                $this->manageIncidents($monitor, $wasDown, $isDown, $check['error'] ?? null);

                $checked++;
                if ($isDown) {
                    $downs++;
                }
            });

        $this->info("Checked {$checked} monitor(s). Down: {$downs}.");

        return self::SUCCESS;
    }

    /** @return array{status: MonitorStatus, response_ms: int, error: string|null} */
    private function mockCheck(Monitor $monitor): array
    {
        return [
            'status'      => MonitorStatus::Up,
            'response_ms' => random_int(12, 80),
            'error'       => null,
        ];
    }

    /** @return array{status: MonitorStatus, response_ms: int, error: string|null} */
    private function realCheck(Monitor $monitor): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout(10)->withOptions(['verify' => false])->get($monitor->target);
            $ms       = (int) round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                return ['status' => MonitorStatus::Up, 'response_ms' => $ms, 'error' => null];
            }

            return [
                'status'      => MonitorStatus::Down,
                'response_ms' => $ms,
                'error'       => "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'status'      => MonitorStatus::Down,
                'response_ms' => $ms,
                'error'       => mb_substr($e->getMessage(), 0, 490),
            ];
        }
    }

    private function updateUptime(Monitor $monitor): void
    {
        $total = $monitor->checks()->where('checked_at', '>=', now()->subDays(30))->count();

        if ($total === 0) {
            return;
        }

        $up = $monitor->checks()
            ->where('checked_at', '>=', now()->subDays(30))
            ->where('status', MonitorStatus::Up->value)
            ->count();

        $monitor->update(['uptime_percent' => round($up / $total * 100, 2)]);
    }

    private function manageSslExpiry(Monitor $monitor): void
    {
        if ($monitor->type !== 'ssl' && !str_starts_with($monitor->target ?? '', 'https://')) {
            return;
        }

        // Only re-check SSL every 24h to avoid hammering cert servers
        if ($monitor->ssl_expires_at !== null &&
            $monitor->last_check_at?->greaterThan(now()->subHours(23))) {
            return;
        }

        if (config('provisioning.mock_mode', true) === true) {
            if ($monitor->ssl_expires_at === null) {
                $monitor->update(['ssl_expires_at' => now()->addDays(90)->toDateString()]);
            }
            return;
        }

        try {
            $host   = parse_url($monitor->target, PHP_URL_HOST) ?? '';
            $ctx    = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $stream = @stream_socket_client(
                "ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx
            );

            if ($stream !== false) {
                $params  = stream_context_get_params($stream);
                $cert    = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '');
                $validTo = $cert['validTo_time_t'] ?? null;

                if ($validTo !== null) {
                    $monitor->update(['ssl_expires_at' => date('Y-m-d', $validTo)]);
                }

                fclose($stream);
            }
        } catch (\Throwable) {
            // SSL check is non-fatal
        }
    }

    private function manageIncidents(Monitor $monitor, bool $wasDown, bool $isDown, ?string $error): void
    {
        $openIncident = $monitor->incidents->first(fn ($i) => $i->isOpen());

        if ($isDown && ! $wasDown) {
            // Transition: UP → DOWN — open new incident
            $incident = $monitor->incidents()->create([
                'severity'   => 'major',
                'reason'     => $error ?? 'Monitor unreachable',
                'started_at' => now(),
            ]);

            activity('monitoring')
                ->performedOn($monitor)
                ->withProperties(['incident_id' => $incident->id, 'reason' => $error])
                ->log('monitoring.incident_opened');

            // Notify customer
            try {
                $monitor->service?->customer?->user?->notify(
                    new MonitorDownNotification($monitor, $error ?? '')
                );
            } catch (\Throwable) {}

            return;
        }

        if (! $isDown && $wasDown && $openIncident !== null) {
            // Transition: DOWN → UP — resolve incident
            $openIncident->update(['resolved_at' => now()]);

            activity('monitoring')
                ->performedOn($monitor)
                ->withProperties(['incident_id' => $openIncident->id, 'downtime_minutes' =>
                    $openIncident->started_at !== null ? $openIncident->started_at->diffInMinutes(now()) : null])

                ->log('monitoring.incident_resolved');
        }
    }
}
