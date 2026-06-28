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

        return collect($response->json('data', []))->map(function (array $item): array {
            $attr = $item['attributes'] ?? [];
            return [
                'id'     => $item['attributes']['id'] ?? 0,
                'name'   => $attr['name'] ?? '—',
                'node'   => (string) ($attr['node'] ?? '—'),
                'status' => $attr['status'] ?? 'unknown',
            ];
        })->all();
    }

    public function getServer(int $serverId): array
    {
        if ($this->mockMode) {
            return ['id' => $serverId, 'name' => 'MOCK-Server', 'status' => 'running'];
        }

        return $this->appRequest('GET', "/api/application/servers/{$serverId}")->json('attributes', []);
    }

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
