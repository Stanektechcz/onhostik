<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Providers;

use App\Domains\Monitoring\Contracts\MonitoringProviderInterface;
use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorCheck;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Uptime Kuma REST API provider (v1.23+).
 *
 * Authentication: Bearer token via UPTIME_KUMA_API_TOKEN.
 * Activated when PROVISIONING_MOCK_MODE=false and UPTIME_KUMA_URL is set.
 *
 * Kuma monitor type IDs: 1=HTTP(s), 4=Ping, 7=DNS.
 * Heartbeat status: 1=up, 0=down, 2=pending, 3=maintenance.
 */
final class UptimeKumaProvider implements MonitoringProviderInterface
{
    private string $baseUrl;
    private string $token;
    private int $timeout;
    private int $interval;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('uptimekuma.url', ''), '/');
        $this->token    = (string) config('uptimekuma.api_token', '');
        $this->timeout  = (int) config('uptimekuma.timeout', 15);
        $this->interval = (int) config('uptimekuma.interval', 60);
    }

    public function createMonitor(Service $service): Monitor
    {
        $this->assertConfigured();

        $name   = $service->label ?? "Service #{$service->id}";
        $target = 'https://' . ltrim($name, 'https://');

        try {
            $response = $this->http()->post('/api/v1/monitors', [
                'type'       => 1,
                'name'       => $name,
                'url'        => $target,
                'interval'   => $this->interval,
                'maxretries' => 2,
                'active'     => 1,
            ]);

            if (!$response->successful()) {
                throw new ProvisioningException(
                    "UptimeKuma createMonitor: HTTP {$response->status()} — {$response->body()}",
                    driver: 'uptime_kuma',
                    retryable: $response->serverError(),
                );
            }

            $data       = $response->json();
            $externalId = (string) ($data['monitorID'] ?? '');

            Log::info('UptimeKuma: monitor created', ['service_id' => $service->id, 'external_id' => $externalId]);
        } catch (RequestException $e) {
            throw new ProvisioningException(
                "UptimeKuma network error: {$e->getMessage()}",
                driver: 'uptime_kuma',
                retryable: true,
            );
        }

        return Monitor::updateOrCreate(
            ['service_id' => $service->id],
            [
                'name'          => $name,
                'type'          => 'http',
                'target'        => $target,
                'provider'      => 'uptime_kuma',
                'status'        => MonitorStatus::Unknown,
                'is_active'     => true,
                'last_check_at' => now(),
                'external_id'   => $externalId,
            ],
        );
    }

    public function checkMonitor(Monitor $monitor): MonitorCheck
    {
        $this->assertConfigured();

        if (empty($monitor->external_id)) {
            return $this->recordCheck($monitor, MonitorStatus::Unknown, null, 'No external_id — re-create monitor');
        }

        try {
            $response = $this->http()->get("/api/v1/monitors/{$monitor->external_id}");

            if (!$response->successful()) {
                return $this->recordCheck($monitor, MonitorStatus::Unknown, null, "HTTP {$response->status()}");
            }

            $data       = $response->json();
            $heartbeats = $data['heartbeatList'] ?? [];
            $latest     = is_array($heartbeats) && count($heartbeats) > 0 ? end($heartbeats) : null;

            $kumaStatus = is_array($latest) ? (int) ($latest['status'] ?? 2) : 2;
            $responseMs = is_array($latest) ? (int) ($latest['ping'] ?? 0) : 0;

            $status = match ($kumaStatus) {
                1       => MonitorStatus::Up,
                0       => MonitorStatus::Down,
                3       => MonitorStatus::Paused,
                default => MonitorStatus::Unknown,
            };

            $uptime = is_array($data['monitor'] ?? null)
                ? (float) ($data['monitor']['uptime'] ?? 0) * 100
                : null;

            $monitor->update([
                'status'         => $status,
                'last_check_at'  => now(),
                'uptime_percent' => $uptime !== null ? round($uptime, 2) : $monitor->uptime_percent,
            ]);

            return $this->recordCheck($monitor, $status, $responseMs > 0 ? $responseMs : null, null);
        } catch (RequestException $e) {
            Log::warning('UptimeKuma checkMonitor failed', ['monitor_id' => $monitor->id, 'error' => $e->getMessage()]);
            return $this->recordCheck($monitor, MonitorStatus::Unknown, null, $e->getMessage());
        }
    }

    public function deleteMonitor(Monitor $monitor): void
    {
        if (!empty($monitor->external_id)) {
            try {
                $this->http()->delete("/api/v1/monitors/{$monitor->external_id}");
            } catch (RequestException $e) {
                Log::warning('UptimeKuma deleteMonitor failed', ['external_id' => $monitor->external_id, 'error' => $e->getMessage()]);
            }
        }

        $monitor->delete();
    }

    public function getStatus(Monitor $monitor): MonitorStatus
    {
        return $monitor->status;
    }

    /** @return list<MonitorIncident> */
    public function getIncidents(Monitor $monitor): array
    {
        return array_values(
            $monitor->incidents()
                ->whereNull('resolved_at')
                ->latest('started_at')
                ->get()
                ->all()
        );
    }

    // ---------------------------------------------------------------- internals

    private function recordCheck(Monitor $monitor, MonitorStatus $status, ?int $responseMs, ?string $error): MonitorCheck
    {
        return $monitor->checks()->create([
            'status'      => $status->value,
            'response_ms' => $responseMs,
            'error'       => $error,
            'checked_at'  => now(),
        ]);
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token)
            ->timeout($this->timeout)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    private function assertConfigured(): void
    {
        if ($this->baseUrl === '' || $this->token === '') {
            throw new ProvisioningException(
                'UptimeKuma not configured — set UPTIME_KUMA_URL and UPTIME_KUMA_API_TOKEN.',
                driver: 'uptime_kuma',
                retryable: false,
            );
        }
    }
}
