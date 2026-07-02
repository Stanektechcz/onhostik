<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Drivers;

use App\Domains\Integrations\Clients\PterodactylClient;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Provisioning\Contracts\ProvisioningDriverInterface;
use App\Domains\Provisioning\DTOs\ProvisioningResult;
use App\Domains\Provisioning\DTOs\UsageStats;
use App\Domains\Provisioning\Exceptions\ProvisioningException;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Str;

/**
 * Pterodactyl Panel provisioning driver (game server hosting).
 *
 * Credentials required in IntegrationSetting (provider='pterodactyl'):
 *   base_url             — https://panel.example.com
 *   application_api_key  — ptla_xxx (Application API key)
 *
 * Per-plan resource configuration (service.resources):
 *   memory_mb    — RAM allocation in MB (default: 512)
 *   disk_mb      — Disk limit in MB   (default: 5120)
 *   cpu_limit    — CPU % limit        (default: 100)
 *   swap_mb      — Swap in MB         (default: 0)
 *   io_weight    — Block IO weight    (default: 500)
 *   nest_id      — Egg nest ID        (default: 1)
 *   egg_id       — Egg ID             (default: 1)
 *   node_id      — Preferred node ID  (default: auto-pick)
 *   allocation_id — Port allocation   (default: auto-pick)
 *   startup      — Startup command override
 *   environment  — JSON env vars (per egg)
 *   docker_image — Docker image override
 *
 * Env gates:
 *   PROVISIONING_MOCK_MODE=false
 *
 * Security:
 *   - application_api_key never logged (only 'api_key_set: true' is safe).
 */
final class PterodactylProductionDriver implements ProvisioningDriverInterface
{
    private readonly PterodactylClient $client;

    public function __construct()
    {
        $setting = IntegrationSetting::query()
            ->where('provider', 'pterodactyl')
            ->first();

        if ($setting === null) {
            throw new ProvisioningException(
                'Pterodactyl integration setting not found in database.',
                driver: 'pterodactyl',
                retryable: false,
            );
        }

        $credentials = $setting->credentials;

        if (empty($credentials['base_url']) || empty($credentials['application_api_key'])) {
            throw new ProvisioningException(
                'Pterodactyl driver not configured — set base_url and application_api_key in integration settings.',
                driver: 'pterodactyl',
                retryable: false,
            );
        }

        $this->client = new PterodactylClient($setting);
    }

    /** @param array<string, mixed> $config */
    public function create(Service $service, array $config = []): ProvisioningResult
    {
        if ($service->external_id !== null) {
            return ProvisioningResult::ok(
                externalId: $service->external_id,
                metadata: ['idempotent' => true, 'driver' => 'pterodactyl'],
            );
        }

        /** @var array<string, mixed> $resources */
        $resources = is_array($service->resources) ? $service->resources : [];

        $nestId   = (int) ($resources['nest_id']  ?? 1);
        $eggId    = (int) ($resources['egg_id']   ?? 1);
        $nodeId   = (int) ($resources['node_id']  ?? 0);
        $memoryMb = (int) ($resources['memory_mb'] ?? 512);
        $diskMb   = (int) ($resources['disk_mb']   ?? 5120);
        $cpuLimit = (int) ($resources['cpu_limit'] ?? 100);
        $swapMb   = (int) ($resources['swap_mb']   ?? 0);
        $ioWeight = (int) ($resources['io_weight'] ?? 500);

        // Pick a free allocation on the preferred node (or first available)
        $allocationId = (int) ($resources['allocation_id'] ?? 0);
        if ($allocationId === 0 && $nodeId > 0) {
            $allocationId = $this->pickFreeAllocation($nodeId);
        }

        $serverName = $service->label ?? ('game-' . Str::lower(Str::random(8)));

        // Create a Pterodactyl user for this service
        $username = 'onhost_' . Str::lower(preg_replace('/[^a-z0-9]/i', '', $serverName) ?? $service->id);
        $password = Str::password(20);
        $customer = $service->customer;
        $email    = $customer?->email ?? ($username . '@noreply.onhost.cz');

        $userResult = $this->client->createUser([
            'username'   => substr($username, 0, 32),
            'email'      => $email,
            'first_name' => $customer?->company_name ?? 'OnHost',
            'last_name'  => 'Customer',
            'password'   => $password,
        ]);

        $userId = (int) ($userResult['id'] ?? 0);

        if ($userId === 0) {
            return ProvisioningResult::failure('Pterodactyl: failed to create user account.');
        }

        /** @var array<string, string> $environment */
        $environment = is_array($resources['environment'] ?? null)
            ? $resources['environment']
            : [];

        $serverData = [
            'name'           => $serverName,
            'user'           => $userId,
            'egg'            => $eggId,
            'docker_image'   => $resources['docker_image'] ?? '',
            'startup'        => $resources['startup'] ?? '',
            'environment'    => $environment,
            'limits' => [
                'memory' => $memoryMb,
                'swap'   => $swapMb,
                'disk'   => $diskMb,
                'io'     => $ioWeight,
                'cpu'    => $cpuLimit,
            ],
            'feature_limits' => [
                'databases'   => 1,
                'backups'     => 2,
                'allocations' => 1,
            ],
            'deploy' => $nodeId > 0 && $allocationId > 0
                ? [] // use allocation instead
                : ['locations' => [], 'dedicated_ip' => false, 'port_range' => []],
        ];

        if ($allocationId > 0) {
            $serverData['allocation'] = ['default' => $allocationId];
            unset($serverData['deploy']);
        }

        $result = $this->client->createServer($serverData);

        if (! ($result['ok'] ?? false)) {
            return ProvisioningResult::failure(
                'Pterodactyl createServer failed: ' . ($result['message'] ?? 'unknown error')
            );
        }

        $serverId = (int) ($result['id'] ?? 0);

        if ($serverId === 0) {
            return ProvisioningResult::failure('Pterodactyl createServer: no server ID returned.');
        }

        return ProvisioningResult::ok(
            externalId: (string) $serverId,
            credentials: [
                'panel_username' => substr($username, 0, 32),
                'panel_password' => $password,
                'panel_url'      => $this->client->connectionTest()['data']['base_url'] ?? '',
            ],
            metadata: [
                'server_id'    => $serverId,
                'user_id'      => $userId,
                'server_name'  => $serverName,
                'driver'       => 'pterodactyl',
            ],
        );
    }

    public function suspend(Service $service): ProvisioningResult
    {
        return $this->lifecycle($service, 'suspend');
    }

    public function unsuspend(Service $service): ProvisioningResult
    {
        return $this->lifecycle($service, 'unsuspend');
    }

    public function terminate(Service $service): ProvisioningResult
    {
        if ($service->external_id === null) {
            return ProvisioningResult::failure('No external_id — nothing to terminate.');
        }

        $serverId = (int) $service->external_id;

        $ok = $this->client->deleteServer($serverId);

        return $ok
            ? ProvisioningResult::ok(externalId: (string) $serverId, metadata: ['operation' => 'terminate'])
            : ProvisioningResult::failure('Pterodactyl deleteServer failed.');
    }

    /** @param array<string, mixed> $newResources */
    public function changePackage(Service $service, array $newResources): ProvisioningResult
    {
        if ($service->external_id === null) {
            return ProvisioningResult::failure('No external_id — cannot resize.');
        }

        $serverId = (int) $service->external_id;

        $ok = $this->client->updateServerBuild($serverId, [
            'allocation'     => ['default' => (int) ($newResources['allocation_id'] ?? 0)],
            'memory'         => (int) ($newResources['memory_mb'] ?? 512),
            'swap'           => (int) ($newResources['swap_mb']   ?? 0),
            'disk'           => (int) ($newResources['disk_mb']   ?? 5120),
            'io'             => (int) ($newResources['io_weight'] ?? 500),
            'cpu'            => (int) ($newResources['cpu_limit'] ?? 100),
            'feature_limits' => ['databases' => 1, 'backups' => 2, 'allocations' => 1],
        ]);

        return $ok
            ? ProvisioningResult::ok(externalId: $service->external_id, metadata: ['operation' => 'change_package'])
            : ProvisioningResult::failure('Pterodactyl updateServerBuild failed.');
    }

    public function getUsageStats(Service $service): UsageStats
    {
        if ($service->external_id === null) {
            return new UsageStats(0, 0, 0, 0);
        }

        /** @var array<string, mixed> $resources */
        $resources    = is_array($service->resources) ? $service->resources : [];
        $diskLimitMb  = (int) ($resources['disk_mb']   ?? 5120);
        $bandLimitMb  = 0;

        try {
            $data = $this->client->getServer((int) $service->external_id);

            return new UsageStats(
                diskUsedMb:       0,
                diskLimitMb:      $diskLimitMb,
                bandwidthUsedMb:  0,
                bandwidthLimitMb: $bandLimitMb,
                cpuPercent:       0.0,
                extra: ['status' => $data['status'] ?? 'unknown'],
            );
        } catch (\Throwable) {
            return new UsageStats(0, $diskLimitMb, 0, $bandLimitMb);
        }
    }

    public function resetPassword(Service $service): string
    {
        // Pterodactyl password reset happens through the panel — return a
        // new random password and note that the user must be updated via API.
        return Str::password(20);
    }

    public function loginAsUser(Service $service): ?string
    {
        // Pterodactyl does not support SSO redirects via the Application API.
        return null;
    }

    public function testConnection(): bool
    {
        try {
            $result = $this->client->connectionTest();
            return (bool) ($result['ok'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------------------------------------------------------------- internals

    private function lifecycle(Service $service, string $operation): ProvisioningResult
    {
        if ($service->external_id === null) {
            return ProvisioningResult::failure("No external_id — cannot {$operation}.");
        }

        $serverId = (int) $service->external_id;

        $ok = $operation === 'suspend'
            ? $this->client->suspendServer($serverId)
            : $this->client->unsuspendServer($serverId);

        return $ok
            ? ProvisioningResult::ok(externalId: (string) $serverId, metadata: ['operation' => $operation])
            : ProvisioningResult::failure("Pterodactyl {$operation} failed.");
    }

    private function pickFreeAllocation(int $nodeId): int
    {
        $allocations = $this->client->listAllocations($nodeId);

        foreach ($allocations as $alloc) {
            if (! ($alloc['assigned'] ?? true)) {
                return (int) $alloc['id'];
            }
        }

        return 0;
    }
}
