<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Pterodactyl Panel API client (Application API).
 *
 * Pterodactyl uses two separate API keys:
 *   - Application API key: server/node/user management (admin panel)
 *   - Client API key: per-server actions (file manager, power, console)
 *
 * Security: all write operations are guarded by integration.is_active
 * and integration.mock_mode. Real calls are never sent in mock mode.
 */
final class PterodactylClient
{
    private string $baseUrl;
    private string $appApiKey;
    private bool   $mockMode;

    public function __construct(IntegrationSetting $setting)
    {
        $creds = $setting->credentials;

        $this->baseUrl   = rtrim($creds['base_url'] ?? '', '/');
        $this->appApiKey = $creds['application_api_key'] ?? '';
        $this->mockMode  = $setting->mock_mode || !$setting->is_active;
    }

    /** @return array{ok: bool, message: string, dry_run: bool, data?: mixed} */
    public function connectionTest(): array
    {
        if ($this->mockMode || empty($this->baseUrl) || empty($this->appApiKey)) {
            return [
                'ok'      => true,
                'message' => 'Pterodactyl mock/dry-run test OK (no HTTP sent).',
                'dry_run' => true,
            ];
        }

        try {
            $response = $this->appRequest('GET', '/api/application/servers', ['per_page' => 1]);

            if ($response->successful()) {
                $total = $response->json('meta.pagination.total', 0);
                return [
                    'ok'      => true,
                    'message' => "Pterodactyl API OK. Celkem serverů: {$total}.",
                    'dry_run' => false,
                    'data'    => ['total_servers' => $total],
                ];
            }

            return [
                'ok'      => false,
                'message' => "Pterodactyl API chyba: HTTP {$response->status()}",
                'dry_run' => false,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Pterodactyl: ' . $e->getMessage(), 'dry_run' => false];
        }
    }

    /** @return array<int, array{id: int, name: string, node: string, status: string}> */
    public function listServers(int $page = 1): array
    {
        if ($this->mockMode) {
            return [
                ['id' => 1, 'name' => 'MOCK-Server-1', 'node' => 'Node 1', 'status' => 'running'],
                ['id' => 2, 'name' => 'MOCK-Server-2', 'node' => 'Node 1', 'status' => 'offline'],
            ];
        }

        $response = $this->appRequest('GET', '/api/application/servers', ['per_page' => 25, 'page' => $page]);

        $items = is_array($response->json('data')) ? $response->json('data') : [];

        return collect($items)->map(function (array $item): array {
            $attr = $item['attributes'] ?? [];
            return [
                'id'     => $item['attributes']['id'] ?? 0,
                'name'   => $attr['name'] ?? '—',
                'node'   => (string) ($attr['node'] ?? '—'),
                'status' => $attr['status'] ?? 'unknown',
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function getServer(int $serverId): array
    {
        if ($this->mockMode) {
            return ['id' => $serverId, 'name' => 'MOCK-Server', 'status' => 'running'];
        }

        return $this->appRequest('GET', "/api/application/servers/{$serverId}")->json('attributes', []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createServer(array $data): array
    {
        if ($this->mockMode) {
            return ['ok' => true, 'message' => 'Mock: server vytvoření simulováno.', 'id' => rand(100, 999)];
        }

        $response = $this->appRequest('POST', '/api/application/servers', $data);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Server vytvořen.' : $response->body(),
            'id'      => $response->json('attributes.id', 0),
        ];
    }

    public function deleteServer(int $serverId): bool
    {
        if ($this->mockMode) {
            return true;
        }

        return $this->appRequest('DELETE', "/api/application/servers/{$serverId}")->successful();
    }

    public function suspendServer(int $serverId): bool
    {
        if ($this->mockMode) {
            return true;
        }

        return $this->appRequest('POST', "/api/application/servers/{$serverId}/suspend")->successful();
    }

    public function unsuspendServer(int $serverId): bool
    {
        if ($this->mockMode) {
            return true;
        }

        return $this->appRequest('POST', "/api/application/servers/{$serverId}/unsuspend")->successful();
    }

    /**
     * Reinstall a server (resets files to egg default).
     */
    public function reinstallServer(int $serverId): bool
    {
        if ($this->mockMode) return true;
        return $this->appRequest('POST', "/api/application/servers/{$serverId}/reinstall")->successful();
    }

    /**
     * Update server build (resources: CPU, RAM, disk, ports).
     *
     * @param array<string, mixed> $data
     */
    public function updateServerBuild(int $serverId, array $data): bool
    {
        if ($this->mockMode) return true;
        return $this->appRequest('PATCH', "/api/application/servers/{$serverId}/build", $data)->successful();
    }

    /**
     * Update server details (name, description, user).
     *
     * @param array<string, mixed> $data
     */
    public function updateServerDetails(int $serverId, array $data): bool
    {
        if ($this->mockMode) return true;
        return $this->appRequest('PATCH', "/api/application/servers/{$serverId}/details", $data)->successful();
    }

    /** @return array<int, array{id: int, name: string, fqdn: string, memory: int, disk: int}> */
    public function listNodes(): array
    {
        if ($this->mockMode) {
            return [['id' => 1, 'name' => 'MOCK-Node', 'fqdn' => 'node.example.com', 'memory' => 16384, 'disk' => 102400]];
        }
        $response = $this->appRequest('GET', '/api/application/nodes', ['per_page' => 50]);
        $items = is_array($response->json('data')) ? $response->json('data') : [];
        return collect($items)->map(function (array $item): array {
            $attr = $item['attributes'] ?? [];
            return [
                'id'     => $attr['id'] ?? 0,
                'name'   => $attr['name'] ?? '—',
                'fqdn'   => $attr['fqdn'] ?? '—',
                'memory' => (int) ($attr['memory'] ?? 0),
                'disk'   => (int) ($attr['disk'] ?? 0),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function getNode(int $nodeId): array
    {
        if ($this->mockMode) return ['id' => $nodeId, 'name' => 'MOCK-Node', 'fqdn' => 'node.example.com'];
        return $this->appRequest('GET', "/api/application/nodes/{$nodeId}")->json('attributes', []);
    }

    /**
     * List available port allocations for a node.
     *
     * @return array<int, array{id: int, ip: string, port: int, assigned: bool}>
     */
    public function listAllocations(int $nodeId): array
    {
        if ($this->mockMode) {
            return [['id' => 1, 'ip' => '0.0.0.0', 'port' => 25565, 'assigned' => false]];
        }
        $response = $this->appRequest('GET', "/api/application/nodes/{$nodeId}/allocations", ['per_page' => 100]);
        $items = is_array($response->json('data')) ? $response->json('data') : [];
        return collect($items)->map(function (array $item): array {
            $attr = $item['attributes'] ?? [];
            return [
                'id'       => $attr['id'] ?? 0,
                'ip'       => $attr['ip'] ?? '0.0.0.0',
                'port'     => (int) ($attr['port'] ?? 0),
                'assigned' => $attr['assigned'] ?? false,
            ];
        })->all();
    }

    /** @return array<int, array{id: int, name: string}> */
    public function listNests(): array
    {
        if ($this->mockMode) {
            return [['id' => 1, 'name' => 'Minecraft'], ['id' => 2, 'name' => 'Source Engine']];
        }
        $response = $this->appRequest('GET', '/api/application/nests', ['per_page' => 50]);
        $items = is_array($response->json('data')) ? $response->json('data') : [];
        return collect($items)->map(fn (array $i) => [
            'id'   => $i['attributes']['id'] ?? 0,
            'name' => $i['attributes']['name'] ?? '—',
        ])->all();
    }

    /**
     * List eggs (server templates) within a nest.
     *
     * @return array<int, array{id: int, name: string, docker_image: string}>
     */
    public function listEggs(int $nestId): array
    {
        if ($this->mockMode) {
            return [['id' => 1, 'name' => 'Paper', 'docker_image' => 'ghcr.io/pterodactyl/yolks:java_17']];
        }
        $response = $this->appRequest('GET', "/api/application/nests/{$nestId}/eggs", ['include' => 'variables', 'per_page' => 50]);
        $items = is_array($response->json('data')) ? $response->json('data') : [];
        return collect($items)->map(fn (array $i) => [
            'id'           => $i['attributes']['id'] ?? 0,
            'name'         => $i['attributes']['name'] ?? '—',
            'docker_image' => $i['attributes']['docker_image'] ?? '',
        ])->all();
    }

    /** @return array<int, array{id: int, username: string, email: string}> */
    public function listUsers(int $page = 1): array
    {
        if ($this->mockMode) {
            return [['id' => 1, 'username' => 'mock_user', 'email' => 'mock@example.com']];
        }
        $response = $this->appRequest('GET', '/api/application/users', ['per_page' => 50, 'page' => $page]);
        $items = is_array($response->json('data')) ? $response->json('data') : [];
        return collect($items)->map(fn (array $i) => [
            'id'       => $i['attributes']['id'] ?? 0,
            'username' => $i['attributes']['username'] ?? '—',
            'email'    => $i['attributes']['email'] ?? '—',
        ])->all();
    }

    /**
     * @param array<string, mixed> $data  username, email, first_name, last_name, password
     * @return array<string, mixed>
     */
    public function createUser(array $data): array
    {
        if ($this->mockMode) {
            return ['ok' => true, 'id' => rand(100, 999), 'username' => $data['username'] ?? 'mock_user'];
        }
        $response = $this->appRequest('POST', '/api/application/users', $data);
        return [
            'ok'       => $response->successful(),
            'id'       => $response->json('attributes.id', 0),
            'username' => $response->json('attributes.username', ''),
        ];
    }

    public function deleteUser(int $userId): bool
    {
        if ($this->mockMode) return true;
        return $this->appRequest('DELETE', "/api/application/users/{$userId}")->successful();
    }

    /** @param array<string, mixed> $data */
    private function appRequest(string $method, string $path, array $data = []): Response
    {
        $http = Http::withHeaders([
            'Authorization' => "Bearer {$this->appApiKey}",
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout(15);

        return match (strtoupper($method)) {
            'GET'    => $http->get("{$this->baseUrl}{$path}", $data),
            'POST'   => $http->post("{$this->baseUrl}{$path}", $data),
            'DELETE' => $http->delete("{$this->baseUrl}{$path}"),
            'PATCH'  => $http->patch("{$this->baseUrl}{$path}", $data),
            default  => $http->get("{$this->baseUrl}{$path}"),
        };
    }
}
