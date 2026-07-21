<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Clients;

use App\Domains\Shared\Support\SecretRedactor;
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

    /** @return array<string, mixed> */
    public function renewDomain(string $fqdn, int $years = 1): array
    {
        return $this->dryRunOr('renewDomain', ['name' => $fqdn, 'period' => $years], 'domain-renew');
    }

    /**
     * List all domains in the account.
     *
     * @return array<string, mixed>
     */
    public function listDomains(): array
    {
        return $this->dryRunOr('listDomains', [], 'domain-list');
    }

    /**
     * Get all DNS records for a domain.
     *
     * @return array<string, mixed>
     */
    public function getDnsRecords(string $fqdn): array
    {
        return $this->dryRunOr('getDnsRecords', ['domain' => $fqdn], 'dns-rows-list');
    }

    /** @return array<string, mixed> */
    public function deleteDnsRecord(string $fqdn, int $rowId): array
    {
        return $this->dryRunOr('deleteDnsRecord', ['domain' => $fqdn, 'row_id' => $rowId], 'dns-row-delete');
    }

    /** @return array<string, mixed> */
    public function setAutoRenew(string $fqdn, bool $autoRenew): array
    {
        return $this->dryRunOr('setAutoRenew', ['name' => $fqdn, 'autorenew' => $autoRenew ? 1 : 0], 'domain-autorenew');
    }

    /*
    |--------------------------------------------------------------------------
    | DNSSEC (audit F87)
    |--------------------------------------------------------------------------
    | DS records published at the registry prove the zone's signing key. Every
    | call goes through dryRunOr like the rest of the client, so in mock mode
    | nothing is sent and the payload is echoed back.
    */

    /** @return array<string, mixed> */
    public function getDnssecKeys(string $fqdn): array
    {
        return $this->dryRunOr('getDnssecKeys', ['name' => $fqdn], 'domain-dnssec-list');
    }

    /**
     * Publish a DS record.
     *
     * @param  array{key_tag: int, algorithm: int, digest_type: int, digest: string}  $dsRecord
     * @return array<string, mixed>
     */
    public function addDnssecKey(string $fqdn, array $dsRecord): array
    {
        return $this->dryRunOr('addDnssecKey', ['name' => $fqdn] + $dsRecord, 'domain-dnssec-add');
    }

    /** @return array<string, mixed> */
    public function deleteDnssecKey(string $fqdn, int $keyTag): array
    {
        return $this->dryRunOr('deleteDnssecKey', ['name' => $fqdn, 'key_tag' => $keyTag], 'domain-dnssec-delete');
    }

    /*
    |--------------------------------------------------------------------------
    | WHOIS contacts + privacy (audit F89)
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function getDomainContacts(string $fqdn): array
    {
        return $this->dryRunOr('getDomainContacts', ['name' => $fqdn], 'domain-contacts-list');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function updateDomainContact(string $fqdn, string $type, array $contact): array
    {
        return $this->dryRunOr(
            'updateDomainContact',
            ['name' => $fqdn, 'type' => $type, 'contact' => $contact],
            'domain-contact-update',
        );
    }

    /**
     * Toggle WHOIS privacy — hides the registrant's personal details from
     * public WHOIS. Not offered by every TLD; the registry decides.
     *
     * @return array<string, mixed>
     */
    public function setWhoisPrivacy(string $fqdn, bool $enabled): array
    {
        return $this->dryRunOr(
            'setWhoisPrivacy',
            ['name' => $fqdn, 'privacy' => $enabled ? 1 : 0],
            'domain-privacy-set',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Glue records / vanity nameservers (audit F92)
    |--------------------------------------------------------------------------
    | A glue record is the A/AAAA of a nameserver that lives INSIDE the zone it
    | serves (ns1.example.cz for example.cz) — without it the zone cannot be
    | resolved at all. Resellers need these to run branded nameservers.
    */

    /** @return array<string, mixed> */
    public function getGlueRecords(string $fqdn): array
    {
        return $this->dryRunOr('getGlueRecords', ['name' => $fqdn], 'host-list');
    }

    /** @return array<string, mixed> */
    public function addGlueRecord(string $fqdn, string $hostname, string $ipAddress): array
    {
        return $this->dryRunOr(
            'addGlueRecord',
            ['name' => $fqdn, 'host' => $hostname, 'ip' => $ipAddress],
            'host-add',
        );
    }

    /** @return array<string, mixed> */
    public function deleteGlueRecord(string $fqdn, string $hostname): array
    {
        return $this->dryRunOr(
            'deleteGlueRecord',
            ['name' => $fqdn, 'host' => $hostname],
            'host-delete',
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dryRunOr(string $operation, array $payload, string $command): array
    {
        if ($this->isDryRun($this->setting)) {
            // The echo is logged AND returned to the caller, so credentials in
            // the payload (transfer auth codes, contact secrets) must be
            // masked before either happens.
            $safe = self::redactSecrets($payload);

            Log::info("wedos.dry_run.{$operation}", ['payload' => $safe]);

            return [
                'ok'         => true,
                'dry_run'    => true,
                'operation'  => $operation,
                'would_call' => $command,
                'payload'    => $safe,
            ];
        }

        $this->assertRealCallAllowed($this->setting, self::GATE, $operation, self::REQUIRED);

        return $this->realRequest($command, $payload);
    }

    /**
     * Mask credential-bearing keys before a payload is logged or returned.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function redactSecrets(array $payload): array
    {
        /** @var array<string, mixed> $redacted */
        $redacted = SecretRedactor::redact($payload);

        return $redacted;
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
