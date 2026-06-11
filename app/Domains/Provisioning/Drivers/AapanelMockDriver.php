<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Str;

/**
 * MOCK aaPanel driver — performs NO network I/O whatsoever.
 *
 * Behaviour contract (mirrored by the future real driver):
 *  - create() is idempotent: an already provisioned service (external_id set)
 *    returns its existing identifier and never "creates" twice.
 *  - `$config['simulate_failure'] === true` produces a failure result so the
 *    whole retry / manual-review path can be exercised end to end.
 *  - Credentials are generated locally and returned exactly once in the
 *    result; callers must sanitize before persisting/logging.
 */
final class AapanelMockDriver implements ProvisioningDriverInterface
{
    /** @param array<string, mixed> $config */
    public function create(Service $service, array $config = []): ProvisioningResult
    {
        // Idempotency: never re-create an already provisioned service.
        if ($service->external_id !== null) {
            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['idempotent' => true, 'mock' => true],
            );
        }

        if (($config['simulate_failure'] ?? false) === true) {
            return ProvisioningResult::failure(
                errorMessage: 'Simulated aaPanel failure (mock mode).',
                externalRequestId: 'MOCK-REQ-' . Str::lower(Str::random(10)),
            );
        }

        $username = 'u' . Str::lower(Str::random(9));

        return ProvisioningResult::ok(
            externalId: 'MOCK-AAP-' . Str::upper(Str::random(8)),
            credentials: [
                'panel_username' => $username,
                'panel_password' => Str::password(20),
                'ftp_host'       => 'ftp.mock.onhost.local',
            ],
            metadata: [
                'mock'        => true,
                'doc_root'    => '/www/wwwroot/' . ($service->label ?? $username),
                'php_version' => '8.3',
            ],
        );
    }

    public function suspend(Service $service): ProvisioningResult
    {
        return $this->mockLifecycleResult($service, 'suspend');
    }

    public function unsuspend(Service $service): ProvisioningResult
    {
        return $this->mockLifecycleResult($service, 'unsuspend');
    }

    public function terminate(Service $service): ProvisioningResult
    {
        return $this->mockLifecycleResult($service, 'terminate');
    }

    /** @param array<string, mixed> $newResources */
    public function changePackage(Service $service, array $newResources): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: $service->external_id ?? 'MOCK-AAP-UNPROVISIONED',
            metadata: ['mock' => true, 'operation' => 'change_package', 'resources' => $newResources],
        );
    }

    public function getUsageStats(Service $service): UsageStats
    {
        /** @var array<string, int|null> $resources */
        $resources = is_array($service->resources) ? $service->resources : [];
        $diskLimit = (int) ($resources['disk_mb'] ?? 5_120);

        return new UsageStats(
            diskUsedMb: (int) round($diskLimit * 0.18),
            diskLimitMb: $diskLimit,
            bandwidthUsedMb: 2_048,
            bandwidthLimitMb: (int) ($resources['bandwidth_gb'] ?? 50) * 1_024,
            cpuPercent: 3.5,
            extra: ['mock' => true],
        );
    }

    public function resetPassword(Service $service): string
    {
        return Str::password(20);
    }

    public function loginAsUser(Service $service): ?string
    {
        if ($service->external_id === null) {
            return null;
        }

        return 'https://mock.aapanel.local/sso/' . Str::lower(Str::random(32));
    }

    public function testConnection(): bool
    {
        return true;
    }

    private function mockLifecycleResult(Service $service, string $operation): ProvisioningResult
    {
        return ProvisioningResult::ok(
            externalId: $service->external_id ?? 'MOCK-AAP-UNPROVISIONED',
            metadata: ['mock' => true, 'operation' => $operation],
        );
    }
}
