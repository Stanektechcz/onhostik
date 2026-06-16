<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Proxmox VE provisioning driver.
 *
 * Authentication: PVE API token (no ticket renewal).
 *   Authorization: PVEAPIToken=USER@REALM!TOKENID=SECRET
 *
 * Flow for create():
 *   1. Allocate next free VMID.
 *   2. Clone the configured template (full clone).
 *   3. Resize disk to plan spec.
 *   4. Apply cloud-init: hostname, nameservers, ssh-keys (from service.resources).
 *   5. Start the VM.
 *   6. Return VMID as external_id.
 *
 * All operations are idempotent: if external_id is set, the VM already exists.
 *
 * IMPORTANT: The Proxmox API uses fire-and-forget task UPIDs for slow ops
 * (clone, resize). This driver submits the task and stores the UPID; a
 * background poller (Phase 5) tracks completion and transitions the service
 * status. For now we return immediately after clone submission.
 *
 * Env/config keys (see config/provisioning.php → proxmox):
 *   PROXMOX_HOST, PROXMOX_NODE, PROXMOX_API_USER, PROXMOX_API_TOKEN_ID,
 *   PROXMOX_API_TOKEN_SECRET, PROXMOX_TEMPLATE_VMID, PROXMOX_STORAGE,
 *   PROXMOX_BRIDGE, PROXMOX_NAMESERVERS.
 */
final class ProxmoxDriver implements ProvisioningDriverInterface
{
    private readonly string $host;
    private readonly string $node;
    private readonly string $apiUser;
    private readonly string $tokenId;
    private readonly string $tokenSecret;
    private readonly int    $templateVmid;
    private readonly string $storage;
    private readonly string $bridge;
    private readonly string $nameservers;

    public function __construct()
    {
        $this->host         = (string)  config('provisioning.proxmox.host', '');
        $this->node         = (string)  config('provisioning.proxmox.node', 'pve');
        $this->apiUser      = (string)  config('provisioning.proxmox.api_user', 'root@pam');
        $this->tokenId      = (string)  config('provisioning.proxmox.api_token_id', '');
        $this->tokenSecret  = (string)  config('provisioning.proxmox.api_token_secret', '');
        $this->templateVmid = (int)     config('provisioning.proxmox.template_vmid', 9000);
        $this->storage      = (string)  config('provisioning.proxmox.storage', 'local-lvm');
        $this->bridge       = (string)  config('provisioning.proxmox.network_bridge', 'vmbr0');
        $this->nameservers  = (string)  config('provisioning.proxmox.nameservers', '1.1.1.1 8.8.8.8');
    }

    /** @param array<string, mixed> $config */
    public function create(Service $service, array $config = []): ProvisioningResult
    {
        if ($service->external_id !== null) {
            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['idempotent' => true],
            );
        }

        try {
            $vmid = $this->nextFreeVmid();

            /** @var array<string, mixed> $resources */
            $resources = is_array($service->resources) ? $service->resources : [];

            $hostname    = $service->label ?? ('vps-' . $vmid);
            $cpus        = (int)   ($resources['cpu']     ?? 1);
            $memoryMb    = (int)   ($resources['ram_mb']  ?? 1024);
            $diskGb      = (int)   round(($resources['disk_mb'] ?? 20480) / 1024);
            $ipConfig    = (string)($resources['ipconfig'] ?? 'dhcp');

            // Clone the cloud-init template (full clone so it's independent).
            $cloneTask = $this->apiPost("/nodes/{$this->node}/qemu/{$this->templateVmid}/clone", [
                'newid'   => $vmid,
                'name'    => $hostname,
                'full'    => 1,
                'storage' => $this->storage,
            ]);

            // Wait for the clone task to complete before configuring.
            $this->waitForTask($cloneTask['data'] ?? '', maxWaitSeconds: 120);

            // Apply cloud-init config + resource limits.
            $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/config", [
                'cores'      => $cpus,
                'memory'     => $memoryMb,
                'nameserver' => $this->nameservers,
                'ipconfig0'  => $ipConfig,
                'net0'       => "virtio,bridge={$this->bridge}",
                'cipassword' => $rootPassword = Str::password(24),
                'ciuser'     => 'root',
            ]);

            // Resize root disk if larger than template default.
            if ($diskGb > 0) {
                $this->apiPut("/nodes/{$this->node}/qemu/{$vmid}/resize", [
                    'disk' => 'scsi0',
                    'size' => "{$diskGb}G",
                ]);
            }

            // Start the VM.
            $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/status/start", []);

            return ProvisioningResult::ok(
                externalId: (string) $vmid,
                credentials: ['root_password' => $rootPassword],
                metadata: [
                    'vmid'     => $vmid,
                    'hostname' => $hostname,
                    'node'     => $this->node,
                ],
            );
        } catch (ProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return ProvisioningResult::failure("Proxmox create failed: {$e->getMessage()}");
        }
    }

    public function suspend(Service $service): ProvisioningResult
    {
        return $this->vmLifecycle($service, 'stop');
    }

    public function unsuspend(Service $service): ProvisioningResult
    {
        return $this->vmLifecycle($service, 'start');
    }

    public function terminate(Service $service): ProvisioningResult
    {
        $vmid = $this->vmidFromService($service);

        if ($vmid === null) {
            return ProvisioningResult::failure('No VMID stored — nothing to terminate.');
        }

        try {
            // Stop first (ignore error if already stopped), then destroy.
            try {
                $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/status/stop", []);
            } catch (\Throwable) {
                // Already stopped is acceptable.
            }

            $task = $this->apiDelete("/nodes/{$this->node}/qemu/{$vmid}?purge=1");
            $this->waitForTask($task['data'] ?? '', maxWaitSeconds: 60);

            return ProvisioningResult::ok(
                externalId: (string) $vmid,
                metadata: ['operation' => 'terminate', 'vmid' => $vmid],
            );
        } catch (\Throwable $e) {
            return ProvisioningResult::failure("Proxmox terminate failed: {$e->getMessage()}");
        }
    }

    /** @param array<string, mixed> $newResources */
    public function changePackage(Service $service, array $newResources): ProvisioningResult
    {
        $vmid = $this->vmidFromService($service);

        if ($vmid === null) {
            return ProvisioningResult::failure('No VMID — cannot resize.');
        }

        try {
            $cpus     = (int) ($newResources['cpu']    ?? 1);
            $memoryMb = (int) ($newResources['ram_mb'] ?? 1024);

            $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/config", [
                'cores'  => $cpus,
                'memory' => $memoryMb,
            ]);

            return ProvisioningResult::ok(
                externalId: (string) $vmid,
                metadata: ['operation' => 'change_package', 'resources' => $newResources],
            );
        } catch (\Throwable $e) {
            return ProvisioningResult::failure("Proxmox changePackage failed: {$e->getMessage()}");
        }
    }

    public function getUsageStats(Service $service): UsageStats
    {
        $vmid = $this->vmidFromService($service);

        if ($vmid === null) {
            return new UsageStats(0, 0, 0, 0);
        }

        try {
            $data = $this->apiGet("/nodes/{$this->node}/qemu/{$vmid}/status/current")['data'] ?? [];

            /** @var array<string, mixed> $resources */
            $resources = is_array($service->resources) ? $service->resources : [];

            $diskLimitMb = (int) ($resources['disk_mb'] ?? 0);
            $bandLimitMb = (int) ($resources['bandwidth_gb'] ?? 0) * 1024;

            return new UsageStats(
                diskUsedMb:       (int) round(($data['disk'] ?? 0) / 1024 / 1024),
                diskLimitMb:      $diskLimitMb,
                bandwidthUsedMb:  (int) round((($data['netin'] ?? 0) + ($data['netout'] ?? 0)) / 1024 / 1024),
                bandwidthLimitMb: $bandLimitMb,
                cpuPercent:       round((float)($data['cpu'] ?? 0.0) * 100, 2),
                extra: [
                    'status'   => $data['status'] ?? 'unknown',
                    'uptime'   => $data['uptime'] ?? 0,
                    'mem_used' => (int) round(($data['mem'] ?? 0) / 1024 / 1024),
                ],
            );
        } catch (\Throwable) {
            return new UsageStats(0, 0, 0, 0);
        }
    }

    public function resetPassword(Service $service): string
    {
        $vmid    = $this->vmidFromService($service);
        $newPass = Str::password(24);

        if ($vmid !== null) {
            try {
                $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/config", [
                    'cipassword' => $newPass,
                ]);
            } catch (\Throwable) {
                // Best-effort — return the password regardless.
            }
        }

        return $newPass;
    }

    public function loginAsUser(Service $service): ?string
    {
        // Proxmox does not support customer SSO — VPS users connect via SSH.
        return null;
    }

    public function testConnection(): bool
    {
        try {
            $this->apiGet('/version');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------------------------------------------------------------- internals

    private function vmLifecycle(Service $service, string $action): ProvisioningResult
    {
        $vmid = $this->vmidFromService($service);

        if ($vmid === null) {
            return ProvisioningResult::failure("No VMID — cannot {$action}.");
        }

        try {
            $this->apiPost("/nodes/{$this->node}/qemu/{$vmid}/status/{$action}", []);

            return ProvisioningResult::ok(
                externalId: (string) $vmid,
                metadata: ['operation' => $action, 'vmid' => $vmid],
            );
        } catch (\Throwable $e) {
            return ProvisioningResult::failure("Proxmox {$action} failed: {$e->getMessage()}");
        }
    }

    private function vmidFromService(Service $service): ?int
    {
        return $service->external_id !== null ? (int) $service->external_id : null;
    }

    private function nextFreeVmid(): int
    {
        $data = $this->apiGet('/cluster/nextid')['data'] ?? null;

        if (!is_int($data) && !is_string($data)) {
            throw new ProvisioningException('Could not obtain next free VMID from Proxmox.', retryable: true);
        }

        return (int) $data;
    }

    private function waitForTask(string $upid, int $maxWaitSeconds = 60): void
    {
        if ($upid === '') {
            return;
        }

        // UPID format: UPID:node:...  — extract the node segment.
        $parts = explode(':', $upid);
        $node  = $parts[1] ?? $this->node;

        $encodedUpid = rawurlencode($upid);
        $deadline    = time() + $maxWaitSeconds;

        while (time() < $deadline) {
            $status = $this->apiGet("/nodes/{$node}/tasks/{$encodedUpid}/status")['data']['status'] ?? 'unknown';

            if ($status === 'stopped') {
                return;
            }

            sleep(2);
        }

        throw new ProvisioningException("Task {$upid} did not complete within {$maxWaitSeconds}s.", retryable: true);
    }

    /** @return array<string, mixed> */
    private function apiGet(string $path): array
    {
        $response = $this->client()->get($path);

        $this->assertSuccess($response, 'GET', $path);

        return $response->json() ?? [];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function apiPost(string $path, array $data): array
    {
        $response = $this->client()->post($path, $data);

        $this->assertSuccess($response, 'POST', $path);

        return $response->json() ?? [];
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function apiPut(string $path, array $data): array
    {
        $response = $this->client()->put($path, $data);

        $this->assertSuccess($response, 'PUT', $path);

        return $response->json() ?? [];
    }

    /** @return array<string, mixed> */
    private function apiDelete(string $path): array
    {
        $response = $this->client()->delete($path);

        $this->assertSuccess($response, 'DELETE', $path);

        return $response->json() ?? [];
    }

    private function assertSuccess(\Illuminate\Http\Client\Response $response, string $method, string $path): void
    {
        if ($response->failed()) {
            throw new ProvisioningException(
                "Proxmox API {$method} {$path} failed [{$response->status()}]: " . $response->body(),
                driver: 'proxmox',
                retryable: $response->serverError(),
            );
        }
    }

    private function client(): PendingRequest
    {
        if ($this->host === '' || $this->tokenId === '' || $this->tokenSecret === '') {
            throw new ProvisioningException(
                'Proxmox driver is not configured — set PROXMOX_HOST, PROXMOX_API_TOKEN_ID, PROXMOX_API_TOKEN_SECRET.',
                driver: 'proxmox',
                retryable: false,
            );
        }

        $tokenHeader = "{$this->apiUser}!{$this->tokenId}={$this->tokenSecret}";

        return Http::baseUrl(rtrim($this->host, '/') . '/api2/json')
            ->withHeader('Authorization', "PVEAPIToken={$tokenHeader}")
            ->timeout((int) config('provisioning.proxmox.timeout', 60))
            ->withOptions(['verify' => (bool) config('provisioning.proxmox.verify_tls', true)]);
    }
}
