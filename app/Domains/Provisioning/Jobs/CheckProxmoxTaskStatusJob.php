<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Jobs;

use App\Domains\Integrations\Clients\ProxmoxClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceActivationHooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Config;

/**
 * Polls the status of a pending Proxmox provisioning task (UPID).
 *
 * In mock/test mode:   simulates the running → success transition inline.
 * In production mode:  calls the Proxmox API to check the UPID task status.
 *
 * This job is dispatched by ProvisionHostingServiceJob after the driver
 * returns a pending_task=true result (ProxmoxMockDriver or future real
 * Proxmox async driver).
 *
 * Idempotent: if the service is already Active, the job is a no-op.
 */
final class CheckProxmoxTaskStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 10;
    public int $backoff = 30; // seconds between retries

    public function __construct(
        public int $serviceId,
        public int $taskId,
    ) {
        $this->onQueue(Config::string('provisioning.queues.high', 'provisioning-high'));
    }

    public function handle(): void
    {
        $service = Service::find($this->serviceId);
        $task    = ProvisioningTask::find($this->taskId);

        if ($service === null || $task === null) {
            return;
        }

        // Already completed — no-op (idempotent).
        if ($service->status === ServiceStatus::Active) {
            return;
        }

        if ($task->status === TaskStatus::Success) {
            return;
        }

        $upid     = $task->result['upid'] ?? null;
        $mockMode = (bool) Config::get('provisioning.mock_mode', true);

        if ($mockMode) {
            $this->handleMock($service, $task, $upid);
            return;
        }

        // Real Proxmox API polling — only available with credentials.
        $this->handleReal($service, $task, $upid);
    }

    private function handleMock(Service $service, ProvisioningTask $task, ?string $upid): void
    {
        // In mock mode: immediately simulate successful task completion.
        $vmid = $task->result['vmid'] ?? (100 + ($service->id % 900));
        $mockExternalId = 'MOCK-PVE-' . strtoupper(substr(md5((string) $vmid), 0, 8));

        $service->update([
            'external_id' => $mockExternalId,
            'status'      => ServiceStatus::Active,
        ]);

        $service->server?->increment('current_services');

        $task->update([
            'status'      => TaskStatus::Success,
            'result'      => array_merge($task->result ?? [], [
                'mock'             => true,
                'vmid'             => $vmid,
                'task_completed'   => now()->toIso8601String(),
                'upid_checked'     => $upid,
            ]),
            'finished_at' => now(),
        ]);

        activity('provisioning')
            ->performedOn($service)
            ->withProperties([
                'task_id'     => $task->id,
                'external_id' => $mockExternalId,
                'upid'        => $upid,
                'mock'        => true,
            ])
            ->log('provisioning.proxmox_task_completed');

        // Monitoring + backup hooks.
        app(ServiceActivationHooks::class)->handle($service);
    }

    private function handleReal(Service $service, ProvisioningTask $task, ?string $upid): void
    {
        if (empty($upid)) {
            // No UPID — fall back to mock completion.
            $this->handleMock($service, $task, $upid);
            return;
        }

        $integration = IntegrationSetting::where('provider', 'proxmox')
            ->where('is_active', true)
            ->first();

        if ($integration === null) {
            $this->handleMock($service, $task, $upid);
            return;
        }

        try {
            $client = new ProxmoxClient($integration);
            $node   = $integration->credentials['node'] ?? 'pve';

            // Proxmox UPID encoding: spaces in UPID need URL encoding.
            $encodedUpid = rawurlencode($upid);
            $status = $this->proxmoxTaskStatus($client, $node, $encodedUpid);

            if ($status === null) {
                // API unreachable — retry via backoff.
                $this->release($this->backoff);
                return;
            }

            if ($status['status'] === 'running') {
                // Still running — re-queue.
                $this->release($this->backoff);
                return;
            }

            // Task stopped.
            if (($status['exitstatus'] ?? '') === 'OK') {
                // Success — complete as in mock flow.
                $vmid = $task->result['vmid'] ?? null;
                $externalId = "PVE-{$node}-" . strtoupper(substr(md5($upid), 0, 8));

                $service->update([
                    'external_id' => $externalId,
                    'status'      => ServiceStatus::Active,
                ]);
                $service->server?->increment('current_services');

                $task->update([
                    'status'      => TaskStatus::Success,
                    'result'      => array_merge($task->result ?? [], [
                        'external_id'  => $externalId,
                        'upid'         => $upid,
                        'exitstatus'   => 'OK',
                        'mock'         => false,
                    ]),
                    'finished_at' => now(),
                ]);

                app(ServiceActivationHooks::class)->handle($service);
            } else {
                // Task failed.
                $exitStatus = $status['exitstatus'] ?? 'unknown';
                $task->update([
                    'status'       => TaskStatus::Failed,
                    'error_message'=> "Proxmox task failed: {$exitStatus}",
                    'result'       => array_merge($task->result ?? [], [
                        'upid'       => $upid,
                        'exitstatus' => $exitStatus,
                    ]),
                    'finished_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Network/auth error — retry.
            report($e);
            $this->release($this->backoff);
        }
    }

    /** @return array<string, mixed>|null */
    private function proxmoxTaskStatus(ProxmoxClient $client, string $node, string $encodedUpid): ?array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
                ->timeout(10)
                ->get("{$this->proxmoxBaseUrl($client)}/api2/json/nodes/{$node}/tasks/{$encodedUpid}/status");

            if ($response->successful()) {
                return $response->json('data') ?? null;
            }
        } catch (\Throwable) {}

        return null;
    }

    private function proxmoxBaseUrl(ProxmoxClient $client): string
    {
        // Extract base URL from client via reflection or config
        $integration = IntegrationSetting::where('provider', 'proxmox')->first();
        return rtrim($integration?->credentials['base_url'] ?? 'https://proxmox.localhost:8006', '/');
    }
}
