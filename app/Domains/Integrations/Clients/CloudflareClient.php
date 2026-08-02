<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Integrations\Clients\Concerns\GuardsRealCalls;
use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare API v4 client (DNS records), credentials from the admin vault.
 *
 * Same five-layer refusal gate as the other providers: writes require an active,
 * non-mock, non-dry-run row with the env gate `CLOUDFLARE_ALLOW_REAL_WRITES`
 * open and the api_token present; otherwise the caller gets a simulated dry-run
 * result and no HTTP is sent. Reads (list, connection test) only require the
 * token. The api_token is a secret — sent as a Bearer header, never logged, never
 * placed in a URL.
 */
final class CloudflareClient
{
    use GuardsRealCalls;

    private const BASE     = 'https://api.cloudflare.com/client/v4';
    private const REQUIRED = ['api_token'];

    public function __construct(private readonly IntegrationSetting $setting) {}

    /** @return array{ok: bool, dry_run: bool, message: string} */
    public function connectionTest(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['ok' => true, 'dry_run' => true, 'message' => 'Mock/dry-run connection OK (no HTTP sent).'];
        }

        $this->assertToken();

        $json   = $this->request('get', '/user/tokens/verify');
        $active = ($json['result']['status'] ?? null) === 'active';

        return [
            'ok'      => $active,
            'dry_run' => false,
            'message' => $active ? 'Cloudflare token je platný a aktivní.' : 'Cloudflare token není aktivní.',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listDnsRecords(?string $zoneId = null): array
    {
        if ($this->isDryRun($this->setting)) {
            return [];
        }

        $this->assertToken();

        $json = $this->request('get', '/zones/' . $this->zone($zoneId) . '/dns_records');

        /** @var list<array<string, mixed>> $records */
        $records = is_array($json['result'] ?? null) ? array_values($json['result']) : [];

        return $records;
    }

    /** @return array<string, mixed> */
    public function createDnsRecord(string $zoneId, string $type, string $name, string $content, int $ttl = 1): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['dry_run' => true, 'type' => $type, 'name' => $name, 'content' => $content];
        }

        $this->assertRealCallAllowed($this->setting, 'cloudflare', 'createDnsRecord', self::REQUIRED);

        $json = $this->request('post', '/zones/' . $zoneId . '/dns_records', [
            'type' => $type, 'name' => $name, 'content' => $content, 'ttl' => $ttl,
        ]);

        /** @var array<string, mixed> $result */
        $result = is_array($json['result'] ?? null) ? $json['result'] : [];

        return $result;
    }

    public function deleteDnsRecord(string $zoneId, string $recordId): bool
    {
        if ($this->isDryRun($this->setting)) {
            return true;
        }

        $this->assertRealCallAllowed($this->setting, 'cloudflare', 'deleteDnsRecord', self::REQUIRED);

        return ($this->request('delete', '/zones/' . $zoneId . '/dns_records/' . $recordId)['success'] ?? false) === true;
    }

    private function zone(?string $zoneId): string
    {
        $zone = $zoneId ?: (string) ($this->setting->credentials['zone_id'] ?? '');

        if ($zone === '') {
            throw new RuntimeException('[cloudflare] missing zone_id.');
        }

        return $zone;
    }

    private function assertToken(): void
    {
        if (($this->setting->credentials['api_token'] ?? '') === '') {
            throw new RuntimeException('[cloudflare] missing credential [api_token].');
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $token   = (string) ($this->setting->credentials['api_token'] ?? '');
        $request = Http::withToken($token)->acceptJson()->timeout(20);

        $response = $method === 'post'
            ? $request->post(self::BASE . $path, $body)
            : $request->{$method}(self::BASE . $path);

        /** @var array<string, mixed>|null $json */
        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('[cloudflare] neplatná odpověď API.');
        }

        if (($json['success'] ?? false) !== true && $response->failed()) {
            $message = is_string($json['errors'][0]['message'] ?? null) ? $json['errors'][0]['message'] : 'neznámá chyba';
            throw new RuntimeException("[cloudflare] API chyba: {$message}");
        }

        return $json;
    }
}
