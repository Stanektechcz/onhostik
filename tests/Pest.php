<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpPool;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Contract', 'Unit');

// Contract tests must never reach a real vendor API.
pest()->beforeEach(fn () => Http::preventStrayRequests())->in('Contract');

// A credential a test puts into $_ENV or putenv() must not outlive it: the `env` secrets driver reads both, so a leftover
// key made later tests see a configured gateway or panel depending on file order (CheckoutTest → RenewalGuardTest).
// The snapshot is taken after the application booted (its .env is in place) and before the test file's own hooks run.
pest()->beforeEach(function () {
    $this->envBaseline = ['env' => $_ENV, 'process' => getenv()];
})->afterEach(fn () => restoreEnvironment($this->envBaseline))->in('Feature', 'Contract', 'Unit');

/** Undoes every $_ENV and putenv() key a test added, changed or removed, leaving the rest of the process environment alone. */
function restoreEnvironment(array $baseline): void
{
    foreach (array_diff_key($_ENV, $baseline['env']) as $key => $value) {
        unset($_ENV[$key]);
    }
    foreach ($baseline['env'] as $key => $value) {
        $_ENV[$key] = $value;
    }
    foreach (array_diff_key(getenv(), $baseline['process']) as $key => $value) {
        putenv($key);
    }
    foreach ($baseline['process'] as $key => $value) {
        if (getenv($key) !== $value) {
            putenv($key.'='.$value);
        }
    }
}

expect()->extend('toBeMoney', function (int $minor, string $currency) {
    return $this->minor->toBe($minor)->and($this->value->currency->value)->toBe($currency);
});

/*
|--------------------------------------------------------------------------
| Shared provider doubles (used by Feature tests across domains)
|--------------------------------------------------------------------------
*/

/**
 * In-memory WEDOS registry double driven through the real WAPI JSON envelope.
 * $state keys: registered (false|'pending'|true), nsset (bool), expiration (Y-m-d), created, async (bool),
 * listing (domains-list rows), credit, queue (poll events), unknown (names `domain-info` answers "not found" for).
 * Commands sent are appended to $state['commands'].
 */
function registryFake(array &$state): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$state) {
        if (! $request->isForm() || ! isset($request->data()['request'])) {
            throw new RuntimeException('Non-WAPI request reached the registry double: '.$request->method().' '.$request->url().' '.$request->body());
        }
        $payload = json_decode((string) $request['request'], true)['request'];
        $command = $payload['command'];
        $data = (array) ($payload['data'] ?? []);
        $state['commands'][] = $command;
        $overrides = match ($command) {
            'contact-create' => ['data' => ['contact' => ['cname' => $data['cname']]]],
            'nsset-info' => $state['nsset'] ? ['data' => ['nsset' => ['nsset' => $data['nsset']]]] : ['code' => 2303, 'result' => 'Object not found'],
            'nsset-create' => (function () use (&$state) {
                $state['nsset'] = true;

                return [];
            })(),
            'domain-create' => (function () use (&$state) {
                $state['registered'] = ($state['async'] ?? false) ? 'pending' : true;

                return ($state['async'] ?? false) ? ['code' => 1001, 'result' => 'Accepted'] : [];
            })(),
            'domain-info' => (function () use (&$state, $data) {
                if ($state['registered'] === false || in_array($data['name'] ?? '', (array) ($state['unknown'] ?? []), true)) { // `unknown`: names this registrar does not have
                    return ['code' => 3222, 'result' => 'Domain not found'];
                }
                if ($state['registered'] === 'pending') {
                    $state['info_calls'] = ($state['info_calls'] ?? 0) + 1;
                    if ($state['info_calls'] >= 2) {
                        $state['registered'] = true;
                    }
                }

                return ['data' => ['domain' => ['name' => $data['name'], 'status' => $state['info_status'] ?? ($state['registered'] === true ? 'active' : 'pending'), 'expiration' => $state['expiration'], 'created' => $state['created'] ?? '2026-09-06', 'dns' => [['name' => 'ns1.onhost.cz'], ['name' => 'ns2.onhost.cz']], 'owner_c' => 'ONH-X', 'nsset' => 'NSSET-ONHOST']]];
            })(),
            'domain-renew' => (function () use (&$state, $data) {
                if ($state['renew_refused'] ?? false) { // opt-in: the registry refuses the renewal for good (a status that prohibits it)
                    return ['code' => 2304, 'result' => 'Object status prohibits operation'];
                }
                $state['expiration'] = (new DateTimeImmutable($state['expiration']))->modify('+'.((int) $data['period']).' year')->format('Y-m-d');

                return [];
            })(),
            // live WAPI: a free name answers 1000 with `data.name` only, a registered one 3201 "Domain is registered"
            'domain-check' => ($state['registered'] ?? false) === false ? ['data' => ['name' => $data['name'] ?? '']] : ['code' => 3201, 'result' => 'Domain is registered'],
            'domains-list' => ['data' => ['domain' => $state['listing'] ?? []]],
            'credit-info' => ['data' => ['credit' => $state['credit'] ?? '25000.00', 'currency' => 'CZK']],
            'poll-req' => ($event = array_shift($state['queue'])) === null ? ['data' => []] : ['data' => ['event' => $event]],
            'domain-send-auth-info', 'poll-ack', 'domain-update-ns', 'ping' => [],
            default => ['code' => 2100, 'result' => "unexpected {$command}"],
        };

        return Http::response(['response' => array_merge(['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'], 'svTRID' => 'sv-'.uniqid(), 'command' => $command, 'data' => []], $overrides)]);
    }]);
}

/** A domain of an organization at the WEDOS registrar, in a given state (domain lifecycle tests). */
function graceDomain(Organization $org, string $fqdn, string $state, DateTimeInterface $expires): Domain
{
    $contact = RegistrarContact::query()->firstOrCreate(['organization_id' => $org->id, 'remote_id' => 'ONH-X'], ['kind' => 'registrant', 'name' => 'Jana Nováková', 'email' => 'jana@example.cz', 'country' => 'CZ', 'state' => 'synced']);

    return Domain::query()->create(['organization_id' => $org->id, 'fqdn_ascii' => $fqdn, 'fqdn_unicode' => $fqdn, 'tld' => 'cz', 'state' => $state, 'registered_at' => now()->subYear(), 'expires_at' => $expires,
        'auto_renew' => true, 'renewal_period' => 1, 'dns_provider' => 'external', 'registrar_provider' => 'wedos', 'registrant_contact_id' => $contact->id, 'admin_contact_id' => $contact->id]);
}

/** PowerDNS double for one zone: first existence check 404, afterwards the zone exists with an empty RRset. */
function pdnsZoneFake(string $zone): void
{
    $base = 'pdns.mgmt.test:8081/api/v1/servers/localhost/zones';
    Http::fake([
        "{$base}/{$zone}.?rrsets=false" => Http::sequence()->push(['error' => 'Not Found'], 404)->whenEmpty(Http::response(['name' => "{$zone}.", 'serial' => 2026090601])),
        $base => Http::response(['name' => "{$zone}.", 'serial' => 2026090601], 201),
        "{$base}/{$zone}./metadata" => Http::response([], 201),
        "{$base}/{$zone}./notify" => Http::response([], 200),
        "{$base}/{$zone}." => Http::response(['name' => "{$zone}.", 'rrsets' => []]),
    ]);
}

const PVE = 'pve.mgmt.test:8006/api2/json';

/** Proxmox lab: region cz1, one compute node, small RFC 5737/3849 IP pools. Idempotent. */
function pveLab(): ProviderInstance
{
    $_ENV['PROXMOX_CZ1_TOKEN_ID'] = 'onhost@pve!cp';
    $_ENV['PROXMOX_CZ1_TOKEN_SECRET'] = 'deadbeef-0000';
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'proxmox-cz1'], [
        'provider' => 'proxmox', 'name' => 'PVE CZ1', 'region_code' => 'cz1', 'base_url' => 'https://pve.mgmt.test:8006', 'secret_ref' => 'env://PROXMOX_CZ1', 'state' => 'active',
        'capabilities' => ['vm.create' => true, 'compute' => true, 'console' => true, 'vm.backup' => true],
        'options' => ['default_node' => 'prg1-n2', 'storage' => 'local-zfs', 'backup_storage' => 'pbs-cz1', 'templates' => ['debian-13' => 9001], 'template_node' => 'prg1-n2', 'os_disk' => 'scsi0', 'verify_tls' => false], 'adapter_version' => '1.0.0',
    ]);
    Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'prg1-n2'], ['region_code' => 'cz1', 'role' => 'compute', 'state' => 'active', 'capacity' => ['cpu_cores' => 64, 'ram_mb' => 262144, 'disk_gb' => 4000], 'usage' => ['cpu_pct' => 12, 'ram_used_mb' => 40000, 'disk_used_gb' => 500, 'io_wait_pct' => 1], 'failure_domain' => 'rack-a']);
    $ipam = app(IpamService::class);
    $v4 = IpPool::query()->firstOrCreate(['cidr' => '192.0.2.0/29', 'family' => 4], ['region_code' => 'cz1', 'purpose' => 'vps', 'gateway' => '192.0.2.1', 'dns' => ['9.9.9.9'], 'state' => 'active', 'reserve_count' => 1]);
    $v6 = IpPool::query()->firstOrCreate(['cidr' => '2001:db8:1::/48', 'family' => 6], ['region_code' => 'cz1', 'purpose' => 'vps', 'gateway' => '2001:db8:1::1', 'dns' => ['2620:fe::fe'], 'state' => 'active', 'reserve_count' => 0]);
    $ipam->populate($v4);
    $ipam->populate($v6, 4);

    return $instance;
}

/** PowerDNS hidden primary instance (pair with pdnsZoneFake()). */
function pdnsLab(): ProviderInstance
{
    $_ENV['POWERDNS_HIDDEN01_API_KEY'] = 'pdns-key';

    return ProviderInstance::query()->firstOrCreate(['key' => 'powerdns-hidden01'], [
        'provider' => 'powerdns', 'name' => 'PowerDNS hidden primary', 'base_url' => 'http://pdns.mgmt.test:8081', 'secret_ref' => 'env://POWERDNS_HIDDEN01', 'state' => 'active',
        'capabilities' => ['dns' => true], 'options' => ['nameservers' => ['ns1.onhost.cz', 'ns2.onhost.cz']], 'adapter_version' => '1.0.0',
    ]);
}

function pveVmConfig(array $overrides = []): array
{
    return ['data' => array_merge(['cores' => 4, 'sockets' => 1, 'memory' => 8192, 'scsi0' => 'local-zfs:vm-1042-disk-0,size=20G', 'name' => 'vm-test', 'tags' => 'onhost', 'agent' => '1', 'onboot' => 1], $overrides)];
}

/**
 * Play the scheduler for one operation: advance the clock to its next_run_at and dispatch until it is terminal.
 * With the sync queue a job never re-dispatches itself, exactly like production without `onhost:provisioning:tick`.
 */
function driveOperation(Operation $operation, int $maxTicks = 60): Operation
{
    $operations = app(OperationService::class);
    for ($i = 0; $i < $maxTicks; $i++) {
        $operation->refresh();
        if ($operation->isTerminal() || $operation->state === Operation::FAILED) {
            break;
        }
        if ($operation->next_run_at !== null && $operation->next_run_at->isFuture()) {
            Date::setTestNow($operation->next_run_at->copy()->addSecond());
        }
        $operations->dispatch($operation);
    }

    return $operation->refresh();
}

/** Drive every non-terminal operation (fulfilment fan-out) until the queue is quiet. */
function driveOperations(int $maxRounds = 20): void
{
    for ($round = 0; $round < $maxRounds; $round++) {
        $pending = Operation::query()->whereIn('state', [Operation::PENDING, Operation::WAITING])->orderBy('next_run_at')->get();
        if ($pending->isEmpty()) {
            return;
        }
        foreach ($pending as $operation) {
            driveOperation($operation);
        }
    }
}

/** WAPI commands recorded so far, in order. @return list<string> */
function wapiCommands(): array
{
    return collect(Http::recorded())
        ->filter(fn (array $p) => str_contains($p[0]->url(), 'api.wedos.com') && $p[0]->isForm())
        ->map(fn (array $p) => json_decode((string) ($p[0]->data()['request'] ?? ''), true)['request']['command'] ?? null)
        ->filter()->values()->all();
}

// ── Subreg SOAP doubles ──────────────────────────────────────────────────────

/** Folds the unqualified children of a SOAP element into arrays (repeated names → lists). @return array<string,mixed>|string */
function subregXmlToArray(SimpleXMLElement $element): array|string
{
    $children = [];
    foreach ($element->children() as $child) {
        $children[$child->getName()][] = subregXmlToArray($child);
    }
    if ($children === []) {
        return trim((string) $element);
    }
    $out = [];
    foreach ($children as $name => $values) {
        $out[$name] = count($values) === 1 ? $values[0] : $values;
    }

    return $out;
}

/** @return array{0:string,1:array<string,mixed>} function name and parameters of a Subreg request envelope */
function subregRequest(Request $request): array
{
    $xml = simplexml_load_string($request->body());
    $body = $xml->children('http://schemas.xmlsoap.org/soap/envelope/')->Body;
    $call = $body->children('http://subreg.cz/types');
    $function = $call->getName();
    $params = subregXmlToArray($call);

    return [$function, is_array($params) ? $params : []];
}

/** @param array<string,mixed>|string $value */
function subregXml(string $name, mixed $value): string
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            return implode('', array_map(fn ($v) => subregXml($name, $v), $value));
        }
        $inner = '';
        foreach ($value as $k => $v) {
            $inner .= subregXml((string) $k, $v);
        }

        return "<{$name}>{$inner}</{$name}>";
    }

    return "<{$name}>".htmlspecialchars(is_bool($value) ? ($value ? '1' : '0') : (string) $value, ENT_XML1)."</{$name}>";
}

/**
 * Subreg SOAP double. The handler receives (function, params) and returns `['data' => …]`, `[]` (status ok, no data)
 * or `['error' => [message, major, minor]]`.
 *
 * @param  callable(string, array<string,mixed>): array  $handler
 */
function subregFake(callable $handler): void
{
    $respond = function (Request $request) use ($handler) {
        [$function, $params] = subregRequest($request);
        $result = $handler($function, $params);
        if (isset($result['fault'])) {
            return Http::response('<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><SOAP-ENV:Fault><faultcode>SOAP-ENV:Server</faultcode><faultstring>'.htmlspecialchars((string) $result['fault'], ENT_XML1).'</faultstring></SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>', 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }
        if (isset($result['error'])) {
            [$message, $major, $minor] = $result['error'];
            $inner = '<status>error</status><error><errormsg>'.htmlspecialchars($message, ENT_XML1)."</errormsg><errorcode><major>{$major}</major><minor>{$minor}</minor></errorcode></error>";
        } else {
            $inner = '<status>ok</status>'.(isset($result['data']) ? subregXml('data', $result['data']) : '');
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://subreg.cz/types"><SOAP-ENV:Body><ns1:'.$function.'_Container><response>'.$inner.'</response></ns1:'.$function.'_Container></SOAP-ENV:Body></SOAP-ENV:Envelope>';

        return Http::response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    };
    Http::fake(['subreg.cz/*' => $respond, 'demoreg.net/*' => $respond]);
}

/** Subreg functions recorded so far, in order. @return list<string> */
function subregCalls(): array
{
    return collect(Http::recorded())
        ->filter(fn (array $p) => str_contains($p[0]->url(), 'subreg.cz') || str_contains($p[0]->url(), 'demoreg.net'))
        ->map(fn (array $p) => subregRequest($p[0])[0])->values()->all();
}

/** @return list<array<string,mixed>> params of every recorded call of one function */
function subregParams(string $function): array
{
    return collect(Http::recorded())
        ->filter(fn (array $p) => str_contains($p[0]->url(), 'subreg.cz') || str_contains($p[0]->url(), 'demoreg.net'))
        ->map(fn (array $p) => subregRequest($p[0]))->filter(fn (array $r) => $r[0] === $function)->map(fn (array $r) => $r[1])->values()->all();
}

/**
 * Stateful Subreg registry double for the domain sagas: orders complete immediately unless `$state['async']`,
 * `$state['registered']` (false|'pending'|true), `$state['expiration']`, `$state['prices']` per TLD, `$state['queue']` poll events.
 */
function subregRegistryFake(array &$state): void
{
    $state += ['registered' => false, 'expiration' => '2027-09-06', 'orders' => [], 'contacts' => 0, 'nsset' => false, 'prices' => [], 'queue' => [], 'listing' => [], 'credit' => '12500.00', 'currency' => 'CZK', 'commands' => []];
    subregFake(function (string $function, array $params) use (&$state): array {
        $state['commands'][] = $function;
        $placeOrder = function (string $type, string $domain) use (&$state): array {
            $id = (string) (100 + count($state['orders']));
            $status = ($state['async'] ?? false) ? 'Pending' : 'Completed';
            $state['orders'][$id] = ['type' => $type, 'domain' => $domain, 'status' => $status];
            if ($type === 'Create_Domain') {
                $state['registered'] = $status === 'Completed' ? true : 'pending';
            }
            if ($type === 'Create_Object') {
                $state['nsset'] = true;
            }
            if ($type === 'Renew_Domain' && $status === 'Completed') {
                $state['expiration'] = (new DateTimeImmutable($state['expiration']))->modify('+'.((int) ($state['last_period'] ?? 1)).' year')->format('Y-m-d');
            }

            return ['data' => ['orderid' => $id]];
        };

        return match ($function) {
            'Login' => ['data' => ['ssid' => 'ssid-'.substr(sha1((string) ($params['login'] ?? '')), 0, 8)]],
            'Check_Domain' => ['data' => ['name' => $params['domain'], 'avail' => $state['registered'] === false ? 1 : 0, 'price' => ['amount' => $state['prices'][substr((string) $params['domain'], strrpos((string) $params['domain'], '.') + 1)]['register'] ?? '140.00', 'premium' => ($state['premium'] ?? false) ? 1 : 0, 'currency' => $state['currency']]]],
            'Get_TLD_Info' => ['data' => ['periodsCreate' => ['1', '2', '3'], 'periodsRenew' => ['1', '2'], 'transfer' => '1', 'ns' => 'hosts']],
            'Create_Contact' => ['data' => ['contactid' => 'G-'.str_pad((string) (++$state['contacts']), 6, '0', STR_PAD_LEFT)]],
            'Info_Contact' => ['data' => ['id' => $params['contact']['id'] ?? 'G-000001', 'name' => 'Jana', 'surname' => 'Nováková', 'email' => 'jana@example.cz', 'cc' => 'CZ']],
            'Check_Object' => ['data' => ['id' => $params['id'] ?? '', 'avail' => $state['nsset'] ? 0 : 1]],
            'Info_Object' => ['data' => ['id' => $params['id'] ?? '', 'type' => 'nsset', 'nsset' => ['tech' => 'G-000001', 'ns' => [['hostname' => 'ns1.onhost.cz'], ['hostname' => 'ns2.onhost.cz']], 'clID' => 'REG-SUBREG']]],
            'Make_Order' => (function () use (&$state, $params, $placeOrder) {
                $type = (string) ($params['order']['type'] ?? '');
                $state['last_period'] = (int) ($params['order']['params']['period'] ?? 1);
                if ($type === 'Create_Domain' && $state['registered'] === true) {
                    return ['error' => ['Domain is not available', 506, 1001]];
                }

                return $placeOrder($type, (string) ($params['order']['domain'] ?? $params['order']['object'] ?? ''));
            })(),
            'Info_Order' => (function () use (&$state, $params) {
                $id = (string) ($params['order'] ?? '');
                $o = $state['orders'][$id] ?? null;
                if ($o === null) {
                    return ['error' => ['You are not allowed for this order', 506, 1001]];
                }
                if ($o['status'] === 'Pending') { // the second look completes the order
                    $state['orders'][$id]['status'] = 'Completed';
                    if ($o['type'] === 'Create_Domain') {
                        $state['registered'] = true;
                    }
                }

                return ['data' => ['order' => ['id' => $id, 'domain' => $o['domain'], 'type' => $o['type'], 'status' => $o['status'], 'message' => '', 'payed' => '1', 'amount' => '140.00']]];
            })(),
            'Info_Domain' => $state['registered'] === false
                ? ['error' => ['You are not allowed for this domain!', 501, 1004]]
                : ['data' => ['domain' => $params['domain'], 'contacts' => ['admin' => [['subregid' => 'G-000001', 'registryid' => 'ONH-1']]], 'hosts' => $state['hosts'] ?? ['ns1.onhost.cz', 'ns2.onhost.cz'], 'registrant' => ['subregid' => 'G-000001'], 'exDate' => $state['expiration'], 'crDate' => $state['created'] ?? '2026-09-06', 'authid' => 'Auth-Secret-1', 'status' => $state['registered'] === 'pending' ? ['pendingCreate'] : ['ok'], 'autorenew' => 0, 'premium' => ($state['premium'] ?? false) ? 1 : 0, 'options' => ['nsset' => $state['nsset'] ? 'NSSET-ONHOST' : '']]],
            'Domains_List' => ['data' => ['count' => count($state['listing']), 'domains' => $state['listing']]],
            'Get_Credit' => ['data' => ['credit' => ['amount' => $state['credit'], 'reserved' => '0.00', 'threshold' => '0.00', 'users' => '0.00', 'currency' => $state['currency']]]],
            'Prices' => isset($state['prices'][$params['tld']])
                ? ['data' => ['tld' => $params['tld'], 'minyear' => 1, 'maxyear' => 10, 'prices' => array_map(fn ($type, $value) => ['type' => $type, 'value' => $value], array_keys($state['prices'][$params['tld']]), array_values($state['prices'][$params['tld']]))]]
                : ['error' => ['TLD is not supported', 605, 1004]],
            'In_Subreg' => $state['registered'] === true ? ['data' => ['myaccount' => 'yes']] : ['error' => ['Domain does not exist', 507, 1009]],
            'POLL_Get' => ($event = array_shift($state['queue'])) === null ? ['data' => ['count' => 0]] : ['data' => ['count' => count($state['queue']) + 1] + $event],
            'POLL_Ack', 'Set_Autorenew', 'Update_Contact' => [],
            default => ['error' => ["unexpected {$function}", 505, 1003]],
        };
    });
}

/* Shared web-hosting fixture: an active web service bound to an aaPanel or ISPConfig instance (WebFeature*Test). */
const AAP = 'https://managed01.mgmt.test:8888';
const ISP = 'https://shared01.mgmt.test:8080';

/**
 * The real aaPanel adapter for the `aapanel-managed01` instance. It lives here and not in the contract test that first
 * wrote it: three files call it, and a helper in another test file exists only when that file happens to be loaded —
 * run on its own, a test that borrowed it failed with "Call to undefined function".
 */
function aaToolsAdapter(): AaPanelWebProvider
{
    $_ENV['AAPANEL_MANAGED01_API_KEY'] = 'aa-key-123';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'base_url' => AAP, 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'capabilities' => ['web'], 'region_code' => 'cz1']);
    $registry = app(ProviderRegistry::class);
    $registry->register('aapanel', AaPanelWebProvider::class);

    return $registry->forInstance($instance);
}

function featureWebService(Organization $org, string $executor): Service
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    if ($executor === 'aapanel') {
        $_ENV['AAPANEL_MANAGED01_API_KEY'] = 'aa-key-123';
        $instance = ProviderInstance::query()->firstOrCreate(['key' => 'aapanel-managed01'], ['provider' => 'aapanel', 'name' => 'aaPanel managed01', 'region_code' => 'cz1', 'base_url' => AAP, 'secret_ref' => 'env://AAPANEL_MANAGED01', 'state' => 'active', 'capabilities' => ['web.create' => true], 'options' => ['verify_tls' => false]]);
        $binding = ['remote_type' => 'site', 'remote_id' => '41', 'remote_node' => 'aapanel-managed01', 'meta' => ['name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']];
    } else {
        $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost';
        $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'secret';
        $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => ISP, 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true, 'mail.create' => true], 'options' => ['verify_tls' => false, 'server_id' => 1]]);
        $binding = ['remote_type' => 'web_domain', 'remote_id' => '7', 'remote_node' => '1', 'meta' => ['client_id' => 3, 'system_user' => 'web7', 'document_root' => '/var/www/clients/client3/web7']];
    }
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => $executor.'-web01'], ['region_code' => 'cz1', 'role' => 'web', 'state' => 'active', 'capacity' => ['sites' => 500], 'usage' => []]);
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Standard', 'hostname' => 'shop.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => $executor, 'family' => 'web', 'domain' => 'shop.cz', 'php_version' => '8.3'], 'entitlements' => ['sites' => 3, 'databases' => 2, 'ftp_accounts' => 2, 'cron_jobs' => 5, 'nvme_gb' => 50, 'mailboxes' => 10, 'ssh' => true], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [], 'health' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "feature-test:{$service->id}", 'adapter_version' => '1.0.0'] + $binding);

    return $service;
}

/* Shared mail fixture: an active mail domain on the lab ISPConfig instance (mail tools, mail spec). */
function featureMailService(Organization $org, string $domain = 'shop.cz'): Service
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $_ENV['ISPCONFIG_SHARED01_REMOTE_USER'] = 'onhost';
    $_ENV['ISPCONFIG_SHARED01_REMOTE_PASSWORD'] = 'secret';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'ispconfig-shared01'], ['provider' => 'ispconfig', 'name' => 'ISPConfig shared01', 'region_code' => 'cz1', 'base_url' => ISP, 'secret_ref' => 'env://ISPCONFIG_SHARED01', 'state' => 'active', 'capabilities' => ['web.create' => true, 'mail.create' => true], 'options' => ['verify_tls' => false, 'server_id' => 1]]);
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'ispconfig-mail01'], ['region_code' => 'cz1', 'role' => 'mail', 'state' => 'active', 'capacity' => ['mailboxes' => 5000], 'usage' => []]);
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'mail-hosting', 'family' => 'mail', 'name' => 'E-mail hosting', 'hostname' => $domain, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'ispconfig', 'family' => 'mail', 'domain' => $domain], 'entitlements' => ['mailboxes' => 10, 'aliases' => 50, 'quota_gb_per_mailbox' => 10], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "feature-test:{$service->id}", 'adapter_version' => '1.0.0', 'remote_type' => 'mail_domain', 'remote_id' => '5', 'remote_node' => '1', 'meta' => ['client_id' => 3, 'domain' => $domain]]);

    return $service;
}

/* Shared game-server fixture: an active game service bound to the lab Pterodactyl instance (GameTools*Test). */
const PTERO = 'https://games01.mgmt.test';

function featureGameService(Organization $org, array $entitlements = [], int $remoteId = 77, string $identifier = 'e4c1abcd'): Service
{
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    $_ENV['PTERODACTYL_GAMES01_APPLICATION_KEY'] = 'ptla_APPLICATIONKEY1234567890';
    $_ENV['PTERODACTYL_GAMES01_CLIENT_KEY'] = 'ptlc_CLIENTKEY1234567890';
    $instance = ProviderInstance::query()->firstOrCreate(['key' => 'pterodactyl-games01'], ['provider' => 'pterodactyl', 'name' => 'Game panel games01', 'region_code' => 'cz1', 'base_url' => PTERO, 'secret_ref' => 'env://PTERODACTYL_GAMES01', 'state' => 'active', 'capabilities' => ['game.create' => true, 'console' => true], 'options' => ['eggs' => ['minecraft-paper' => ['nest' => 1, 'egg' => 5]]]]);
    $node = Node::query()->firstOrCreate(['provider_instance_id' => $instance->id, 'name' => 'games01'], ['region_code' => 'cz1', 'role' => 'game', 'state' => 'active', 'capacity' => ['cpu_cores' => 32, 'ram_mb' => 131072, 'disk_gb' => 2000], 'usage' => [], 'remote_id' => '2']);
    $service = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'game', 'family' => 'game', 'name' => 'Herní server 8 GB', 'label' => 'mc-liga', 'hostname' => 'mc.liga.test', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $node->id,
        'desired_spec' => ['executor' => 'pterodactyl', 'family' => 'game', 'egg' => 'minecraft-paper'], 'entitlements' => array_merge(['ram_mb' => 8192, 'nvme_gb' => 60, 'backups' => 5, 'allocations' => 2, 'databases' => 2, 'subusers' => 3], $entitlements), 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['access' => ['address' => '89.187.160.10:25566', 'identifier' => 'e4c1abcd']], 'health' => [],
    ]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $instance->id, 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "feature-test:{$service->id}", 'adapter_version' => '1.0.0', 'remote_type' => 'server', 'remote_id' => (string) $remoteId, 'remote_node' => '2', 'meta' => ['uuid' => $identifier.'-uuid', 'identifier' => $identifier, 'user_id' => 9, 'allocation_id' => 11]]);

    return $service;
}

/** A statement the customer paid from the credit for one period of a service: `$from`/`$to` are days from the accounting day. */
function chargebackPaidStatement(Organization $org, Service $service, int $gross, int $tax, int $from, int $to, string $type = 'statement', bool $paid = true): Invoice
{
    $invoices = app(InvoiceService::class);
    $ctx = CommandContext::system('test')->withScope($org->id);
    $day = AccountingClock::now()->startOfDay();
    $draft = $invoices->draft($org, $type, 'CZK', [[
        'sku' => $service->product_key.'-renewal', 'description' => "Prodloužení služby {$service->name}", 'qty' => 1, 'unit' => 'ks', 'unit_net' => $gross - $tax, 'discount' => 0, 'net' => $gross - $tax,
        'tax_rate' => '21', 'tax_category' => 'S', 'tax' => $tax, 'total' => $gross, 'period_from' => $day->addDays($from)->toDateString(), 'period_to' => $day->addDays($to)->toDateString(), 'service_id' => $service->id,
    ]], $ctx, null, $type === 'invoice' ? ['postpaid' => true, 'payment_method' => 'invoice'] : ['payment_method' => 'wallet']);
    $document = $invoices->issue($draft, $ctx, dueDays: $type === 'invoice' ? 14 : 0);

    return $paid ? $invoices->markPaid($document, $document->total(), 'wallet', $ctx, postLedger: false) : $document;
}

/** A WAPI double for one customer account: domains, hosted zones with rows, credit; records every login used. */
function connectionWapiFake(array &$state): void
{
    Http::fake(['api.wedos.com/wapi/json' => function (Request $request) use (&$state) {
        $payload = json_decode((string) $request['request'], true)['request'];
        $command = $payload['command'];
        $data = (array) ($payload['data'] ?? []);
        $state['commands'][] = $command;
        $state['logins'][] = (string) ($payload['user'] ?? '');
        $rows = fn (string $zone) => array_values(array_map(fn (array $r) => ['ID' => (string) $r['ID'], 'name' => (string) $r['name'], 'ttl' => (int) ($r['ttl'] ?? 1800), 'rdtype' => (string) $r['rdtype'], 'rdata' => (string) $r['rdata']], $state['zones'][$zone] ?? []));
        $overrides = match ($command) {
            'ping', 'dns-domain-commit', 'dns-row-update' => [],
            'domains-list' => ['data' => ['domain' => array_values($state['domains'])]],
            'domain-info' => (($d = collect($state['domains'])->firstWhere('name', $data['name'])) === null) ? ['code' => 3222, 'result' => 'Domain not found'] : ['data' => ['domain' => $d + ['dns' => [['name' => 'ns.wedos.cz']]]]],
            'credit-info' => ['data' => ['amount' => $state['credit'], 'currency' => 'CZK']],
            'dns-domain-info' => isset($state['zones'][$data['name']]) ? ['data' => ['domain' => ['name' => $data['name'], 'status' => 'active']]] : ['code' => 2303, 'result' => 'Object not found'],
            'dns-rows-list' => ['data' => ['row' => $rows((string) $data['domain'])]],
            'dns-row-add' => (function () use (&$state, $data) {
                $state['zones'][$data['domain']][] = ['ID' => (string) (100 + count($state['zones'][$data['domain']] ?? [])), 'name' => (string) $data['name'], 'ttl' => (int) $data['ttl'], 'rdtype' => strtoupper((string) $data['type']), 'rdata' => (string) $data['rdata']];

                return [];
            })(),
            'dns-row-delete' => (function () use (&$state, $data) {
                $state['zones'][$data['domain']] = array_values(array_filter($state['zones'][$data['domain']] ?? [], fn ($r) => (string) $r['ID'] !== (string) $data['row_id']));

                return [];
            })(),
            default => ['code' => 2100, 'result' => "unexpected {$command}"],
        };

        return Http::response(['response' => array_merge(['code' => 1000, 'result' => 'OK', 'timestamp' => time(), 'clTRID' => $payload['clTRID'], 'svTRID' => 'sv-'.uniqid(), 'command' => $command, 'data' => []], $overrides)]);
    }]);
}

/**
 * Four eyes in a test: somebody else (a platform owner by default) approves the request the refusal opened.
 * Returns the approval id the requester repeats the request with (`approval_ids`).
 */
function secondPersonApproves(string $approvalId, ?User $decider = null): string
{
    $decider ??= User::factory()->staff()->create();
    if (! PolicyBinding::query()->where('principal_id', $decider->id)->exists()) {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $decider->id, 'role_key' => 'platform_owner', 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    }
    $approval = Approval::query()->findOrFail($approvalId);
    app(ApprovalService::class)->decide($approval, $decider, 'approved', null, new CommandContext('user', $decider->id, null, null, '127.0.0.1', 'pest', 'second-person', stepUpMethod: 'totp'));

    return $approvalId;
}

/**
 * The subject a staff VAT override confirms, as the requester sees it now (stack polish): the normalised number and the
 * organization name. OverrideVatStatusHandler refuses a payload whose subject is not the organization's any more.
 *
 * @return array{vat_number:string, organization_name:string}
 */
function vatOverrideSubject(Organization $organization): array
{
    $current = Organization::query()->findOrFail($organization->id);

    return ['vat_number' => (string) (VatStanding::subject($current)->value ?? ''), 'organization_name' => (string) $current->name];
}
