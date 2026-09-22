<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\IspConfig\IspConfigWebProvider;
use Onhost\Providers\Proxmox\ProxmoxComputeProvider;

/**
 * Registering and connecting provider instances from the admin console (blueprint §30 onboarding):
 * base URL + options + capabilities live on `provider_instances`, credentials go to the secret store
 * (`db://provider_instances/<key>` encrypted at rest, or an existing `env://` / `bao://` reference),
 * a connection test runs the adapter's health check, and Proxmox clusters can import their nodes.
 */
final class ProviderInstanceService
{
    /** The state reason of an instance whose panel address moved: only a successful probe lifts this lock (H311). */
    public const ADDRESS_CHANGE_REASON = 'panel address changed, awaiting a probe of';

    /** Credential keys each adapter reads (see docs/provider-adapters/*). `required` must be present to activate. */
    public const CREDENTIALS = [
        'proxmox' => ['required' => ['token_id', 'token_secret'], 'optional' => [], 'hint' => 'PVE API token: user@realm!tokenid + secret (privileges VM.*, Datastore.AllocateSpace, Sys.Audit)'],
        'pbs' => ['required' => ['token_id', 'token_secret'], 'optional' => [], 'hint' => 'PBS API token with Datastore.Backup/Datastore.Verify on the datastore'],
        'ispconfig' => ['required' => ['remote_user', 'remote_password'], 'optional' => [], 'hint' => 'Remote user (System → Remote users) with client, sites, mail and dns functions'],
        'aapanel' => ['required' => ['api_key'], 'optional' => [], 'hint' => 'Settings → API interface: key + allow-list the control plane IP'],
        'pterodactyl' => ['required' => ['application_key'], 'optional' => ['client_key'], 'hint' => 'Application API key (users/servers/nodes/allocations) and a client key for power/console'],
        'powerdns' => ['required' => ['api_key'], 'optional' => [], 'hint' => 'pdns.conf api-key with the HTTP API enabled'],
        'wedos' => ['required' => ['login', 'wapi_password'], 'optional' => [], 'hint' => 'WAPI login (customer e-mail) and WAPI password; enable JSON API and allow the control plane IP in the WEDOS admin'],
        'wedos_zone' => ['required' => ['login', 'wapi_password'], 'optional' => [], 'hint' => 'Same WAPI account as the registrar instance'],
        'subreg' => ['required' => ['login', 'password'], 'optional' => [], 'hint' => 'Subreg.CZ API user and password (Nastavení účtu → API); allow the control plane IP; only domain functions are used. Set options.demo=true for a demoreg.net sandbox account'],
        'kubernetes' => ['required' => ['token'], 'optional' => ['ca_cert'], 'hint' => 'Service-account token with namespace-scoped RBAC (see infra/rke2/policies)'],
    ];

    /** Default capability map per provider (what the scheduler may place on the instance). */
    public const CAPABILITIES = [
        'proxmox' => ['vm.create' => true, 'compute' => true, 'console' => true, 'vm.backup' => true],
        'pbs' => ['backup' => true],
        'ispconfig' => ['web.create' => true, 'mail.create' => true],
        'aapanel' => ['web.create' => true],
        'pterodactyl' => ['game.create' => true, 'console' => true],
        'powerdns' => ['dns' => true],
        'wedos' => ['registrar' => true],
        'wedos_zone' => ['dns' => true],
        'subreg' => ['registrar' => true],
        'kubernetes' => ['apps.create' => true],
    ];

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly SecretStore $secrets,
        private readonly IntegrationHealthProbe $probe,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly ProviderHttpClient $http,
    ) {}

    /**
     * Create or update an instance. Credentials (when given) are stored under `db://provider_instances/<key>`
     * unless an explicit `secret_ref` (`env://…`, `bao://…`) is supplied; blank credential fields keep stored values.
     *
     * @param  array{key:string,provider:string,name?:string,region_code?:string,base_url:string,options?:array,capabilities?:array,secret_ref?:string,credentials?:array,state?:string,quotas?:array,rate_limits?:array}  $input
     */
    public function upsert(array $input, CommandContext $context): ProviderInstance
    {
        $key = strtolower(trim((string) ($input['key'] ?? '')));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,58}$/', $key)) {
            throw new DomainError('instance_key_invalid', 'Key must be lowercase letters, digits and dashes (e.g. proxmox-cz1).', 422, ['field' => 'key']);
        }
        $provider = (string) ($input['provider'] ?? '');
        if (! isset(self::CREDENTIALS[$provider])) {
            throw new DomainError('instance_provider_unknown', 'Unknown provider: '.implode(', ', array_keys(self::CREDENTIALS)).' are supported.', 422, ['field' => 'provider']);
        }
        $baseUrl = trim((string) ($input['base_url'] ?? ''));
        if (! preg_match('~^https?://[^\s/]+~i', $baseUrl)) {
            throw new DomainError('instance_base_url_invalid', 'base_url must be an absolute http(s) URL.', 422, ['field' => 'base_url']);
        }
        if (isset($input['region_code']) && $input['region_code'] !== null && ! Region::query()->where('code', $input['region_code'])->exists()) {
            throw new DomainError('instance_region_unknown', 'Unknown region code.', 422, ['field' => 'region_code']);
        }
        $existing = ProviderInstance::query()->where('key', $key)->first();
        if ($existing !== null && $existing->provider !== $provider) {
            throw new DomainError('instance_provider_immutable', 'The provider of an existing instance cannot change; create a new instance.', 409, ['field' => 'provider']);
        }
        // a new host is where the stored credentials would be sent next (H311): it has to be acknowledged, and automation
        // stays off the instance until a probe run by staff confirms the panel answers there — the change never activates itself
        $hostChanged = $existing !== null && strtolower((string) parse_url($existing->base_url, PHP_URL_HOST)) !== strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($hostChanged && ! (bool) ($input['confirm_host_change'] ?? false)) {
            throw new DomainError('instance_host_change_unconfirmed', 'The panel address points at another host; confirm the change (confirm_host_change) — the instance is then locked until a probe confirms the new address.', 409, ['field' => 'base_url', 'current_host' => parse_url($existing->base_url, PHP_URL_HOST), 'new_host' => parse_url($baseUrl, PHP_URL_HOST)]);
        }
        $credentials = array_filter((array) ($input['credentials'] ?? []), fn ($v) => is_string($v) && $v !== '');
        $secretRef = isset($input['secret_ref']) && $input['secret_ref'] !== '' ? SecretRef::parse((string) $input['secret_ref']) : null;
        if ($secretRef === null && ($credentials !== [] || $existing === null)) {
            $secretRef = SecretRef::parse("db://provider_instances/{$key}");
        }

        // a stored access belongs to one instance (H314): the console's own secret of another instance can never be pointed at
        // this one, whatever the two are called — its credentials would be sent to this panel's host
        if ($secretRef !== null && $secretRef->scheme === 'db' && str_starts_with($secretRef->path, 'provider_instances/') && $secretRef->path !== "provider_instances/{$key}") {
            throw new DomainError('instance_secret_ref_foreign', 'This secret belongs to another instance; every instance keeps its own access.', 422, ['field' => 'secret_ref']);
        }
        if ($existing !== null && $credentials !== [] && ! (bool) ($input['force_credentials'] ?? false)) {
            $this->verifyRotation($existing, $credentials, $baseUrl, $context);
        }

        return DB::transaction(function () use ($input, $key, $provider, $baseUrl, $existing, $credentials, $secretRef, $hostChanged, $context) {
            $ref = $secretRef ?? $existing?->secretRef();
            if ($credentials !== []) {
                if ($ref === null || $ref->scheme !== 'db') {
                    throw new DomainError('instance_credentials_readonly', "Credentials for {$ref} are managed outside the console (environment / OpenBao).", 422, ['field' => 'credentials']);
                }
                if ($this->secrets instanceof DbSecretStore) {
                    $this->secrets->merge($ref, $credentials, $context->actorId);
                } else {
                    $this->secrets->write($ref, $credentials);
                }
            }
            $instance = ProviderInstance::query()->updateOrCreate(['key' => $key], array_filter([
                'provider' => $provider,
                'name' => $input['name'] ?? $existing?->name ?? $key,
                'region_code' => array_key_exists('region_code', $input) ? $input['region_code'] : $existing?->region_code,
                'base_url' => rtrim($baseUrl, '/'),
                'secret_ref' => (string) $ref,
                'options' => array_key_exists('options', $input) ? (array) $input['options'] : ($existing?->options ?? []),
                'capabilities' => array_key_exists('capabilities', $input) ? (array) $input['capabilities'] : ($existing?->capabilities ?? self::CAPABILITIES[$provider]),
                'quotas' => array_key_exists('quotas', $input) ? (array) $input['quotas'] : ($existing?->quotas ?? null),
                'rate_limits' => array_key_exists('rate_limits', $input) ? (array) $input['rate_limits'] : ($existing?->rate_limits ?? null),
                'state' => $hostChanged ? 'maintenance' : ($input['state'] ?? $existing?->state ?? 'active'),
                'adapter_version' => $existing?->adapter_version ?? '1.0.0',
            ], fn ($v) => $v !== null));
            if ($hostChanged) {
                $instance->forceFill(['maintenance_until' => null, 'state_reason' => self::ADDRESS_CHANGE_REASON.' '.(string) parse_url($baseUrl, PHP_URL_HOST)])->save();
            }
            $this->providers->forget($instance);
            $this->audit->record($context, $existing ? 'provider.instance.update' : 'provider.instance.create', 'succeeded', ['key' => $key, 'provider' => $provider, 'base_url' => $instance->base_url, 'secret_ref' => (string) $ref, 'credentials_forced' => $credentials !== [] && (bool) ($input['force_credentials'] ?? false), 'credential_keys' => array_keys($credentials),
                'host_changed' => $hostChanged ? ['from' => parse_url((string) $existing?->base_url, PHP_URL_HOST), 'to' => parse_url($baseUrl, PHP_URL_HOST)] : null], 'provider_instance', $instance->id);

            return $instance;
        });
    }

    /**
     * A new access is tried before it replaces the one that works (Brain card H314). The candidate is never stored: a
     * trial adapter is built in memory on the stored values merged with the new ones, against the address the instance
     * is about to have, and asked for its health in the diagnostic lane. Only an access the panel accepts goes on to be
     * written; otherwise nothing changes and the working access stays. `force_credentials` skips this — for an access
     * that is compromised while the panel cannot be reached — and says so in the audit record.
     *
     * @param  array<string,string>  $credentials
     */
    private function verifyRotation(ProviderInstance $existing, array $credentials, string $baseUrl, CommandContext $context): void
    {
        $ref = $existing->secretRef();
        if ($ref->scheme !== 'db' || ! $this->secrets->exists($ref)) {
            return; // nothing stored in the console yet: this is the first access, there is no working one to protect
        }
        $current = $this->secrets->read($ref);
        $changed = array_keys(array_filter($credentials, fn (string $value, string $name) => ($current[$name] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return;
        }
        $candidate = clone $existing;
        $candidate->base_url = rtrim($baseUrl, '/');
        $error = null;
        try {
            $adapter = $this->providers->trial($candidate, array_merge($current, $credentials));
            $health = $this->http->diagnostic(fn () => $adapter->health());
            $error = $health->healthy ? null : ($health->error ?? 'the panel did not confirm the new access');
            // the game panel has two accesses with separate rights: a working application key says nothing about the client key
            if ($error === null && in_array('client_key', $changed, true) && method_exists($adapter, 'clientApiStatus') && $adapter->clientApiStatus() !== 'ok') {
                $error = 'the panel refused the new client key';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        if ($error === null) {
            return;
        }
        // from the terminal this row is the record of the refusal; inside the command bus it rolls back with the transaction
        // and the bus's own `failed` row (error slug, message, credentials stripped) stands instead
        $this->audit->record($context, 'provider.instance.credentials.rotate', 'failed', ['key' => $existing->key, 'credential_keys' => $changed, 'error' => mb_substr($error, 0, 300)], 'provider_instance', $existing->id);

        throw new DomainError('instance_credentials_unverified', 'The panel did not accept the new access, so the stored one stays active: '.mb_substr($error, 0, 300), 422, ['field' => 'credentials', 'credential_keys' => $changed]);
    }

    /** An audited change of one option group of an instance (template mapping, allocations) made outside `upsert`. */
    public function auditOptions(ProviderInstance $instance, string $group, mixed $value, CommandContext $context): void
    {
        $this->audit->record($context, 'provider.instance.options', 'succeeded', ['key' => $instance->key, 'group' => $group, 'value' => $value], 'provider_instance', $instance->id);
    }

    /** Which credential keys are stored (never values) and which required ones are missing. */
    public function credentialStatus(ProviderInstance $instance): array
    {
        $schema = self::CREDENTIALS[$instance->provider] ?? ['required' => [], 'optional' => [], 'hint' => ''];
        $present = [];
        try {
            $ref = $instance->secretRef();
            $present = $this->secrets instanceof DbSecretStore ? $this->secrets->keys($ref) : ($this->secrets->exists($ref) ? array_keys($this->secrets->read($ref)) : []);
        } catch (\Throwable) {
            $present = [];
        }

        return ['secret_ref' => $instance->secret_ref, 'schema' => $schema, 'present' => array_values($present), 'missing' => array_values(array_diff($schema['required'], $present)), 'managed_in_console' => str_starts_with((string) $instance->secret_ref, 'db://')];
    }

    /** Connection test: adapter health check, health record updated, result returned (errors redacted by the adapter). */
    public function probe(ProviderInstance $instance, CommandContext $context): array
    {
        $result = $this->probe->probeInstance($instance);
        $this->audit->record($context, 'provider.instance.probe', $result['up'] ? 'succeeded' : 'failed', ['key' => $instance->key, 'error' => $result['error'] ?? null, 'latency_ms' => $result['latency_ms'] ?? null], 'provider_instance', $instance->id);

        return $result + ['credentials' => $this->credentialStatus($instance->fresh())];
    }

    public function setState(ProviderInstance $instance, string $state, CommandContext $context, ?string $reason = null, ?\DateTimeInterface $maintenanceUntil = null): ProviderInstance
    {
        if (! in_array($state, ['active', 'draining', 'maintenance', 'disabled'], true)) {
            throw new DomainError('instance_state_invalid', 'State must be active, draining, maintenance or disabled.', 422, ['field' => 'state']);
        }
        $instance->forceFill(['state' => $state, 'maintenance_until' => $state === 'maintenance' ? $maintenanceUntil : null, 'state_reason' => $state === 'active' ? null : ($reason !== null ? mb_substr($reason, 0, 250) : null)])->save();
        $this->providers->forget($instance);
        $this->audit->record($context, 'provider.instance.state', 'succeeded', ['key' => $instance->key, 'state' => $state, 'reason' => $reason, 'maintenance_until' => $maintenanceUntil?->format(DATE_ATOM)], 'provider_instance', $instance->id);

        return $instance;
    }

    /** Import cluster nodes from a Proxmox instance into the scheduler (`nodes`), keeping manual capacity overrides. */
    public function discoverNodes(ProviderInstance $instance, CommandContext $context): array
    {
        $adapter = $this->providers->forInstance($instance);
        if ($instance->region_code === null) {
            throw new DomainError('instance_region_required', 'Set the region of the instance before importing its nodes (nodes are scheduled per region).', 422, ['field' => 'region_code']);
        }
        if ($adapter instanceof IspConfigWebProvider) {
            return $this->discoverIspConfig($instance, $adapter, $context);
        }
        if ($adapter instanceof GameProvider) {
            return $this->discoverGameNodes($instance, $adapter, $context);
        }
        if ($adapter instanceof AaPanelWebProvider) {
            return $this->discoverAaPanel($instance, $adapter, $context);
        }
        if (! $adapter instanceof ProxmoxComputeProvider) {
            throw new DomainError('instance_discovery_unsupported', 'Node discovery is available for Proxmox, ISPConfig, aaPanel and game-panel instances; register other nodes manually.', 422);
        }
        $seen = [];
        foreach ($adapter->clusterNodes() as $remote) {
            $node = Node::query()->firstOrNew(['provider_instance_id' => $instance->id, 'name' => $remote['node']]);
            $capacity = (array) ($node->capacity ?? []);
            $node->forceFill([
                'region_code' => $node->region_code ?? $instance->region_code, 'role' => $node->role ?? 'compute',
                'state' => $remote['status'] === 'online' ? ($node->exists ? $node->state : Node::QUALIFYING) : 'unreachable',
                'capacity' => $capacity + ['cpu_cores' => $remote['maxcpu'] ?? 0, 'ram_mb' => (int) round(($remote['maxmem'] ?? 0) / 1048576), 'disk_gb' => (int) round(($remote['maxdisk'] ?? 0) / 1073741824)],
                'usage' => ['cpu_pct' => (int) round(($remote['cpu'] ?? 0) * 100), 'ram_used_mb' => (int) round(($remote['mem'] ?? 0) / 1048576), 'disk_used_gb' => (int) round(($remote['disk'] ?? 0) / 1073741824), 'io_wait_pct' => 0],
                'remote_id' => $remote['node'], 'last_seen_at' => now(), 'failure_domain' => $node->failure_domain ?? $remote['node'], 'tags' => $node->tags ?? [],
            ])->save();
            $seen[] = $node->name;
        }
        $this->audit->record($context, 'provider.instance.discover', 'succeeded', ['key' => $instance->key, 'nodes' => $seen], 'provider_instance', $instance->id);

        return ['instance' => $instance->key, 'nodes' => $seen];
    }

    /**
     * ISPConfig discovery: one node per ISPConfig server with the roles it runs (web/mail/dns/managed). A remote user
     * without the "Server functions" group cannot list servers, so the panel host is registered as the web server.
     * A single-server installation also gets option `server_id` filled in when it was not set explicitly.
     *
     * @return array{instance:string, nodes:list<string>}
     */
    private function discoverIspConfig(ProviderInstance $instance, IspConfigWebProvider $adapter, CommandContext $context): array
    {
        $servers = $adapter->discoverServers();
        $seen = [];
        foreach ($servers as $remote) {
            $node = Node::query()->firstOrNew(['provider_instance_id' => $instance->id, 'name' => $remote['name']]);
            $node->forceFill([
                'region_code' => $node->region_code ?? $instance->region_code,
                'role' => $node->role ?? $remote['roles'][0],
                'state' => $node->exists ? $node->state : Node::QUALIFYING,
                'capacity' => (array) ($node->capacity ?? []) + $remote['capacity'],
                'usage' => $node->usage ?? [],
                'remote_id' => (string) $remote['remote_id'], 'last_seen_at' => now(),
                'failure_domain' => $node->failure_domain ?? $remote['name'],
                'tags' => array_merge((array) ($node->tags ?? []), ['ispconfig_roles' => $remote['roles']]),
            ])->save();
            $seen[] = $node->name;
        }
        if (count($servers) === 1 && (int) $instance->option('server_id', 0) <= 0) {
            $instance->forceFill(['options' => array_merge((array) $instance->options, ['server_id' => (int) $servers[0]['remote_id']])])->save();
            $this->providers->forget($instance);
        }
        $this->audit->record($context, 'provider.instance.discover', 'succeeded', ['key' => $instance->key, 'nodes' => $seen], 'provider_instance', $instance->id);

        return ['instance' => $instance->key, 'nodes' => $seen];
    }

    /**
     * aaPanel discovery (audit §5z): an aaPanel is one server, so it becomes one scheduler node with role `managed` (the
     * role the website workflow asks for on aaPanel) — its memory from the panel's system totals, reachable when the
     * panel answers. A node staff already registered keeps its role, state and tags.
     *
     * @return array{instance:string, nodes:list<string>}
     */
    private function discoverAaPanel(ProviderInstance $instance, AaPanelWebProvider $adapter, CommandContext $context): array
    {
        $health = $adapter->health();
        if (! $health->healthy) {
            throw new DomainError('instance_unreachable', 'The aaPanel did not answer: '.(string) $health->error, 422);
        }
        $name = (string) (parse_url((string) $instance->base_url, PHP_URL_HOST) ?: $instance->key);
        $node = Node::query()->where('provider_instance_id', $instance->id)->orderBy('created_at')->first() ?? new Node(['provider_instance_id' => $instance->id, 'name' => $name]);
        $capacity = (array) ($node->capacity ?? []);
        $node->forceFill([
            'region_code' => $node->region_code ?? $instance->region_code, 'role' => $node->role ?? 'managed',
            'state' => $node->exists ? $node->state : Node::QUALIFYING,
            'capacity' => array_merge($capacity, array_filter(['ram_mb' => (int) data_get($health->detail, 'mem_total', 0)])),
            'usage' => array_merge((array) ($node->usage ?? []), ['ram_used_mb' => (int) data_get($health->detail, 'mem_realused', 0)]),
            'remote_id' => $node->remote_id, 'last_seen_at' => now(), 'failure_domain' => $node->failure_domain ?? $name, 'tags' => (array) ($node->tags ?? []),
        ])->save();
        $this->audit->record($context, 'provider.instance.discover', 'succeeded', ['key' => $instance->key, 'nodes' => [$node->name]], 'provider_instance', $instance->id);

        return ['instance' => $instance->key, 'nodes' => [$node->name]];
    }

    /**
     * Game panel discovery: one scheduler node per panel node (Wings daemon) with its memory and disk, what is already
     * allocated to servers as usage, and maintenance mode as the node state. `remote_id` is the panel's node id — the
     * provisioning saga picks allocations by it.
     *
     * @return array{instance:string, nodes:list<string>}
     */
    private function discoverGameNodes(ProviderInstance $instance, GameProvider $adapter, CommandContext $context): array
    {
        $seen = [];
        foreach ($adapter->listNodes() as $remote) {
            $node = Node::query()->firstOrNew(['provider_instance_id' => $instance->id, 'name' => (string) $remote['name']]);
            $capacity = (array) ($node->capacity ?? []);
            $node->forceFill([
                'region_code' => $node->region_code ?? $instance->region_code, 'role' => 'game',
                'state' => $remote['maintenance'] ? 'maintenance' : ($node->exists && $node->state !== 'maintenance' ? $node->state : Node::QUALIFYING),
                'capacity' => array_merge($capacity, ['cpu_cores' => (int) ($capacity['cpu_cores'] ?? 0), 'ram_mb' => self::overallocated((int) $remote['memory'], (int) ($remote['memory_overallocate'] ?? 0)), 'disk_gb' => (int) round(self::overallocated((int) $remote['disk'], (int) ($remote['disk_overallocate'] ?? 0)) / 1024)]), // the panel's overallocation (%) is what it really accepts // the panel's limits win over what was stored (audit §5q follow-up: limits change from the console)
                'usage' => ['cpu_pct' => (int) data_get($node->usage, 'cpu_pct', 0), 'ram_used_mb' => (int) $remote['allocated_memory'], 'disk_used_gb' => (int) round($remote['allocated_disk'] / 1024), 'io_wait_pct' => 0],
                'remote_id' => (string) $remote['id'], 'last_seen_at' => now(), 'failure_domain' => $node->failure_domain ?? (string) $remote['name'], 'tags' => array_merge((array) ($node->tags ?? []), ['maintenance' => (bool) $remote['maintenance']]),
            ])->save();
            $seen[] = $node->name;
        }
        $this->audit->record($context, 'provider.instance.discover', 'succeeded', ['key' => $instance->key, 'nodes' => $seen], 'provider_instance', $instance->id);

        return ['instance' => $instance->key, 'nodes' => $seen];
    }

    /** Manual node registration for executors without discovery (ISPConfig servers, aaPanel hosts, Wings nodes). */
    /** A game-panel limit with its overallocation percentage (-1 = unlimited → the plain limit stays the reference). */
    public static function overallocated(int $limit, int $percent): int
    {
        return $percent > 0 ? (int) floor($limit * (1 + $percent / 100)) : $limit;
    }

    public function upsertNode(ProviderInstance $instance, array $input, CommandContext $context): Node
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new DomainError('node_name_required', 'Node name is required.', 422, ['field' => 'name']);
        }
        if (! in_array($input['role'] ?? '', ['compute', 'web', 'managed', 'game', 'mail', 'dns', 'apps', 'backup'], true)) {
            throw new DomainError('node_role_invalid', 'Role must be compute, web, managed, game, mail, dns, apps or backup.', 422, ['field' => 'role']);
        }
        if (($input['region_code'] ?? $instance->region_code) === null) {
            throw new DomainError('node_region_required', 'A node needs a region (set it on the node or on the instance).', 422, ['field' => 'region_code']);
        }
        // a node the platform has just learnt about is not a node anybody has looked at: it is listed and watched, and the
        // scheduler does not see it until it has been qualified (H471). A node already known keeps the state it has.
        $known = Node::query()->where('provider_instance_id', $instance->id)->where('name', $name)->first();
        $state = (string) ($input['state'] ?? '');
        if ($state === '') {
            $state = $known instanceof Node ? (string) $known->state : Node::QUALIFYING;
        }
        $node = Node::query()->updateOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], array_filter([
            'region_code' => $input['region_code'] ?? $instance->region_code, 'role' => $input['role'], 'state' => $state,
            'capacity' => (array) ($input['capacity'] ?? []), 'usage' => $input['usage'] ?? ['cpu_pct' => 0, 'ram_used_mb' => 0, 'disk_used_gb' => 0, 'io_wait_pct' => 0],
            'failure_domain' => $input['failure_domain'] ?? null, 'remote_id' => $input['remote_id'] ?? null, 'tags' => (array) ($input['tags'] ?? []), 'last_seen_at' => now(),
        ], fn ($v) => $v !== null));
        $this->audit->record($context, 'provider.node.upsert', 'succeeded', ['key' => $instance->key, 'node' => $node->name, 'role' => $node->role], 'node', $node->id);

        return $node;
    }
}
