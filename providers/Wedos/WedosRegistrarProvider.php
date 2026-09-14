<?php

declare(strict_types=1);

namespace Onhost\Providers\Wedos;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Clock\Clock;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\RegistrarProvider;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * Primary RegistrarProvider (blueprint §45). All calls go through WapiGateway.
 * `domain-create`/`domain-transfer` may return 1000 (sync) or 1001 (accepted,
 * async per TLD); async outcomes are resolved through `domain-info` and the
 * poll-req/poll-ack queue — never by resending the create (S34).
 */
final class WedosRegistrarProvider implements RegistrarProvider
{
    private readonly WapiGateway $wapi;

    public function __construct(
        private readonly ProviderInstance $instance,
        array $credentials,
        ProviderHttpClient $http,
        CacheRepository $cache,
        Clock $clock,
    ) {
        $this->wapi = new WapiGateway($instance, $credentials, $http, $cache, $clock);
    }

    public static function providerKey(): string
    {
        return 'wedos';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['wapi-json-2025'];
    }

    public function capabilities(): array
    {
        return ['domain.check' => true, 'domain.register' => true, 'domain.renew' => true, 'domain.transfer' => true, 'domain.auth_info' => true, 'domain.nameservers' => true, 'domain.keyset' => true, 'contacts' => true, 'nsset' => 'cz', 'credit' => true, 'poll' => true, 'dns_zone' => 'optional', 'pricing' => false, 'test_mode' => true];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $this->wapi->command('ping', [], critical: true);
            $quota = $this->wapi->quota();

            return new ProviderHealth(true, null, (int) ((hrtime(true) - $started) / 1_000_000), ['quota' => $quota]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        return 'wapi-json';
    }

    public function gateway(): WapiGateway
    {
        return $this->wapi;
    }

    public function checkAvailability(array $fqdns): array
    {
        $out = [];
        foreach ($fqdns as $fqdn) {
            try {
                $r = $this->wapi->command('domain-check', ['name' => $fqdn]);
                // live WAPI (verified 2026-09-07): 1000 with `data.name` only = the name is free; a registered name is answered with 3201 "Domain is registered"
                $status = strtolower((string) ($r['data']['domain']['status'] ?? $r['data']['status'] ?? 'free'));
                $out[$fqdn] = ['available' => in_array($status, ['free', 'available'], true), 'reason' => $status];
            } catch (ProviderException $e) {
                if ($e->errorCode === ProviderErrorCode::RATE_LIMIT || $e->errorCode === ProviderErrorCode::CIRCUIT_OPEN) {
                    throw $e;
                }
                $normalized = (string) ($e->context['normalized'] ?? 'error');
                $out[$fqdn] = $normalized === WedosErrorMap::DOMAIN_NOT_AVAILABLE
                    ? ['available' => false, 'reason' => 'registered']
                    : ['available' => null, 'reason' => $normalized];
            }
        }

        return $out;
    }

    public function tldPeriods(string $tld): array
    {
        $periods = [];
        foreach ([1, 2, 3, 5, 10] as $period) {
            try {
                $this->wapi->command('domain-tld-period-check', ['tld' => $tld, 'period' => $period]);
                $periods[] = $period;
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::VALIDATION) {
                    throw $e;
                }
            }
        }

        return ['periods' => $periods ?: [1], 'default' => 1];
    }

    public function domainInfo(string $fqdn): array
    {
        $r = $this->wapi->command('domain-info', ['name' => $fqdn], critical: true, testMode: false); // reads never carry the test flag: WAPI answers test-mode reads with empty data
        $d = (array) ($r['data']['domain'] ?? $r['data']);

        return [
            'name' => (string) ($d['name'] ?? $fqdn), 'status' => (string) ($d['status'] ?? ''), 'expires_at' => $d['expiration'] ?? null, 'registered_at' => $d['created'] ?? null,
            'nameservers' => array_values(array_filter((array) ($d['dns'] ?? []), fn ($ns) => is_string($ns) ? $ns !== '' : ! empty($ns['name']))), 'nsset' => $d['nsset'] ?? null, 'keyset' => $d['keyset'] ?? null,
            'registrant' => $d['owner_c'] ?? null, 'admin' => $d['admin_c'] ?? null, 'dnssec' => ! empty($d['keyset']), 'raw' => $d,
        ];
    }

    public function listDomains(): array
    {
        $r = $this->wapi->command('domains-list', [], critical: true, testMode: false);
        $rows = (array) ($r['data']['domain'] ?? $r['data']['domains'] ?? []);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = ['name' => (string) ($row['name'] ?? ''), 'status' => (string) ($row['status'] ?? ''), 'expires_at' => $row['expiration'] ?? null, 'registered_at' => $row['created'] ?? null];
        }

        return $out;
    }

    public function register(string $fqdn, array $request, string $clTrid, bool $testMode = false): ProviderResult
    {
        $data = array_filter([
            'name' => $fqdn, 'period' => (int) ($request['period'] ?? 1),
            'owner_c' => $request['registrant'] ?? null, 'admin_c' => $request['admin'] ?? ($request['registrant'] ?? null),
            'nsset' => $request['nsset'] ?? null, 'dns' => isset($request['nameservers']) ? array_values($request['nameservers']) : null,
            'keyset' => $request['keyset'] ?? null,
            'rules' => $request['rules'] ?? null, // identity of the person who accepted registry/registrar terms (§45.4)
        ], fn ($v) => $v !== null && $v !== []);
        $r = $this->wapi->command('domain-create', $data, $clTrid, critical: true, testMode: $testMode);
        $ref = new ResourceRef('domain', $fqdn, null, ['svTRID' => $r['svTRID']]);
        if (WedosErrorMap::isAsyncAccepted($r['code'])) {
            return ProviderResult::accepted(new AsyncHandle('wapi_async', $fqdn, null, ['command' => 'domain-create', 'clTRID' => $clTrid, 'svTRID' => $r['svTRID']], 900, 5 * 86400), $ref, $r['data']);
        }

        return ProviderResult::completed($ref, $r['data']);
    }

    public function renew(string $fqdn, int $period, string $clTrid, bool $testMode = false): ProviderResult
    {
        $r = $this->wapi->command('domain-renew', ['name' => $fqdn, 'period' => $period], $clTrid, critical: true, testMode: $testMode);
        $ref = new ResourceRef('domain', $fqdn, null, ['svTRID' => $r['svTRID']]);

        return WedosErrorMap::isAsyncAccepted($r['code'])
            ? ProviderResult::accepted(new AsyncHandle('wapi_async', $fqdn, null, ['command' => 'domain-renew', 'clTRID' => $clTrid], 900, 2 * 86400), $ref, $r['data'])
            : ProviderResult::completed($ref, $r['data']);
    }

    public function transferCheck(string $fqdn): array
    {
        $r = $this->wapi->command('domain-transfer-check', ['name' => $fqdn], testMode: false);

        return ['transferable' => $r['code'] === 1000, 'detail' => $r['data']];
    }

    public function transferIn(string $fqdn, string $authInfo, array $request, string $clTrid): ProviderResult
    {
        $data = array_filter(['name' => $fqdn, 'auth_info' => $authInfo, 'owner_c' => $request['registrant'] ?? null, 'admin_c' => $request['admin'] ?? null, 'nsset' => $request['nsset'] ?? null, 'rules' => $request['rules'] ?? null, 'period' => $request['period'] ?? null], fn ($v) => $v !== null);
        $r = $this->wapi->command('domain-transfer', $data, $clTrid, critical: true);
        $ref = new ResourceRef('domain', $fqdn, null, ['svTRID' => $r['svTRID']]);

        return WedosErrorMap::isAsyncAccepted($r['code'])
            ? ProviderResult::accepted(new AsyncHandle('wapi_async', $fqdn, null, ['command' => 'domain-transfer', 'clTRID' => $clTrid], 1800, 7 * 86400), $ref, $r['data'])
            : ProviderResult::completed($ref, $r['data']);
    }

    public function sendAuthInfo(string $fqdn, string $clTrid): ProviderResult
    {
        $r = $this->wapi->command('domain-send-auth-info', ['name' => $fqdn], $clTrid, critical: true);

        return ProviderResult::completed(new ResourceRef('domain', $fqdn), $r['data']);
    }

    public function updateNameservers(string $fqdn, array $nameservers, ?string $nsset, string $clTrid): ProviderResult
    {
        $data = ['name' => $fqdn];
        if ($nsset !== null) {
            $data['nsset'] = $nsset;
        } else {
            $data['dns'] = array_values(array_map(fn ($ns) => is_array($ns) ? $ns : ['name' => $ns], $nameservers));
        }
        $r = $this->wapi->command('domain-update-ns', $data, $clTrid, critical: true);
        $ref = new ResourceRef('domain', $fqdn);

        return WedosErrorMap::isAsyncAccepted($r['code']) ? ProviderResult::accepted(new AsyncHandle('wapi_async', $fqdn, null, ['command' => 'domain-update-ns', 'clTRID' => $clTrid], 600, 86400), $ref) : ProviderResult::completed($ref, $r['data']);
    }

    public function updateKeyset(string $fqdn, array $keyset, string $clTrid): ProviderResult
    {
        $r = $this->wapi->command('domain-update-keyset', ['name' => $fqdn, 'keyset' => $keyset['handle'] ?? null, 'dnskey' => $keyset['dnskey'] ?? null, 'ds' => $keyset['ds'] ?? null], $clTrid, critical: true);

        return ProviderResult::completed(new ResourceRef('domain', $fqdn), $r['data']);
    }

    public function createContact(array $contact, string $clTrid): array
    {
        $data = array_filter([
            'tld' => $contact['tld'], 'cname' => $contact['handle'] ?? null, 'fname' => $contact['first_name'] ?? null, 'lname' => $contact['last_name'] ?? null, 'company' => $contact['organization'] ?? null,
            'email' => $contact['email'], 'phone' => $contact['phone'] ?? null, 'addr_street' => $contact['street'] ?? null, 'addr_city' => $contact['city'] ?? null, 'addr_zip' => $contact['postal_code'] ?? null, 'addr_country' => $contact['country'] ?? 'CZ',
            'ic' => $contact['ico'] ?? null, 'dic' => $contact['dic'] ?? null, 'disclose' => $contact['disclose'] ?? null, 'auth_info' => $contact['auth_info'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $r = $this->wapi->command('contact-create', $data, $clTrid, critical: true);

        return ['remote_id' => (string) ($r['data']['contact']['cname'] ?? $r['data']['cname'] ?? $data['cname'] ?? ''), 'raw' => $r['data']];
    }

    public function contactInfo(string $remoteId): array
    {
        [$tld, $handle] = str_contains($remoteId, ':') ? explode(':', $remoteId, 2) : ['cz', $remoteId];
        $r = $this->wapi->command('contact-info', ['tld' => $tld, 'cname' => $handle], testMode: false);

        return (array) ($r['data']['contact'] ?? $r['data']);
    }

    public function updateContact(string $remoteId, array $contact, string $clTrid): ProviderResult
    {
        [$tld, $handle] = str_contains($remoteId, ':') ? explode(':', $remoteId, 2) : [$contact['tld'] ?? 'cz', $remoteId];
        $data = array_filter(array_merge(['tld' => $tld, 'cname' => $handle], ['email' => $contact['email'] ?? null, 'phone' => $contact['phone'] ?? null, 'addr_street' => $contact['street'] ?? null, 'addr_city' => $contact['city'] ?? null, 'addr_zip' => $contact['postal_code'] ?? null, 'addr_country' => $contact['country'] ?? null]), fn ($v) => $v !== null && $v !== '');
        $r = $this->wapi->command('contact-update', $data, $clTrid, critical: true);

        return ProviderResult::completed(new ResourceRef('contact', $remoteId), $r['data']);
    }

    public function createNsset(string $handle, array $nameservers, string $techContact, string $clTrid): ProviderResult
    {
        try {
            $this->wapi->command('nsset-info', ['nsset' => $handle], testMode: false);

            return ProviderResult::completed(new ResourceRef('nsset', $handle), ['existing' => true], alreadyExisted: true);
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::NOT_FOUND && $e->errorCode !== ProviderErrorCode::VALIDATION) {
                throw $e;
            }
        }
        $r = $this->wapi->command('nsset-create', ['nsset' => $handle, 'dns' => array_values(array_map(fn ($ns) => is_array($ns) ? $ns : ['name' => $ns], $nameservers)), 'tech_c' => $techContact], $clTrid, critical: true);

        return ProviderResult::completed(new ResourceRef('nsset', $handle), $r['data']);
    }

    public function nssetInfo(string $handle): array
    {
        $r = $this->wapi->command('nsset-info', ['nsset' => $handle], testMode: false);

        return (array) ($r['data']['nsset'] ?? $r['data']);
    }

    public function creditInfo(): array
    {
        $r = $this->wapi->command('credit-info', [], critical: true, testMode: false);
        $d = (array) $r['data'];

        return ['balance' => (string) ($d['amount'] ?? $d['credit'] ?? $d['balance'] ?? '0'), 'currency' => strtoupper((string) ($d['currency'] ?? 'CZK'))]; // live WAPI: {amount, currency}
    }

    public function accountMovements(?string $from = null, ?string $to = null): array
    {
        $r = $this->wapi->command('account-list', array_filter(['date_from' => $from, 'date_to' => $to], fn ($v) => $v !== null), critical: true, testMode: false);

        return array_values(array_filter((array) ($r['data']['account'] ?? $r['data']['items'] ?? $r['data']), 'is_array'));
    }

    public function pollRequest(): ?array
    {
        $r = $this->wapi->command('poll-req', [], critical: true, testMode: false);
        $event = $r['data']['event'] ?? $r['data'];
        if (! is_array($event) || empty($event['id'])) {
            return null;
        }

        return ['id' => (string) $event['id'], 'kind' => (string) ($event['type'] ?? $event['kind'] ?? 'unknown'), 'fqdn' => $event['name'] ?? $event['domain'] ?? null, 'payload' => $event];
    }

    public function pollAck(string $notificationId): void
    {
        $this->wapi->command('poll-ack', ['id' => $notificationId], critical: true, testMode: false);
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        $info = $this->domainInfo($handle->handle);
        $status = strtolower($info['status']);
        $command = (string) ($handle->meta['command'] ?? '');
        if ($command === 'domain-transfer') {
            return match (true) {
                str_contains($status, 'pending') || str_contains($status, 'transfer') => AsyncStatus::running("registry status {$status}"),
                $status === 'active' || $status === 'ok' => AsyncStatus::succeeded($info),
                $status === '' => AsyncStatus::running('not visible yet'),
                default => AsyncStatus::failed("transfer ended with status {$status}", $info),
            };
        }
        if (str_contains($status, 'pending') || str_contains($status, 'waiting')) {
            return AsyncStatus::running("registry status {$status}");
        }
        if (in_array($status, ['active', 'ok', 'registered'], true) || ($status === '' && ! empty($info['expires_at']))) {
            return AsyncStatus::succeeded($info);
        }
        if ($status === 'free' || $status === 'available') {
            return AsyncStatus::failed('domain is not registered (registry rejected or timed out)', $info);
        }

        return AsyncStatus::running("registry status {$status}");
    }
}
