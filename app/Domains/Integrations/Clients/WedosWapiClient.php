<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Integrations\Clients\Concerns\GuardsRealCalls;
use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Real-provider-READY WEDOS WAPI client. NOT live in this phase.
 *
 * Write operations (register/transfer/NS/DNS) refuse execution unless all
 * refusal gates open (active + !mock + !dry_run + WAPI_ALLOW_REAL_WRITES
 * + credentials). In dry-run they return simulated payloads and log intent.
 *
 * WAPI auth model (future real path): auth = sha1(user . sha1(password)
 * . hour-in-Prague). Quotas: 1000 req/h total, 100 req/h domain-check.
 */
final class WedosWapiClient
{
    use GuardsRealCalls;

    private const GATE = 'wedos';

    private const REQUIRED = ['user', 'password'];

    public function __construct(
        private readonly IntegrationSetting $setting,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            IntegrationSetting::query()->firstOrCreate(
                ['provider' => 'wedos'],
                ['label' => 'WEDOS WAPI (domény)', 'mock_mode' => true, 'dry_run' => true],
            ),
        );
    }

    /** @return array<string, mixed> */
    public function connectionTest(): array
    {
        if ($this->isDryRun($this->setting)) {
            return ['ok' => true, 'dry_run' => true, 'message' => 'Mock/dry-run WAPI ping OK (no HTTP sent).'];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, 'connectionTest', self::REQUIRED);

        return $this->realRequest('ping', []);
    }

    /** @return array<string, mixed> */
    public function checkDomain(string $fqdn): array
    {
        // Availability checks are read-only, but quota-limited (100/h) —
        // even these stay simulated until the provider goes live.
        return $this->dryRunOr('checkDomain', ['name' => $fqdn], 'domain-check');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function registerDomain(string $fqdn, array $contact = []): array
    {
        return $this->dryRunOr('registerDomain', ['name' => $fqdn, 'contact' => $contact], 'domain-create');
    }

    /** @return array<string, mixed> */
    public function transferDomain(string $fqdn, string $authCode): array
    {
        return $this->dryRunOr('transferDomain', ['name' => $fqdn, 'auth_info' => '[redacted]'], 'domain-transfer');
    }

    /**
     * @param  list<string>  $nameservers
     * @return array<string, mixed>
     */
    public function updateNameservers(string $fqdn, array $nameservers): array
    {
        return $this->dryRunOr('updateNameservers', ['name' => $fqdn, 'ns' => $nameservers], 'domain-update-ns');
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function upsertDnsRecord(string $fqdn, array $record): array
    {
        return $this->dryRunOr('upsertDnsRecord', ['domain' => $fqdn, 'record' => $record], 'dns-row-add');
    }

    /** @return array<string, mixed> */
    public function getDomainInfo(string $fqdn): array
    {
        return $this->dryRunOr('getDomainInfo', ['name' => $fqdn], 'domain-info');
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dryRunOr(string $operation, array $payload, string $command): array
    {
        if ($this->isDryRun($this->setting)) {
            Log::info("wedos.dry_run.{$operation}", ['payload' => $payload]);

            return [
                'ok'         => true,
                'dry_run'    => true,
                'operation'  => $operation,
                'would_call' => $command,
                'payload'    => $payload,
            ];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, $operation, self::REQUIRED);

        return $this->realRequest($command, $payload);
    }

    /**
     * Only reachable once every refusal gate is open.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function realRequest(string $command, array $data): array
    {
        $credentials = $this->setting->credentials;
        $user        = $credentials['user'] ?? '';
        $password    = $credentials['password'] ?? '';

        $request = [
            'request' => [
                'user'    => $user,
                'auth'    => sha1($user . sha1($password) . now('Europe/Prague')->format('H')),
                'command' => $command,
                'data'    => $data,
                'test'    => config('provisioning.wedos.test_mode') ? 1 : 0,
            ],
        ];

        $response = Http::timeout(Config::integer('provisioning.wedos.timeout', 30))
            ->asForm()
            ->post(Config::string('provisioning.wedos.url'), ['request' => (string) json_encode($request)]);

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : ['raw' => $response->body()];
    }
}
