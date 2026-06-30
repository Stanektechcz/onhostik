<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Models\Service;

/**
 * Mock driver for Proxmox VPS provisioning.
 *
 * Returns a PENDING result with a deterministic mock UPID so the async
 * polling lifecycle (CheckProxmoxTaskStatusJob) can be tested end-to-end
 * without a real Proxmox cluster.
 *
 * Success/failure simulation: pass simulate_failure=true in the order config.
 */
class ProxmoxMockDriver implements ProvisioningDriverInterface
{
    public function create(Service $service, array $config = []): ProvisioningResult
    {
        // Idempotency: already provisioned.
        if ($service->external_id !== null) {
            return ProvisioningResult::ok(
                externalId: $service->external_id,
                credentials: [],
                metadata: ['idempotent' => true, 'mock' => true],
            );
        }

        if ($config['simulate_failure'] ?? false) {
            return ProvisioningResult::fail(
                'Simulated Proxmox provisioning failure (mock).',
                retryable: true,
            );
        }

        // Generate a deterministic mock VMID and UPID.
        $vmid    = 100 + ($service->id % 900);
        $mockUpid = sprintf('UPID:pve-mock:%08x:00000001:mock%d', $vmid, $vmid);

        // Return PENDING — CheckProxmoxTaskStatusJob will complete provisioning.
        return ProvisioningResult::pending(
            externalId: null, // not yet assigned — set when task completes
            metadata: [
                'mock'     => true,
                'vmid'     => $vmid,
                'upid'     => $mockUpid,
                'hostname' => $service->label ?? "vps-{$vmid}",
                'node'     => 'pve-mock',
            ],
        );
    }

    public function suspend(Service $service): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: $service->external_id,
            metadata: ['mock' => true, 'operation' => 'suspend'],
        );
    }

    public function unsuspend(Service $service): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: $service->external_id,
            metadata: ['mock' => true, 'operation' => 'unsuspend'],
        );
    }

    public function terminate(Service $service): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: 'mock-terminated-' . $service->id,
            metadata: ['mock' => true, 'operation' => 'terminate'],
        );
    }

    public function changePackage(Service $service, array $newResources): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: $service->external_id,
            metadata: ['mock' => true, 'operation' => 'change_package'],
        );
    }

    public function getUsageStats(Service $service): UsageStats
    {
        return new UsageStats(
            diskUsedMb: 1024,
            diskLimitMb: 20480,
            cpuPercent: 15.0,
            memoryUsedMb: 512,
            memoryLimitMb: 1024,
            extra: ['mock' => true],
        );
    }

    public function resetPassword(Service $service): string
    {
        return 'mock-reset-' . substr(md5((string) $service->id), 0, 8);
    }

    public function loginAsUser(Service $service): ?string
    {
        return null; // SSH-only access
    }

    public function testConnection(): bool
    {
        return true;
    }
}
