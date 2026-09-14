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
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;
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
        $credentials = array_filter((array) ($input['credentials'] ?? []), fn ($v) => is_string($v) && $v !== '');
        $secretRef = isset($input['secret_ref']) && $input['secret_ref'] !== '' ? SecretRef::parse((string) $input['secret_ref']) : null;
        if ($secretRef === null && ($credentials !== [] || $existing === null)) {
            $secretRef = SecretRef::parse("db://provider_instances/{$key}");
        }

        return DB::transaction(function () use ($input, $key, $provider, $baseUrl, $existing, $credentials, $secretRef, $context) {
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
                'state' => $input['state'] ?? $existing?->state ?? 'active',
                'adapter_version' => $existing?->adapter_version ?? '1.0.0',
            ], fn ($v) => $v !== null));
            $this->providers->forget($instance);
            $this->audit->record($context, $existing ? 'provider.instance.update' : 'provider.instance.create', 'succeeded', ['key' => $key, 'provider' => $provider, 'base_url' => $instance->base_url, 'secret_ref' => (string) $ref, 'credential_keys' => array_keys($credentials)], 'provider_instance', $instance->id);

            return $instance;
        });
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
        $instance->forceFill(['state' => $state, 'maintenance_until' => $state === 'maintenance' ? $maintenanceUntil : null])->save();
        $this->providers->forget($instance);
        $this->audit->record($context, 'provider.instance.state', 'succeeded', ['key' => $instance->key, 'state' => $state, 'reason' => $reason], 'provider_instance', $instance->id);

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
        if (! $adapter instanceof ProxmoxComputeProvider) {
            throw new DomainError('instance_discovery_unsupported', 'Node discovery is available for Proxmox, ISPConfig and game-panel instances; register other nodes manually.', 422);
        }
        $seen = [];
        foreach ($adapter->clusterNodes() as $remote) {
            $node = Node::query()->firstOrNew(['provider_instance_id' => $instance->id, 'name' => $remote['node']]);
            $capacity = (array) ($node->capacity ?? []);
            $node->forceFill([
                'region_code' => $node->region_code ?? $instance->region_code, 'role' => $node->role ?? 'compute',
                'state' => $remote['status'] === 'online' ? ($node->exists ? $node->state : 'active') : 'unreachable',
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
                'state' => $node->exists ? $node->state : 'active',
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
                'state' => $remote['maintenance'] ? 'maintenance' : ($node->exists && $node->state !== 'maintenance' ? $node->state : 'active'),
                'capacity' => array_merge($capacity, ['cpu_cores' => (int) ($capacity['cpu_cores'] ?? 0), 'ram_mb' => (int) $remote['memory'], 'disk_gb' => (int) round($remote['disk'] / 1024)]), // the panel's limits win over what was stored (audit §5q follow-up: limits change from the console)
                'usage' => ['cpu_pct' => (int) data_get($node->usage, 'cpu_pct', 0), 'ram_used_mb' => (int) $remote['allocated_memory'], 'disk_used_gb' => (int) round($remote['allocated_disk'] / 1024), 'io_wait_pct' => 0],
                'remote_id' => (string) $remote['id'], 'last_seen_at' => now(), 'failure_domain' => $node->failure_domain ?? (string) $remote['name'], 'tags' => array_merge((array) ($node->tags ?? []), ['maintenance' => (bool) $remote['maintenance']]),
            ])->save();
            $seen[] = $node->name;
        }
        $this->audit->record($context, 'provider.instance.discover', 'succeeded', ['key' => $instance->key, 'nodes' => $seen], 'provider_instance', $instance->id);

        return ['instance' => $instance->key, 'nodes' => $seen];
    }

    /** Manual node registration for executors without discovery (ISPConfig servers, aaPanel hosts, Wings nodes). */
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
        $node = Node::query()->updateOrCreate(['provider_instance_id' => $instance->id, 'name' => $name], array_filter([
            'region_code' => $input['region_code'] ?? $instance->region_code, 'role' => $input['role'], 'state' => $input['state'] ?? 'active',
            'capacity' => (array) ($input['capacity'] ?? []), 'usage' => $input['usage'] ?? ['cpu_pct' => 0, 'ram_used_mb' => 0, 'disk_used_gb' => 0, 'io_wait_pct' => 0],
            'failure_domain' => $input['failure_domain'] ?? null, 'remote_id' => $input['remote_id'] ?? null, 'tags' => (array) ($input['tags'] ?? []), 'last_seen_at' => now(),
        ], fn ($v) => $v !== null));
        $this->audit->record($context, 'provider.node.upsert', 'succeeded', ['key' => $instance->key, 'node' => $node->name, 'role' => $node->role], 'node', $node->id);

        return $node;
    }
}
