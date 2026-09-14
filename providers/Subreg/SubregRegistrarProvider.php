<?php

declare(strict_types=1);

namespace Onhost\Providers\Subreg;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\RegistrarPricingProvider;
use Onhost\Providers\Contracts\RegistrarProvider;
use Onhost\Providers\Contracts\ResourceRef;

/**
 * Subreg.CZ registrar (https://subreg.cz/manual/). Only the domain registration /
 * editing / management functions are used — never the hosting families. Every
 * mutation is a Subreg *order* (`Make_Order`) that completes asynchronously; the
 * order id travels in the AsyncHandle and `awaitStatus` resolves it through
 * `Info_Order` + `Info_Domain`, so a timeout is never resolved by resending (S34).
 * Wholesale prices come from `Prices` (per TLD) and feed the cheapest-registrar
 * selection (`RegistrarSelector`).
 */
final class SubregRegistrarProvider implements RegistrarPricingProvider, RegistrarProvider
{
    private readonly SubregSoapGateway $soap;

    public function __construct(
        private readonly ProviderInstance $instance,
        array $credentials,
        ProviderHttpClient $http,
        CacheRepository $cache,
    ) {
        $this->soap = new SubregSoapGateway($instance, $credentials, $http, $cache);
    }

    public static function providerKey(): string
    {
        return 'subreg';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['soap-2026'];
    }

    public function capabilities(): array
    {
        return [
            'domain.check' => true, 'domain.register' => true, 'domain.renew' => true, 'domain.transfer' => true, 'domain.auth_info' => 'inline',
            'domain.nameservers' => true, 'domain.keyset' => true, 'contacts' => true, 'nsset' => 'cz', 'credit' => true, 'poll' => true,
            'pricing' => true, 'test_mode' => $this->soap->isDemo(),
        ];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $this->soap->login();
            $credit = $this->creditInfo();

            return new ProviderHealth(true, 'soap', (int) ((hrtime(true) - $started) / 1_000_000), ['credit' => $credit['balance'], 'currency' => $credit['currency'], 'demo' => $this->soap->isDemo(), 'quota' => $this->soap->quota()]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        return 'soap';
    }

    public function gateway(): SubregSoapGateway
    {
        return $this->soap;
    }

    // ── availability, info, listing ──────────────────────────────────────────

    public function checkAvailability(array $fqdns): array
    {
        $out = [];
        foreach ($fqdns as $fqdn) {
            try {
                $d = $this->soap->call('Check_Domain', ['domain' => $fqdn]);
                $available = (int) ($d['avail'] ?? 0) === 1;
                $entry = ['available' => $available, 'reason' => $available ? 'free' : 'registered'];
                if (isset($d['price']) && is_array($d['price'])) {
                    $entry['price'] = ['amount' => (string) ($d['price']['amount'] ?? ''), 'currency' => strtoupper((string) ($d['price']['currency'] ?? '')), 'premium' => (int) ($d['price']['premium'] ?? 0) === 1];
                }
                if (! empty($d['existing_claim_id'])) {
                    $entry['reason'] = 'tmch_claim';
                    $entry['available'] = false; // domains with a TMCH claim cannot be registered through the API
                }
                $out[$fqdn] = $entry;
            } catch (ProviderException $e) {
                if ($e->errorCode === ProviderErrorCode::RATE_LIMIT || $e->errorCode === ProviderErrorCode::CIRCUIT_OPEN) {
                    throw $e;
                }
                $out[$fqdn] = ['available' => null, 'reason' => $e->context['normalized'] ?? 'error'];
            }
        }

        return $out;
    }

    public function tldPeriods(string $tld): array
    {
        $d = $this->soap->call('Get_TLD_Info', ['tld' => ltrim(strtolower($tld), '.')]);
        $periods = array_values(array_unique(array_filter(array_map('intval', SubregSoapGateway::listOf($d['periodsCreate'] ?? [])), fn (int $p) => $p > 0)));
        sort($periods);

        return ['periods' => $periods ?: [1], 'default' => $periods[0] ?? 1];
    }

    public function domainInfo(string $fqdn): array
    {
        $d = $this->soap->call('Info_Domain', ['domain' => $fqdn], critical: true);
        $statuses = array_values(array_filter(array_map(fn ($s) => trim((string) $s), SubregSoapGateway::listOf($d['status'] ?? [])), fn ($s) => $s !== ''));
        $options = is_array($d['options'] ?? null) ? $d['options'] : [];
        $hosts = array_values(array_filter(array_map(fn ($h) => is_array($h) ? (string) ($h['hostname'] ?? $h['name'] ?? '') : (string) $h, SubregSoapGateway::listOf($d['hosts'] ?? [])), fn ($h) => $h !== ''));
        $registrant = is_array($d['registrant'] ?? null) ? ($d['registrant']['subregid'] ?? $d['registrant']['registryid'] ?? null) : ($d['registrant'] ?? null);
        $admin = null;
        if (is_array($d['contacts'] ?? null) && isset($d['contacts']['admin'])) {
            $first = SubregSoapGateway::listOf($d['contacts']['admin'])[0] ?? null;
            $admin = is_array($first) ? ($first['subregid'] ?? $first['registryid'] ?? null) : $first;
        }
        $keyset = $options['keyset'] ?? null;
        $dsdata = SubregSoapGateway::listOf($options['dsdata'] ?? []);

        return [
            'name' => (string) ($d['domain'] ?? $fqdn), 'status' => self::normaliseStatus($statuses, (string) ($options['quarantined'] ?? '')), 'statuses' => $statuses,
            'expires_at' => self::date($d['exDate'] ?? null), 'registered_at' => self::date($d['crDate'] ?? null), 'updated_at' => self::date($d['upDate'] ?? null),
            'nameservers' => $hosts, 'nsset' => $options['nsset'] ?? null, 'keyset' => $keyset, 'dnssec' => ! empty($keyset) || $dsdata !== [], 'ds' => $dsdata,
            'registrant' => $registrant, 'admin' => $admin, 'auto_renew' => isset($d['autorenew']) ? (int) $d['autorenew'] : null, 'premium' => (int) ($d['premium'] ?? 0) === 1,
            'raw' => $d,
        ];
    }

    public function listDomains(): array
    {
        $d = $this->soap->call('Domains_List', [], critical: true);
        $out = [];
        foreach (SubregSoapGateway::listOf($d['domains'] ?? []) as $row) {
            if (! is_array($row) || empty($row['name'])) {
                continue;
            }
            $out[] = ['name' => strtolower((string) $row['name']), 'status' => '', 'expires_at' => self::date($row['expire'] ?? null), 'registered_at' => null, 'auto_renew' => isset($row['autorenew']) ? (int) $row['autorenew'] : null];
        }

        return $out;
    }

    // ── orders ───────────────────────────────────────────────────────────────

    public function register(string $fqdn, array $request, string $clTrid, bool $testMode = false): ProviderResult
    {
        $this->assertTestMode($testMode);
        $params = array_filter([
            'period' => (int) ($request['period'] ?? 1),
            'registrant' => ['id' => (string) ($request['registrant'] ?? '')],
            'contacts' => array_filter(['admin' => isset($request['admin']) ? ['id' => (string) $request['admin']] : null, 'tech' => isset($request['tech']) ? ['id' => (string) $request['tech']] : null]),
            'ns' => $this->nsParam($request['nameservers'] ?? null, $request['nsset'] ?? null),
            'params' => $this->dnssecParams((array) ($request['keyset'] ?? [])),
        ], fn ($v) => $v !== null && $v !== []);
        if (! empty($request['dns_template'])) {
            $params['dnstemp'] = (string) $request['dns_template'];
        }

        return $this->submit('Create_Domain', $fqdn, $params, 'domain-create', $clTrid, 60, 5 * 86400);
    }

    public function renew(string $fqdn, int $period, string $clTrid, bool $testMode = false): ProviderResult
    {
        $this->assertTestMode($testMode);
        $params = ['period' => $period];
        try {
            $current = $this->domainInfo($fqdn)['expires_at'];
            if ($current !== null) {
                $params['curExpDate'] = $current; // Subreg refuses a renewal whose expiry moved meanwhile (506/1008) — the double-renew guard
            }
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::NOT_FOUND) {
                throw $e;
            }
        }

        return $this->submit('Renew_Domain', $fqdn, $params, 'domain-renew', $clTrid, 60, 2 * 86400);
    }

    public function transferCheck(string $fqdn): array
    {
        $check = $this->soap->call('Check_Domain', ['domain' => $fqdn]);
        $registered = (int) ($check['avail'] ?? 1) === 0;
        $inSubreg = null;
        $mine = false;
        try {
            $d = $this->soap->call('In_Subreg', ['domain' => $fqdn]);
            $inSubreg = true;
            $mine = strtolower((string) ($d['myaccount'] ?? 'no')) === 'yes';
        } catch (ProviderException $e) {
            if (! in_array($e->errorCode, [ProviderErrorCode::NOT_FOUND, ProviderErrorCode::VALIDATION], true)) {
                throw $e;
            }
            $inSubreg = false;
        }

        return ['transferable' => $registered && ! $mine, 'detail' => ['registered' => $registered, 'in_registrar' => $inSubreg, 'own_account' => $mine, 'price' => $check['price_transfer'] ?? null]];
    }

    public function transferIn(string $fqdn, string $authInfo, array $request, string $clTrid): ProviderResult
    {
        $new = array_filter([
            'registrant' => isset($request['registrant']) ? ['id' => (string) $request['registrant']] : null,
            'admin' => isset($request['admin']) ? ['id' => (string) $request['admin']] : null,
            'ns' => $this->nsParam($request['nameservers'] ?? null, $request['nsset'] ?? null),
        ]);
        $params = array_filter(['authid' => $authInfo, 'new' => $new === [] ? null : $new, 'period' => isset($request['period']) ? (int) $request['period'] : null], fn ($v) => $v !== null);

        return $this->submit('Transfer_Domain', $fqdn, $params, 'domain-transfer', $clTrid, 300, 7 * 86400);
    }

    /** Subreg exposes the AUTH-ID in Info_Domain; ONhost delivers it to the registrant itself (`delivery: inline`). */
    public function sendAuthInfo(string $fqdn, string $clTrid): ProviderResult
    {
        $d = $this->soap->call('Info_Domain', ['domain' => $fqdn], critical: true);
        $authId = trim((string) ($d['authid'] ?? ''));
        if ($authId === '') {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, 'The registry does not expose an AUTH-ID for this domain', null, ['normalized' => SubregErrorMap::INVALID_REQUEST]);
        }

        return ProviderResult::completed(new ResourceRef('domain', $fqdn), ['auth_info' => $authId, 'delivery' => 'inline']);
    }

    public function updateNameservers(string $fqdn, array $nameservers, ?string $nsset, string $clTrid): ProviderResult
    {
        $ns = $this->nsParam($nsset === null ? $nameservers : null, $nsset);

        return $this->submit('ModifyNS_Domain', $fqdn, ['ns' => $ns], 'domain-update-ns', $clTrid, 60, 86400);
    }

    public function updateKeyset(string $fqdn, array $keyset, string $clTrid): ProviderResult
    {
        $params = $this->dnssecParams($keyset);
        if ($params === []) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, 'No keyset handle, DNSKEY or DS records to publish', null, ['normalized' => SubregErrorMap::INVALID_REQUEST]);
        }

        return $this->submit('Modify_Domain', $fqdn, ['params' => $params], 'domain-update-keyset', $clTrid, 60, 86400);
    }

    // ── contacts & objects ───────────────────────────────────────────────────

    public function createContact(array $contact, string $clTrid): array
    {
        $tld = strtolower((string) ($contact['tld'] ?? ''));
        $registryParams = [];
        if (in_array($tld, ['cz', 'ee'], true)) {
            $registryParams = array_filter([
                'vat' => $contact['dic'] ?? null,
                'ident_type' => ! empty($contact['ico']) ? 'ico' : null, 'ident_number' => ! empty($contact['ico']) ? (string) $contact['ico'] : null,
                'notify_email' => $contact['email'] ?? null,
                'disclose' => ((int) ($contact['disclose'] ?? 0) === 1) ? ['voice', 'email'] : null,
            ], fn ($v) => $v !== null && $v !== '');
        }
        $payload = array_filter([
            'name' => (string) ($contact['first_name'] ?? ''), 'surname' => (string) ($contact['last_name'] ?? ($contact['first_name'] ?? '')), 'org' => $contact['organization'] ?? null,
            'street' => (string) ($contact['street'] ?? ''), 'city' => (string) ($contact['city'] ?? ''), 'pc' => (string) ($contact['postal_code'] ?? ''), 'sp' => $contact['state'] ?? null,
            'cc' => strtoupper((string) ($contact['country'] ?? 'CZ')), 'phone' => self::e164((string) ($contact['phone'] ?? ''), strtoupper((string) ($contact['country'] ?? 'CZ'))),
            'email' => (string) ($contact['email'] ?? ''), 'params' => $registryParams === [] ? null : $registryParams,
        ], fn ($v) => $v !== null && $v !== '');
        $d = $this->soap->call('Create_Contact', ['contact' => $payload], critical: true);
        $id = (string) ($d['contactid'] ?? '');
        if ($id === '') {
            throw new ProviderException('subreg', ProviderErrorCode::PROVIDER_BUG, 'Subreg Create_Contact returned no contact id');
        }

        return ['remote_id' => $id, 'raw' => $d];
    }

    public function contactInfo(string $remoteId): array
    {
        return $this->soap->call('Info_Contact', ['contact' => ['id' => $remoteId]]);
    }

    public function updateContact(string $remoteId, array $contact, string $clTrid): ProviderResult
    {
        $payload = array_filter([
            'id' => $remoteId, 'name' => $contact['first_name'] ?? null, 'surname' => $contact['last_name'] ?? null, 'org' => $contact['organization'] ?? null, 'street' => $contact['street'] ?? null,
            'city' => $contact['city'] ?? null, 'pc' => $contact['postal_code'] ?? null, 'sp' => $contact['state'] ?? null, 'cc' => isset($contact['country']) ? strtoupper((string) $contact['country']) : null,
            'phone' => isset($contact['phone']) ? self::e164((string) $contact['phone'], strtoupper((string) ($contact['country'] ?? 'CZ'))) : null, 'email' => $contact['email'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $d = $this->soap->call('Update_Contact', ['contact' => $payload], critical: true);

        return ProviderResult::completed(new ResourceRef('contact', $remoteId), $d);
    }

    public function createNsset(string $handle, array $nameservers, string $techContact, string $clTrid): ProviderResult
    {
        try {
            $check = $this->soap->call('Check_Object', ['object' => 'nsset', 'id' => $handle]);
            if ((int) ($check['avail'] ?? 1) === 0) {
                return ProviderResult::completed(new ResourceRef('nsset', $handle), ['existing' => true], alreadyExisted: true);
            }
        } catch (ProviderException $e) {
            if (! in_array($e->errorCode, [ProviderErrorCode::NOT_FOUND, ProviderErrorCode::VALIDATION], true)) {
                throw $e;
            }
        }
        $hosts = array_values(array_map(fn ($ns) => is_array($ns) ? array_filter(['hostname' => (string) ($ns['name'] ?? $ns['hostname'] ?? ''), 'ipv4' => $ns['ipv4'] ?? ($ns['addr'][0] ?? null)], fn ($v) => $v !== null && $v !== '') : ['hostname' => (string) $ns], $nameservers));
        $params = ['type' => 'nsset', 'registry' => 'CZ-NIC', 'params' => [['tech' => ['id' => $techContact], 'hosts' => $hosts]]];
        $order = $this->soap->order('Create_Object', null, $params, $handle, true);
        $ref = new ResourceRef('nsset', $handle, null, ['order_id' => $order['orderid']]);
        $status = $this->orderStatus($order['orderid']);
        if (SubregErrorMap::orderCompleted($status['status'])) {
            return ProviderResult::completed($ref, $status);
        }
        if (SubregErrorMap::orderFailed($status['status'])) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, 'Subreg refused the NSSET order: '.($status['message'] ?: $status['status']), $status['errorcode'] ?: null, ['normalized' => SubregErrorMap::INVALID_REQUEST, 'order' => $status]);
        }

        return ProviderResult::accepted(new AsyncHandle('subreg_order', $handle, null, ['command' => 'nsset-create', 'order_id' => $order['orderid'], 'clTRID' => $clTrid, 'object' => 'nsset'], 30, 3600), $ref, $status);
    }

    public function nssetInfo(string $handle): array
    {
        $d = $this->soap->call('Info_Object', ['object' => 'nsset', 'id' => $handle]);

        return is_array($d['nsset'] ?? null) ? $d['nsset'] + ['id' => $d['id'] ?? $handle] : $d;
    }

    // ── account ──────────────────────────────────────────────────────────────

    public function creditInfo(): array
    {
        $d = $this->soap->call('Get_Credit', [], critical: true);
        $credit = is_array($d['credit'] ?? null) ? $d['credit'] : $d;

        return ['balance' => (string) ($credit['amount'] ?? '0'), 'currency' => strtoupper((string) ($credit['currency'] ?? 'CZK')), 'reserved' => (string) ($credit['reserved'] ?? '0'), 'threshold' => (string) ($credit['threshold'] ?? '0')];
    }

    public function accountMovements(?string $from = null, ?string $to = null): array
    {
        $d = $this->soap->call('Get_Accountings', ['from' => $from ?? now()->subDays(30)->toDateString(), 'to' => $to ?? now()->toDateString()], critical: true);

        return array_values(array_filter(SubregSoapGateway::listOf($d['accounting'] ?? []), 'is_array'));
    }

    public function costPrices(array $tlds): array
    {
        $currency = $this->creditInfo()['currency'];
        $out = [];
        foreach ($tlds as $tld) {
            $tld = ltrim(strtolower((string) $tld), '.');
            try {
                $d = $this->soap->call('Prices', ['tld' => $tld]);
            } catch (ProviderException $e) {
                if (in_array($e->errorCode, [ProviderErrorCode::VALIDATION, ProviderErrorCode::NOT_FOUND], true)) {
                    continue; // TLD not sold by this registrar
                }
                throw $e;
            }
            $prices = [];
            foreach (SubregSoapGateway::listOf($d['prices'] ?? []) as $row) {
                if (is_array($row) && isset($row['type'])) {
                    $prices[strtolower((string) $row['type'])] = (string) ($row['value'] ?? '');
                }
            }
            foreach (['register', 'renew', 'transfer', 'restore'] as $op) { // non-WSDL style: flat keys
                if (! isset($prices[$op]) && isset($d[$op]) && ! is_array($d[$op])) {
                    $prices[$op] = (string) $d[$op];
                }
            }
            if ($prices === []) {
                continue;
            }
            $out[$tld] = [
                'currency' => strtoupper((string) ($d['currency'] ?? $currency)), 'register' => $prices['register'] ?? null, 'renew' => $prices['renew'] ?? null,
                'transfer' => $prices['transfer'] ?? null, 'restore' => $prices['restore'] ?? null, 'min_years' => isset($d['minyear']) ? (int) $d['minyear'] : null, 'max_years' => isset($d['maxyear']) ? (int) $d['maxyear'] : null,
            ];
        }

        return $out;
    }

    // ── notifications ────────────────────────────────────────────────────────

    public function pollRequest(): ?array
    {
        $d = $this->soap->call('POLL_Get', [], critical: true);
        $id = (string) ($d['id'] ?? '');
        if ($id === '' || (int) ($d['count'] ?? 0) === 0 && $id === '0') {
            return null;
        }
        $orderId = (string) ($d['orderid'] ?? '');
        $status = (string) ($d['orderstatus'] ?? '');
        $fqdn = null;
        $type = 'order';
        if ($orderId !== '') {
            try {
                $order = $this->orderStatus($orderId);
                $fqdn = $order['domain'] ?: null;
                $type = $order['type'] ?: 'order';
                $status = $status !== '' ? $status : $order['status'];
            } catch (ProviderException) {
                // the poll message alone is still processable; the worker refreshes domain-info for known domains
            }
        }

        return ['id' => $id, 'kind' => strtolower($type).'.'.strtolower($status ?: 'notice'), 'fqdn' => $fqdn, 'payload' => ['order_id' => $orderId, 'status' => $status, 'message' => (string) ($d['message'] ?? ''), 'errorcode' => (string) ($d['errorcode'] ?? ''), 'date' => $d['date'] ?? null, 'raw' => $d]];
    }

    public function pollAck(string $notificationId): void
    {
        $this->soap->call('POLL_Ack', ['id' => (int) $notificationId], critical: true);
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        $orderId = (string) ($handle->meta['order_id'] ?? '');
        $command = (string) ($handle->meta['command'] ?? '');
        if ($orderId !== '') {
            try {
                $order = $this->orderStatus($orderId);
            } catch (ProviderException $e) {
                if ($e->errorCode === ProviderErrorCode::TRANSIENT) {
                    return AsyncStatus::running('registrar temporarily unavailable');
                }

                return AsyncStatus::unknown($e->getMessage());
            }
            if (SubregErrorMap::orderFailed($order['status'])) {
                return AsyncStatus::failed('registrar order '.$order['status'].($order['message'] !== '' ? ': '.$order['message'] : ''), $order);
            }
            if (! SubregErrorMap::orderCompleted($order['status'])) {
                return AsyncStatus::running("order {$order['status']}", $order);
            }
            if (($handle->meta['object'] ?? null) !== null) {
                return AsyncStatus::succeeded($order);
            }
        }
        try {
            $info = $this->domainInfo($handle->handle);
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return $orderId !== '' ? AsyncStatus::running('order completed, domain not visible yet') : AsyncStatus::failed('domain is not registered on this account', []);
            }

            return AsyncStatus::unknown($e->getMessage());
        }
        $status = strtolower($info['status']);
        if ($command === 'domain-transfer') {
            return $status === 'pending' ? AsyncStatus::running('transfer pending at the registry') : AsyncStatus::succeeded($info);
        }

        return $status === 'pending' ? AsyncStatus::running('registry status pending') : AsyncStatus::succeeded($info);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $params */
    private function submit(string $type, string $fqdn, array $params, string $command, string $clTrid, int $pollInterval, int $timeout): ProviderResult
    {
        $order = $this->soap->order($type, $fqdn, $params, null, true);
        $ref = new ResourceRef('domain', $fqdn, null, ['order_id' => $order['orderid']]);
        $status = $this->orderStatus($order['orderid']);
        if (SubregErrorMap::orderFailed($status['status'])) {
            $mapped = SubregErrorMap::map(0, 0, $status['message']);

            throw new ProviderException('subreg', $mapped['code'] === ProviderErrorCode::UNKNOWN ? ProviderErrorCode::VALIDATION : $mapped['code'], "Subreg order {$type} {$status['status']}: ".($status['message'] ?: 'refused by the registry'), $status['errorcode'] ?: null, ['normalized' => $mapped['normalized'] === SubregErrorMap::UNKNOWN ? SubregErrorMap::INVALID_REQUEST : $mapped['normalized'], 'order' => $status]);
        }
        if (SubregErrorMap::orderCompleted($status['status'])) {
            return ProviderResult::completed($ref, $status);
        }

        return ProviderResult::accepted(new AsyncHandle('subreg_order', $fqdn, null, ['command' => $command, 'order_id' => $order['orderid'], 'clTRID' => $clTrid], $pollInterval, $timeout), $ref, $status);
    }

    /** @return array{id:string, domain:string, type:string, status:string, message:string, errorcode:string, payed:string, amount:string} */
    private function orderStatus(string $orderId): array
    {
        $d = $this->soap->call('Info_Order', ['order' => (int) $orderId], critical: true);
        $o = is_array($d['order'] ?? null) ? $d['order'] : $d;

        return [
            'id' => (string) ($o['id'] ?? $orderId), 'domain' => strtolower((string) ($o['domain'] ?? '')), 'type' => (string) ($o['type'] ?? ''), 'status' => (string) ($o['status'] ?? ''),
            'message' => (string) ($o['message'] ?? ''), 'errorcode' => (string) ($o['errorcode'] ?? ''), 'payed' => (string) ($o['payed'] ?? ''), 'amount' => (string) ($o['amount'] ?? ''),
        ];
    }

    private function assertTestMode(bool $testMode): void
    {
        if ($testMode && ! $this->soap->isDemo()) {
            throw new ProviderException('subreg', ProviderErrorCode::VALIDATION, 'Subreg has no test flag; use a demoreg.net instance (options.demo=true) for test-mode registrations', null, ['normalized' => SubregErrorMap::INVALID_REQUEST]);
        }
    }

    /** @return array<string,mixed>|null */
    private function nsParam(?array $nameservers, ?string $nsset): ?array
    {
        if ($nsset !== null && $nsset !== '') {
            return ['nsset' => $nsset];
        }
        if ($nameservers === null || $nameservers === []) {
            return null;
        }
        $hosts = [];
        foreach ($nameservers as $ns) {
            $host = array_filter(is_array($ns) ? ['hostname' => (string) ($ns['name'] ?? $ns['hostname'] ?? ''), 'ipv4' => $ns['ipv4'] ?? null, 'ipv6' => $ns['ipv6'] ?? null] : ['hostname' => (string) $ns], fn ($v) => $v !== null && $v !== '');
            if (($host['hostname'] ?? '') !== '') {
                $hosts[] = $host;
            }
        }

        return $hosts === [] ? null : ['hosts' => $hosts];
    }

    /** Keyset handle / DNSKEY / DS records → Make_Order `params` list (DNSSEC appendix). @return list<array<string,mixed>> */
    private function dnssecParams(array $keyset): array
    {
        $params = [];
        if (! empty($keyset['handle'])) {
            $params[] = ['param' => 'keyset', 'value' => (string) $keyset['handle']];
        }
        $keys = [];
        foreach ((array) ($keyset['dnskey'] ?? []) as $key) {
            if (is_array($key)) {
                $keys[] = array_filter(['tag' => $key['tag'] ?? null, 'flag' => $key['flags'] ?? $key['flag'] ?? 257, 'protocol' => $key['protocol'] ?? 3, 'alg' => $key['alg'] ?? $key['algorithm'] ?? null, 'key' => $key['key'] ?? $key['public_key'] ?? null], fn ($v) => $v !== null);
            }
        }
        if ($keys !== []) {
            $params[] = ['keydata' => $keys];
        }
        $ds = [];
        foreach ((array) ($keyset['ds'] ?? []) as $record) {
            if (is_string($record)) {
                $parts = preg_split('/\s+/', trim($record), 4) ?: [];
                if (count($parts) === 4) {
                    $ds[] = ['tag' => (int) $parts[0], 'alg' => (int) $parts[1], 'digest_type' => (int) $parts[2], 'digest' => strtoupper(str_replace(' ', '', $parts[3]))];
                }
            } elseif (is_array($record)) {
                $ds[] = array_filter(['tag' => $record['tag'] ?? $record['keytag'] ?? null, 'alg' => $record['alg'] ?? $record['algorithm'] ?? null, 'digest_type' => $record['digest_type'] ?? $record['digesttype'] ?? null, 'digest' => $record['digest'] ?? null], fn ($v) => $v !== null);
            }
        }
        if ($ds !== []) {
            $params[] = ['dsdata' => $ds];
        }
        if (isset($keyset['internal_dnssec'])) {
            $params[] = ['param' => 'internal_dnssec', 'value' => $keyset['internal_dnssec'] ? '1' : '0'];
        }

        return $params;
    }

    /** @param list<string> $statuses */
    private static function normaliseStatus(array $statuses, string $quarantined): string
    {
        $lower = array_map('strtolower', $statuses);
        if ($quarantined !== '' && $quarantined !== '0') {
            return 'redemption';
        }
        foreach ($lower as $s) {
            if (str_contains($s, 'pendingdelete') || str_contains($s, 'redemption')) {
                return 'redemption';
            }
        }
        foreach ($lower as $s) {
            if (str_contains($s, 'pending')) {
                return 'pending';
            }
        }
        foreach ($lower as $s) {
            if (str_contains($s, 'expired')) {
                return 'expired';
            }
        }

        return 'active';
    }

    private static function date(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        return substr($value, 0, 10);
    }

    /** Subreg wants E.164 with a dot after the country code (`+420.123456789`). */
    public static function e164(string $phone, string $country = 'CZ'): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        if (preg_match('/^\+\d{1,3}\.\d{3,}$/', $phone)) {
            return $phone;
        }
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }
        $codes = ['CZ' => '420', 'SK' => '421', 'PL' => '48', 'DE' => '49', 'AT' => '43', 'HU' => '36', 'GB' => '44', 'FR' => '33', 'IT' => '39', 'ES' => '34', 'NL' => '31', 'BE' => '32', 'US' => '1'];
        if (! str_starts_with($digits, '+')) {
            $digits = '+'.($codes[$country] ?? '420').ltrim($digits, '0');
        }
        foreach (array_unique(array_merge([$codes[$country] ?? '420'], array_values($codes))) as $code) {
            if (str_starts_with($digits, '+'.$code)) {
                return '+'.$code.'.'.substr($digits, strlen($code) + 1);
            }
        }
        $code = substr($digits, 1, 2);

        return '+'.$code.'.'.substr($digits, 3);
    }
}
