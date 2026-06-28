<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;

/**
 * Proxmox VE REST API client.
 *
 * Auth: POST /api2/json/access/ticket → returns PVEAuthCookie + CSRFPreventionToken
 * All subsequent requests must include both headers.
 *
 * Security note: credentials never leave the server. Mock mode returns
 * realistic-looking data without any HTTP calls.
 */
final class ProxmoxClient
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $realm;
    private string $node;
    private bool   $mockMode;

    private ?string $ticket = null;
    private ?string $csrf   = null;

    public function __construct(IntegrationSetting $setting)
    {
        $creds = $setting->credentials;

        $this->baseUrl  = rtrim($creds['base_url'] ?? 'https://proxmox.example.com:8006', '/');
        $this->username = $creds['username'] ?? 'root';
        $this->password = $creds['password'] ?? '';
        $this->realm    = $creds['realm'] ?? 'pam';
        $this->node     = $creds['node'] ?? 'pve';
        $this->mockMode = $setting->mock_mode || !$setting->is_active;
    }

    /** @return array{ok: bool, message: string, dry_run: bool} */
    public function connectionTest(): array
    {
        if ($this->mockMode || empty($this->password)) {
            return [
                'ok'      => true,
                'message' => 'Proxmox mock/dry-run test OK (no HTTP sent).',
                'dry_run' => true,
            ];
        }

        try {
            $auth = $this->authenticate();
            if (!$auth) {
                return ['ok' => false, 'message' => 'Proxmox: autentifikace selhala.', 'dry_run' => false];
            }

            $response = $this->get("/nodes/{$this->node}/status");

            if (isset($response['data'])) {
                $mem   = $response['data']['memory'] ?? [];
                $memPct = isset($mem['used'], $mem['total']) && $mem['total'] > 0
                    ? round($mem['used'] / $mem['total'] * 100)
                    : 0;

                return [
                    'ok'      => true,
                    'message' => "Proxmox OK. Node: {$this->node}, RAM: {$memPct}%",
                    'dry_run' => false,
                    'data'    => $response['data'],
                ];
            }

            return ['ok' => false, 'message' => 'Proxmox: neočekávaná odpověď.', 'dry_run' => false];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Proxmox: ' . $e->getMessage(), 'dry_run' => false];
        }
    }

    /** @return array<int, array{id: int|string, name: string, status: string, type: string}> */
    public function listVMs(): array
    {
        if ($this->mockMode) {
            return [
                ['id' => 100, 'name' => 'MOCK-vm-100', 'status' => 'running', 'type' => 'qemu'],
                ['id' => 101, 'name' => 'MOCK-vm-101', 'status' => 'stopped', 'type' => 'lxc'],
            ];
        }

        $this->authenticate();
        $response = $this->get("/nodes/{$this->node}/qemu");

        return collect($response['data'] ?? [])->map(function (array $vm): array {
            return [
                'id'     => $vm['vmid'] ?? 0,
                'name'   => $vm['name'] ?? "vm-{$vm['vmid']}",
                'status' => $vm['status'] ?? 'unknown',
                'type'   => 'qemu',
                'cpus'   => $vm['cpus'] ?? 0,
                'mem'    => $vm['mem'] ?? 0,
            ];
        })->all();
    }

    public function startVM(int $vmid): bool
    {
        if ($this->mockMode) return true;
        $this->authenticate();
        $r = Http::withOptions(['verify' => false])
            ->withHeaders($this->authHeaders())
            ->post("{$this->baseUrl}/api2/json/nodes/{$this->node}/qemu/{$vmid}/status/start");
        return $r->successful();
    }

    public function stopVM(int $vmid): bool
    {
        if ($this->mockMode) return true;
        $this->authenticate();
        $r = Http::withOptions(['verify' => false])
            ->withHeaders($this->authHeaders())
            ->post("{$this->baseUrl}/api2/json/nodes/{$this->node}/qemu/{$vmid}/status/stop");
        return $r->successful();
    }

    public function getNodeStatus(): array
    {
        if ($this->mockMode) {
            return ['cpu' => 0.15, 'memory' => ['used' => 4_000_000_000, 'total' => 16_000_000_000]];
        }
        $this->authenticate();
        return $this->get("/nodes/{$this->node}/status")['data'] ?? [];
    }

    private function authenticate(): bool
    {
        if ($this->ticket) return true;

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(10)
                ->asForm()
                ->post("{$this->baseUrl}/api2/json/access/ticket", [
                    'username' => "{$this->username}@{$this->realm}",
                    'password' => $this->password,
                ]);

            if ($response->successful()) {
                $this->ticket = $response->json('data.ticket');
                $this->csrf   = $response->json('data.CSRFPreventionToken');
                return true;
            }
        } catch (\Throwable) {}

        return false;
    }

    private function get(string $path): array
    {
        $response = Http::withOptions(['verify' => false])
            ->timeout(15)
            ->withHeaders($this->authHeaders())
            ->get("{$this->baseUrl}/api2/json{$path}");

        return $response->json() ?? [];
    }

    /** @return array<string, string> */
    private function authHeaders(): array
    {
        return [
            'Cookie'               => "PVEAuthCookie={$this->ticket}",
            'CSRFPreventionToken'  => $this->csrf ?? '',
        ];
    }
}
