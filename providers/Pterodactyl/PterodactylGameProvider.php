<?php

declare(strict_types=1);

namespace Onhost\Providers\Pterodactyl;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Platform\ProviderHttp\ProviderRequest;
use Onhost\Platform\ProviderHttp\ProviderResponse;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\GameProvider;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\Usage;

/**
 * Pterodactyl 1.11 Panel: Application API (`ptla_`, control plane only) for
 * servers/users/nodes/allocations and Client API (`ptlc_`, root-admin user of the
 * panel) for power, console tokens, backups, schedules. JSON:API envelopes
 * `{object, attributes}`; errors in `errors[]`. Creation is two-phase: 201 then
 * `container.installed = 1` (blueprint §14, docs-provider-apis §4).
 */
final class PterodactylGameProvider implements GameProvider, GameToolsProvider
{
    public function __construct(
        private readonly ProviderInstance $instance,
        private readonly array $credentials,
        private readonly ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->http->configureBucket($instance->key, (int) ($instance->rate_limits['per_minute'] ?? 240), 60, 0.15);
    }

    public static function providerKey(): string
    {
        return 'pterodactyl';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['1.11.x'];
    }

    public function capabilities(): array
    {
        return ['game.create' => true, 'game.power' => true, 'game.console' => true, 'game.backup' => true, 'game.restore' => true, 'game.schedule' => true, 'game.subusers' => true, 'game.files' => true, 'game.databases' => true, 'game.startup' => true, 'game.allocations' => true, 'game.privileged_eggs' => false, 'game.host_network' => false];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $nodes = $this->listNodes();
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $maintenance = count(array_filter($nodes, fn ($n) => $n['maintenance']));

            return new ProviderHealth(count($nodes) > 0 && $maintenance < count($nodes), null, $ms, ['nodes' => count($nodes), 'maintenance' => $maintenance]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        return $this->instance->vendor_version;
    }

    public function listNodes(): array
    {
        $out = [];
        foreach ($this->pages('/api/application/nodes', 'app', 'nodes.list') as $node) {
            $a = $node['attributes'];
            $out[] = ['id' => (int) $a['id'], 'name' => (string) $a['name'], 'memory' => (int) $a['memory'], 'disk' => (int) $a['disk'], 'allocated_memory' => (int) ($a['allocated_resources']['memory'] ?? 0), 'allocated_disk' => (int) ($a['allocated_resources']['disk'] ?? 0), 'maintenance' => (bool) ($a['maintenance_mode'] ?? false), 'memory_overallocate' => (int) ($a['memory_overallocate'] ?? 0), 'disk_overallocate' => (int) ($a['disk_overallocate'] ?? 0)];
        }

        return $out;
    }

    public function freeAllocations(int $nodeId, ?int $port = null): array
    {
        $out = [];
        foreach ($this->pages("/api/application/nodes/{$nodeId}/allocations", 'app', 'allocations.list') as $alloc) {
            $a = $alloc['attributes'];
            if (! empty($a['assigned'])) {
                continue;
            }
            if ($port !== null && (int) $a['port'] !== $port) {
                continue;
            }
            $out[] = ['id' => (int) $a['id'], 'ip' => (string) $a['ip'], 'port' => (int) $a['port'], 'alias' => $a['alias'] ?? null];
        }

        return $out;
    }

    public function ensureUser(string $email, string $displayName, string $externalId): array
    {
        $found = $this->request('GET', '/api/application/users', 'app', 'users.find', [], ['filter[email]' => $email]);
        $existing = collect((array) ($found['data'] ?? []))->first();
        if (is_array($existing)) {
            return ['remote_id' => (string) $existing['attributes']['id'], 'created' => false];
        }
        [$first, $last] = array_pad(explode(' ', trim($displayName), 2), 2, 'Customer');
        $created = $this->request('POST', '/api/application/users', 'app', 'users.create', [
            'email' => $email, 'username' => Str::slug(Str::before($email, '@').'-'.substr(md5($externalId), 0, 6), '_'), 'first_name' => $first ?: 'ONhost', 'last_name' => $last ?: 'Customer',
            'external_id' => $externalId, 'root_admin' => false, 'language' => 'en',
        ]);

        return ['remote_id' => (string) $created['attributes']['id'], 'created' => true];
    }

    public function eggDefinition(int $nestId, int $eggId): array
    {
        $egg = $this->request('GET', "/api/application/nests/{$nestId}/eggs/{$eggId}", 'app', 'egg.get', [], ['include' => 'variables']);
        $a = $egg['attributes'];
        $variables = [];
        foreach ((array) ($a['relationships']['variables']['data'] ?? []) as $v) {
            $variables[$v['attributes']['env_variable']] = ['default' => $v['attributes']['default_value'], 'rules' => $v['attributes']['rules'], 'user_editable' => (bool) $v['attributes']['user_editable']];
        }

        return ['id' => (int) $a['id'], 'name' => $a['name'], 'docker_image' => $a['docker_image'], 'docker_images' => (array) ($a['docker_images'] ?? []), 'startup' => $a['startup'], 'variables' => $variables, 'privileged' => (bool) ($a['config']['startup']['privileged'] ?? false)];
    }

    public function provision(ResourceSpec $spec): ProviderResult
    {
        $existing = $this->findByExternalId($spec->idempotencyKey);
        if ($existing !== null) {
            return ProviderResult::completed($this->refFrom($existing, $spec->serviceId), $existing, alreadyExisted: true);
        }
        $nest = (int) $spec->get('nest_id');
        $eggId = (int) $spec->get('egg_id');
        $egg = $this->eggDefinition($nest, $eggId);
        if ($egg['privileged']) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, "Egg {$egg['name']} requires a privileged container and is not allowed on shared game nodes (§14)");
        }
        $environment = [];
        foreach ($egg['variables'] as $name => $def) {
            $environment[$name] = $spec->get("environment.{$name}", $def['default']);
        }
        $ent = (array) $spec->get('entitlements', []);
        $limits = (array) $spec->get('limits', []);
        $payload = [
            'external_id' => $spec->idempotencyKey,
            'name' => mb_substr((string) $spec->get('name', $spec->serviceId), 0, 191),
            'description' => "ONhost service {$spec->serviceId}",
            'user' => (int) $spec->get('ptero_user_id'),
            'egg' => $eggId,
            'docker_image' => (string) $spec->get('docker_image', $egg['docker_image']),
            'startup' => (string) $spec->get('startup', $egg['startup']),
            'environment' => $environment,
            'limits' => ['memory' => (int) ($ent['ram_mb'] ?? 4096), 'swap' => 0, 'disk' => (int) (($ent['nvme_gb'] ?? 40) * 1024), 'io' => 500, 'cpu' => (int) ($limits['cpu_pct'] ?? 200), 'threads' => null, 'oom_disabled' => false],
            'feature_limits' => ['databases' => (int) ($ent['databases'] ?? 1), 'allocations' => (int) ($ent['allocations'] ?? 1), 'backups' => (int) ($ent['backups'] ?? 3)],
            'allocation' => ['default' => (int) $spec->get('allocation_id')],
            'start_on_completion' => true,
        ];
        $created = $this->request('POST', '/api/application/servers', 'app', 'servers.create', $payload, critical: true);
        $a = $created['attributes'];
        $ref = new ResourceRef('server', (string) $a['id'], (string) ($a['node'] ?? ''), ['uuid' => $a['uuid'], 'identifier' => $a['identifier'], 'user_id' => $a['user'], 'allocation_id' => $a['allocation']], $spec->serviceId);

        return ProviderResult::accepted(new AsyncHandle('ptero_install', (string) $a['id'], (string) ($a['node'] ?? ''), ['identifier' => $a['identifier']], 10, 1800), $ref, ['server_id' => (int) $a['id'], 'uuid' => $a['uuid'], 'identifier' => $a['identifier']]);
    }

    public function getActualState(ResourceRef $ref): ActualState
    {
        try {
            $server = $this->request('GET', "/api/application/servers/{$ref->remoteId}", 'app', 'servers.get');
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return ActualState::missing();
            }
            throw $e;
        }
        $a = $server['attributes'];
        $status = ($a['suspended'] ?? false) ? 'suspended' : (($a['container']['installed'] ?? 0) === 1 || ($a['status'] ?? null) === null ? 'installed' : (string) $a['status']);
        if ($status === 'installed') { // the live power state comes from the daemon through the client API: power actions verify against running | starting | stopping | stopped
            try {
                $status = match ((string) ($this->status($ref)['state'] ?? '')) {
                    'running' => 'running', 'starting' => 'starting', 'stopping' => 'stopping', 'offline' => 'stopped', default => $status,
                };
            } catch (\Throwable) {
                // no client key or the daemon down: the application state stands
            }
        }

        return new ActualState(true, [
            'name' => $a['name'], 'ram_mb' => (int) $a['limits']['memory'], 'disk_mb' => (int) $a['limits']['disk'], 'cpu_pct' => (int) $a['limits']['cpu'],
            'backups' => (int) $a['feature_limits']['backups'], 'allocations' => (int) $a['feature_limits']['allocations'], 'databases' => (int) $a['feature_limits']['databases'],
            'suspended' => (bool) $a['suspended'], 'installed' => (int) ($a['container']['installed'] ?? 0), 'egg' => (int) $a['egg'], 'node' => (int) $a['node'], 'identifier' => $a['identifier'],
        ], $status, now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }
        $ent = (array) $spec->get('entitlements', []);
        $drifts = [];
        foreach ([['ram_mb', 'ram_mb', 'AUTO_REPAIRABLE'], ['backups', 'backups', 'AUTO_REPAIRABLE'], ['allocations', 'allocations', 'AUTO_REPAIRABLE']] as [$want, $have, $class]) {
            if (isset($ent[$want]) && (int) $ent[$want] !== (int) $actual->get($have)) {
                $drifts[] = ActionPlan::drift($want, (int) $ent[$want], (int) $actual->get($have), 'ONHOST_MANAGED', $class);
            }
        }
        if (isset($ent['nvme_gb']) && (int) $ent['nvme_gb'] * 1024 !== (int) $actual->get('disk_mb')) {
            $drifts[] = ActionPlan::drift('disk_mb', (int) $ent['nvme_gb'] * 1024, (int) $actual->get('disk_mb'), 'ONHOST_MANAGED', 'REQUIRES_APPROVAL');
        }
        $shouldBeSuspended = (bool) $spec->get('suspended', false);
        if ($shouldBeSuspended !== (bool) $actual->get('suspended')) {
            $drifts[] = ActionPlan::drift('suspended', $shouldBeSuspended, (bool) $actual->get('suspended'), 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS');
        }

        return new ActionPlan($drifts);
    }

    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult
    {
        $ent = (array) $spec->get('entitlements', []);
        $state = $this->getActualState($ref);
        $this->request('PATCH', "/api/application/servers/{$ref->remoteId}/build", 'app', 'servers.build', [
            'allocation' => (int) ($ref->meta['allocation_id'] ?? 0),
            'memory' => (int) ($ent['ram_mb'] ?? $state->get('ram_mb')), 'swap' => 0, 'disk' => isset($ent['nvme_gb']) ? (int) $ent['nvme_gb'] * 1024 : (int) $state->get('disk_mb'), 'io' => 500,
            'cpu' => (int) ($spec->get('limits.cpu_pct') ?? $state->get('cpu_pct')),
            'feature_limits' => ['databases' => (int) ($ent['databases'] ?? $state->get('databases')), 'allocations' => (int) ($ent['allocations'] ?? $state->get('allocations')), 'backups' => (int) ($ent['backups'] ?? $state->get('backups'))],
        ], critical: true);

        return ProviderResult::completed($ref, ['resized' => true]);
    }

    public function suspend(ResourceRef $ref): ProviderResult
    {
        $this->request('POST', "/api/application/servers/{$ref->remoteId}/suspend", 'app', 'servers.suspend', [], critical: true);

        return ProviderResult::completed($ref, ['suspended' => true]);
    }

    public function resume(ResourceRef $ref): ProviderResult
    {
        $this->request('POST', "/api/application/servers/{$ref->remoteId}/unsuspend", 'app', 'servers.unsuspend', [], critical: true);

        return ProviderResult::completed($ref, ['suspended' => false]);
    }

    public function terminate(ResourceRef $ref): ProviderResult
    {
        if (! $this->getActualState($ref)->exists) {
            return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
        }
        $this->request('DELETE', "/api/application/servers/{$ref->remoteId}/force", 'app', 'servers.delete', [], critical: true);

        return ProviderResult::completed(null, ['deleted' => true]);
    }

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        $res = $this->request('GET', "/api/client/servers/{$this->identifier($ref)}/resources", 'client', 'servers.resources');
        $a = $res['attributes'];

        return new Usage([
            'state' => $a['current_state'] ?? null, 'cpu_pct' => (float) ($a['resources']['cpu_absolute'] ?? 0), 'mem_bytes' => (int) ($a['resources']['memory_bytes'] ?? 0),
            'disk_bytes' => (int) ($a['resources']['disk_bytes'] ?? 0), 'net_in_bytes' => (int) ($a['resources']['network_rx_bytes'] ?? 0), 'net_out_bytes' => (int) ($a['resources']['network_tx_bytes'] ?? 0), 'uptime_s' => (int) (($a['resources']['uptime'] ?? 0) / 1000),
        ], now()->toISOString());
    }

    public function power(ResourceRef $ref, string $action): ProviderResult
    {
        $signal = match ($action) {
            'start' => 'start', 'stop', 'shutdown' => 'stop', 'reboot', 'reset' => 'restart', 'kill' => 'kill', default => throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, "Unsupported power action {$action}")
        };
        $this->request('POST', "/api/client/servers/{$this->identifier($ref)}/power", 'client', 'servers.power', ['signal' => $signal], critical: true);

        return ProviderResult::completed($ref, ['signal' => $signal]);
    }

    public function sendCommand(ResourceRef $server, string $command): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/command", 'client', 'servers.command', ['command' => mb_substr($command, 0, 1000)]);

        return ProviderResult::completed($server, ['sent' => true]);
    }

    public function createSchedule(ResourceRef $server, array $schedule): ProviderResult
    {
        [$minute, $hour, $dom, $month, $dow] = array_pad(explode(' ', trim($schedule['cron'])), 5, '*');
        $id = $this->identifier($server);
        $created = $this->request('POST', "/api/client/servers/{$id}/schedules", 'client', 'schedules.create', ['name' => $schedule['name'], 'minute' => $minute, 'hour' => $hour, 'day_of_month' => $dom, 'month' => $month, 'day_of_week' => $dow, 'is_active' => true, 'only_when_online' => false]);
        $scheduleId = (int) $created['attributes']['id'];
        $sequence = 1;
        foreach ($schedule['actions'] as $action) {
            $this->request('POST', "/api/client/servers/{$id}/schedules/{$scheduleId}/tasks", 'client', 'schedules.task', ['action' => $action['action'], 'payload' => $action['payload'], 'time_offset' => 0, 'sequence_id' => $sequence++, 'continue_on_failure' => false]);
        }

        return ProviderResult::completed(new ResourceRef('schedule', (string) $scheduleId, $server->node, [], $server->serviceId));
    }

    public function backup(ResourceRef $ref, array $policy): ProviderResult
    {
        $created = $this->request('POST', "/api/client/servers/{$this->identifier($ref)}/backups", 'client', 'backups.create', array_filter(['name' => $policy['name'] ?? 'onhost-'.now()->format('Ymd-His'), 'is_locked' => (bool) ($policy['protected'] ?? false), 'ignored' => $policy['ignored'] ?? null], fn ($v) => $v !== null), critical: true);
        $uuid = (string) $created['attributes']['uuid'];

        return ProviderResult::accepted(new AsyncHandle('ptero_backup', $uuid, $ref->node, ['identifier' => $this->identifier($ref)], 15, 2 * 3600), $ref, ['backup_uuid' => $uuid]);
    }

    public function listBackups(ResourceRef $ref): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($ref)}/backups", 'client', 'backups.list')['data'] ?? []) as $b) {
            $a = $b['attributes'];
            $out[] = ['remote_id' => (string) $a['uuid'], 'created_at' => (string) $a['created_at'], 'size_bytes' => (int) ($a['bytes'] ?? 0), 'verified' => $a['is_successful'] ?? null, 'protected' => (bool) ($a['is_locked'] ?? false), 'meta' => ['name' => $a['name'], 'completed_at' => $a['completed_at'] ?? null]];
        }

        return $out;
    }

    public function restore(ResourceRef $ref, string $backupRemoteId, array $options = []): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($ref)}/backups/{$backupRemoteId}/restore", 'client', 'backups.restore', ['truncate' => (bool) ($options['truncate'] ?? false)], critical: true);

        return ProviderResult::accepted(new AsyncHandle('ptero_restore', $backupRemoteId, $ref->node, ['identifier' => $this->identifier($ref), 'server_id' => $ref->remoteId], 15, 2 * 3600), $ref);
    }

    public function consoleAccess(ResourceRef $ref): array
    {
        $ws = $this->request('GET', "/api/client/servers/{$this->identifier($ref)}/websocket", 'client', 'servers.websocket');
        $ttl = min((int) config('onhost.provisioning.console_token_ttl_seconds', 120), 600);
        $token = 'con_'.strtolower((string) Str::ulid());
        $this->cache->put("onhost:console:{$token}", ['kind' => 'wings_ws', 'socket' => (string) $ws['data']['socket'], 'token' => (string) $ws['data']['token'], 'instance' => $this->instance->id, 'service_id' => $ref->serviceId], $ttl);

        // The Wings token is short-lived and per session; it is delivered to the browser through the ONhost console
        // endpoint (which re-checks authorization) rather than embedded in a page.
        return ['kind' => 'wings', 'url' => (string) $ws['data']['socket'], 'token' => $token, 'expires_at' => now()->addSeconds($ttl)->toISOString(), 'meta' => ['identifier' => $this->identifier($ref)]];
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        if ($handle->kind === 'ptero_install') {
            $server = $this->request('GET', "/api/application/servers/{$handle->handle}", 'app', 'servers.get');
            $a = $server['attributes'];
            if ((int) ($a['container']['installed'] ?? 0) === 1 && ($a['status'] ?? null) === null) {
                return AsyncStatus::succeeded(['identifier' => $a['identifier'], 'uuid' => $a['uuid']]);
            }
            if (($a['status'] ?? '') === 'install_failed') {
                return AsyncStatus::failed('Pterodactyl install failed', ['status' => $a['status']]);
            }

            return AsyncStatus::running((string) ($a['status'] ?? 'installing'));
        }
        if ($handle->kind === 'ptero_restore' && (string) ($handle->meta['server_id'] ?? '') !== '') {
            // The panel marks the server `restoring_backup` for as long as Wings unpacks the archive and clears the mark when it
            // is done. The power state said nothing: a server being restored is `offline`, which used to read as "finished" at the
            // first poll — while the client API answers 409 to almost everything until the restore is over.
            $status = $this->request('GET', "/api/application/servers/{$handle->meta['server_id']}", 'app', 'servers.get')['attributes']['status'] ?? null;

            return match (true) {
                $status === 'restoring_backup' => AsyncStatus::running('restoring the backup'),
                $status === null || $status === '' => AsyncStatus::succeeded(['restored' => true]),
                default => AsyncStatus::failed("the server is {$status} after the restore", ['status' => $status]),
            };
        }
        if (in_array($handle->kind, ['ptero_backup', 'ptero_restore'], true)) {
            $backup = $this->request('GET', "/api/client/servers/{$handle->meta['identifier']}/backups/{$handle->handle}", 'client', 'backups.get');
            $a = $backup['attributes'];
            if ($handle->kind === 'ptero_backup') {
                if (! empty($a['completed_at'])) {
                    return ($a['is_successful'] ?? true) ? AsyncStatus::succeeded(['uuid' => $a['uuid'], 'bytes' => $a['bytes'] ?? null, 'checksum' => $a['checksum'] ?? null]) : AsyncStatus::failed('backup failed');
                }

                return AsyncStatus::running('backup in progress');
            }
            $state = $this->request('GET', "/api/client/servers/{$handle->meta['identifier']}/resources", 'client', 'servers.resources')['attributes']['current_state'] ?? 'offline';

            return in_array($state, ['offline', 'running'], true) ? AsyncStatus::succeeded(['state' => $state]) : AsyncStatus::running($state);
        }

        return AsyncStatus::unknown("unknown handle kind {$handle->kind}");
    }

    // ── game tools (client API around one server) ────────────────────────────

    public function status(ResourceRef $server): array
    {
        $id = $this->identifier($server);
        $res = $this->request('GET', "/api/client/servers/{$id}/resources", 'client', 'servers.resources')['attributes'] ?? [];
        $detail = $this->request('GET', "/api/client/servers/{$id}", 'client', 'servers.detail')['attributes'] ?? [];
        $r = (array) ($res['resources'] ?? []);

        return [
            'state' => (string) ($res['current_state'] ?? 'unknown'), 'cpu_pct' => round((float) ($r['cpu_absolute'] ?? 0), 1), 'mem_bytes' => (int) ($r['memory_bytes'] ?? 0), 'mem_limit_bytes' => (int) ($detail['limits']['memory'] ?? 0) * 1048576,
            'disk_bytes' => (int) ($r['disk_bytes'] ?? 0), 'disk_limit_bytes' => (int) ($detail['limits']['disk'] ?? 0) * 1048576, 'uptime_s' => (int) (($r['uptime'] ?? 0) / 1000), 'net_in_bytes' => (int) ($r['network_rx_bytes'] ?? 0), 'net_out_bytes' => (int) ($r['network_tx_bytes'] ?? 0),
            'installing' => (bool) ($detail['is_installing'] ?? false), 'suspended' => (bool) ($detail['is_suspended'] ?? $res['is_suspended'] ?? false),
        ];
    }

    public function serverDetail(ResourceRef $server): array
    {
        $a = $this->request('GET', "/api/client/servers/{$this->identifier($server)}", 'client', 'servers.detail')['attributes'] ?? [];
        $primary = null;
        foreach ((array) ($a['relationships']['allocations']['data'] ?? []) as $alloc) {
            $x = (array) ($alloc['attributes'] ?? []);
            if (! empty($x['is_default'])) {
                $primary = ['ip' => (string) ($x['ip'] ?? ''), 'port' => (int) ($x['port'] ?? 0), 'alias' => $x['ip_alias'] ?? null];
            }
        }

        return [
            'name' => (string) ($a['name'] ?? ''), 'description' => $a['description'] ?? null,
            'sftp' => ['host' => (string) ($a['sftp_details']['ip'] ?? ''), 'port' => (int) ($a['sftp_details']['port'] ?? 2022), 'username' => (string) ($a['identifier'] ?? '')],
            'allocation' => $primary, 'egg_features' => array_values(array_map('strval', (array) ($a['egg_features'] ?? []))), 'docker_image' => (string) ($a['docker_image'] ?? ''), 'invocation' => (string) ($a['invocation'] ?? ''),
        ];
    }

    public function startup(ResourceRef $server): array
    {
        $r = $this->request('GET', "/api/client/servers/{$this->identifier($server)}/startup", 'client', 'servers.startup');
        $variables = [];
        foreach ((array) ($r['data'] ?? []) as $v) {
            $a = (array) ($v['attributes'] ?? []);
            $variables[] = ['name' => (string) ($a['name'] ?? ''), 'key' => (string) ($a['env_variable'] ?? ''), 'value' => (string) ($a['server_value'] ?? ''), 'default' => (string) ($a['default_value'] ?? ''), 'description' => (string) ($a['description'] ?? ''), 'editable' => (bool) ($a['is_editable'] ?? false), 'rules' => (string) ($a['rules'] ?? '')];
        }

        return ['startup' => (string) ($r['meta']['startup_command'] ?? ''), 'raw_startup' => (string) ($r['meta']['raw_startup_command'] ?? ''), 'docker_image' => (string) ($r['meta']['docker_image'] ?? ''), 'docker_images' => array_map('strval', (array) ($r['meta']['docker_images'] ?? [])), 'variables' => $variables];
    }

    public function setVariable(ResourceRef $server, string $key, string $value): ProviderResult
    {
        $r = $this->request('PUT', "/api/client/servers/{$this->identifier($server)}/startup/variable", 'client', 'servers.variable', ['key' => $key, 'value' => $value]);

        return ProviderResult::completed($server, ['key' => $key, 'value' => (string) ($r['attributes']['server_value'] ?? $value)]);
    }

    public function setStartup(ResourceRef $server, ?string $startup, ?string $image, array $environment = []): ProviderResult
    {
        $a = (array) ($this->request('GET', "/api/application/servers/{$server->remoteId}", 'app', 'servers.get')['attributes'] ?? []);
        $body = [
            'startup' => $startup !== null && $startup !== '' ? $startup : (string) ($a['container']['startup_command'] ?? ''), 'egg' => (int) ($a['egg'] ?? 0), 'image' => $image !== null && $image !== '' ? $image : (string) ($a['container']['image'] ?? ''),
            'environment' => array_map('strval', array_merge((array) ($a['container']['environment'] ?? []), $environment)), 'skip_scripts' => true,
        ];
        $this->request('PATCH', "/api/application/servers/{$server->remoteId}/startup", 'app', 'servers.startup.update', $body, critical: true);

        return ProviderResult::completed($server, ['startup' => $body['startup'], 'image' => $body['image']]);
    }

    public function setDockerImage(ResourceRef $server, string $image): ProviderResult
    {
        $this->request('PUT', "/api/client/servers/{$this->identifier($server)}/settings/docker-image", 'client', 'servers.image', ['docker_image' => $image]);

        return ProviderResult::completed($server, ['docker_image' => $image]);
    }

    public function rename(ResourceRef $server, string $name): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/settings/rename", 'client', 'servers.rename', ['name' => mb_substr($name, 0, 191)]);

        return ProviderResult::completed($server, ['name' => $name]);
    }

    public function reinstall(ResourceRef $server): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/settings/reinstall", 'client', 'servers.reinstall', [], critical: true);

        return ProviderResult::accepted(new AsyncHandle('ptero_install', $server->remoteId, $server->node, ['identifier' => $this->identifier($server)], 10, 1800), $server, ['reinstalling' => true]);
    }

    public function listSchedules(ResourceRef $server): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/schedules", 'client', 'schedules.list')['data'] ?? []) as $s) {
            $a = (array) ($s['attributes'] ?? []);
            $c = (array) ($a['cron'] ?? []);
            $tasks = [];
            foreach ((array) ($a['relationships']['tasks']['data'] ?? []) as $t) {
                $ta = (array) ($t['attributes'] ?? []);
                $tasks[] = ['action' => (string) ($ta['action'] ?? ''), 'payload' => (string) ($ta['payload'] ?? ''), 'sequence' => (int) ($ta['sequence_id'] ?? 0)];
            }
            $out[] = ['remote_id' => (string) ($a['id'] ?? ''), 'name' => (string) ($a['name'] ?? ''), 'cron' => trim(($c['minute'] ?? '*').' '.($c['hour'] ?? '*').' '.($c['day_of_month'] ?? '*').' '.($c['month'] ?? '*').' '.($c['day_of_week'] ?? '*')), 'active' => (bool) ($a['is_active'] ?? false), 'processing' => (bool) ($a['is_processing'] ?? false), 'only_when_online' => (bool) ($a['only_when_online'] ?? false), 'last_run_at' => $a['last_run_at'] ?? null, 'next_run_at' => $a['next_run_at'] ?? null, 'tasks' => $tasks];
        }

        return $out;
    }

    public function setScheduleActive(ResourceRef $server, string $scheduleId, bool $active): ProviderResult
    {
        $id = $this->identifier($server);
        $a = (array) ($this->request('GET', "/api/client/servers/{$id}/schedules/{$scheduleId}", 'client', 'schedules.get')['attributes'] ?? []);
        $c = (array) ($a['cron'] ?? []);
        $this->request('POST', "/api/client/servers/{$id}/schedules/{$scheduleId}", 'client', 'schedules.update', ['name' => (string) ($a['name'] ?? 'schedule'), 'minute' => (string) ($c['minute'] ?? '*'), 'hour' => (string) ($c['hour'] ?? '*'), 'day_of_month' => (string) ($c['day_of_month'] ?? '*'), 'month' => (string) ($c['month'] ?? '*'), 'day_of_week' => (string) ($c['day_of_week'] ?? '*'), 'is_active' => $active, 'only_when_online' => (bool) ($a['only_when_online'] ?? false)]);

        return ProviderResult::completed($server, ['remote_id' => $scheduleId, 'active' => $active]);
    }

    public function runSchedule(ResourceRef $server, string $scheduleId): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/schedules/{$scheduleId}/execute", 'client', 'schedules.execute');

        return ProviderResult::completed($server, ['remote_id' => $scheduleId, 'executed' => true]);
    }

    public function deleteSchedule(ResourceRef $server, string $scheduleId): ProviderResult
    {
        $this->request('DELETE', "/api/client/servers/{$this->identifier($server)}/schedules/{$scheduleId}", 'client', 'schedules.delete');

        return ProviderResult::completed($server, ['remote_id' => $scheduleId, 'deleted' => true]);
    }

    public function listDatabases(ResourceRef $server, bool $reveal = false): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/databases", 'client', 'databases.list', [], $reveal ? ['include' => 'password'] : [])['data'] ?? []) as $d) {
            $out[] = $this->database((array) ($d['attributes'] ?? []), $reveal);
        }

        return $out;
    }

    public function createDatabase(ResourceRef $server, string $name, string $remote = '%'): ProviderResult
    {
        $created = $this->request('POST', "/api/client/servers/{$this->identifier($server)}/databases", 'client', 'databases.create', ['database' => $name, 'remote' => $remote], critical: true);
        $db = $this->database((array) ($created['attributes'] ?? []), true);

        return ProviderResult::completed(new ResourceRef('game_database', $db['remote_id'], $server->node, [], $server->serviceId), $db);
    }

    public function rotateDatabasePassword(ResourceRef $server, string $databaseId): ProviderResult
    {
        $r = $this->request('POST', "/api/client/servers/{$this->identifier($server)}/databases/{$databaseId}/rotate-password", 'client', 'databases.rotate', [], critical: true);

        return ProviderResult::completed($server, $this->database((array) ($r['attributes'] ?? []), true));
    }

    public function deleteDatabase(ResourceRef $server, string $databaseId): ProviderResult
    {
        $this->request('DELETE', "/api/client/servers/{$this->identifier($server)}/databases/{$databaseId}", 'client', 'databases.delete', [], critical: true);

        return ProviderResult::completed($server, ['remote_id' => $databaseId, 'deleted' => true]);
    }

    public function listSubusers(ResourceRef $server): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/users", 'client', 'subusers.list')['data'] ?? []) as $u) {
            $a = (array) ($u['attributes'] ?? []);
            $out[] = ['remote_id' => (string) ($a['uuid'] ?? ''), 'email' => (string) ($a['email'] ?? ''), 'username' => $a['username'] ?? null, 'permissions' => array_values(array_map('strval', (array) ($a['permissions'] ?? []))), 'created_at' => $a['created_at'] ?? null];
        }

        return $out;
    }

    public function createSubuser(ResourceRef $server, string $email, array $permissions): ProviderResult
    {
        $created = $this->request('POST', "/api/client/servers/{$this->identifier($server)}/users", 'client', 'subusers.create', ['email' => $email, 'permissions' => array_values($permissions)], critical: true);
        $a = (array) ($created['attributes'] ?? []);

        return ProviderResult::completed(new ResourceRef('subuser', (string) ($a['uuid'] ?? ''), $server->node, [], $server->serviceId), ['remote_id' => (string) ($a['uuid'] ?? ''), 'email' => $email, 'permissions' => array_values($permissions)]);
    }

    public function deleteSubuser(ResourceRef $server, string $subuserId): ProviderResult
    {
        $this->request('DELETE', "/api/client/servers/{$this->identifier($server)}/users/{$subuserId}", 'client', 'subusers.delete', [], critical: true);

        return ProviderResult::completed($server, ['remote_id' => $subuserId, 'deleted' => true]);
    }

    public function listFiles(ResourceRef $server, string $directory = '/'): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/files/list", 'client', 'files.list', [], ['directory' => '/'.ltrim($directory, '/')])['data'] ?? []) as $f) {
            $a = (array) ($f['attributes'] ?? []);
            $out[] = ['name' => (string) ($a['name'] ?? ''), 'type' => ! empty($a['is_file']) ? 'file' : 'dir', 'size' => (int) ($a['size'] ?? 0), 'modified' => $a['modified_at'] ?? null, 'mode' => (string) ($a['mode'] ?? '')];
        }
        usort($out, fn ($a, $b) => [$a['type'] !== 'dir', $a['name']] <=> [$b['type'] !== 'dir', $b['name']]);

        return $out;
    }

    public function readFile(ResourceRef $server, string $path): string
    {
        return $this->raw('GET', "/api/client/servers/{$this->identifier($server)}/files/contents", 'files.read', null, ['file' => '/'.ltrim($path, '/')]);
    }

    public function writeFile(ResourceRef $server, string $path, string $content): ProviderResult
    {
        $this->raw('POST', "/api/client/servers/{$this->identifier($server)}/files/write", 'files.write', $content, ['file' => '/'.ltrim($path, '/')]);

        return ProviderResult::completed($server, ['path' => $path, 'bytes' => strlen($content)]);
    }

    public function uploadFile(ResourceRef $server, string $directory, string $filename, mixed $contents): ProviderResult
    {
        $signed = (string) data_get($this->request('GET', "/api/client/servers/{$this->identifier($server)}/files/upload", 'client', 'files.upload_url'), 'attributes.url', '');
        if ($signed === '') {
            throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, 'The panel returned no upload URL');
        }
        $directory = '/'.trim($directory, '/');
        $response = $this->http->send(new ProviderRequest( // the daemon's own endpoint; the one-time token lives in the URL, the logger keeps the path only
            provider: 'pterodactyl', instanceKey: $this->instance->key, method: 'POST', url: $signed.(str_contains($signed, '?') ? '&' : '?').'directory='.rawurlencode($directory), action: 'files.upload',
            headers: ['Accept' => 'application/json'], body: null, bodyType: 'multipart', timeoutSeconds: 600, critical: true, files: ['files' => ['contents' => $contents, 'filename' => basename($filename)]],
        ));
        if ($response->status >= 400) {
            $this->unwrap($response, 'files.upload');
        }

        return ProviderResult::completed($server, ['directory' => $directory, 'name' => basename($filename)]);
    }

    public function deleteFiles(ResourceRef $server, string $root, array $files): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/files/delete", 'client', 'files.delete', ['root' => '/'.ltrim($root, '/'), 'files' => array_values($files)], critical: true);

        return ProviderResult::completed($server, ['root' => $root, 'files' => array_values($files), 'deleted' => true]);
    }

    public function createDirectory(ResourceRef $server, string $root, string $name): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/files/create-folder", 'client', 'files.mkdir', ['root' => '/'.ltrim($root, '/'), 'name' => $name]);

        return ProviderResult::completed($server, ['root' => $root, 'name' => $name]);
    }

    public function renameFile(ResourceRef $server, string $root, string $from, string $to): ProviderResult
    {
        $this->request('PUT', "/api/client/servers/{$this->identifier($server)}/files/rename", 'client', 'files.rename', ['root' => '/'.ltrim($root, '/'), 'files' => [['from' => $from, 'to' => $to]]]);

        return ProviderResult::completed($server, ['root' => $root, 'from' => $from, 'to' => $to]);
    }

    public function listAllocations(ResourceRef $server): array
    {
        $out = [];
        foreach ((array) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/network/allocations", 'client', 'allocations.list')['data'] ?? []) as $x) {
            $out[] = $this->allocation((array) ($x['attributes'] ?? []));
        }

        return $out;
    }

    public function addAllocation(ResourceRef $server): ProviderResult
    {
        $r = $this->request('POST', "/api/client/servers/{$this->identifier($server)}/network/allocations", 'client', 'allocations.add', [], critical: true);
        $alloc = $this->allocation((array) ($r['attributes'] ?? []));

        return ProviderResult::completed(new ResourceRef('allocation', $alloc['remote_id'], $server->node, [], $server->serviceId), $alloc);
    }

    public function setPrimaryAllocation(ResourceRef $server, string $allocationId): ProviderResult
    {
        $this->request('POST', "/api/client/servers/{$this->identifier($server)}/network/allocations/{$allocationId}/primary", 'client', 'allocations.primary', [], critical: true);

        return ProviderResult::completed($server, ['remote_id' => $allocationId, 'primary' => true]);
    }

    public function removeAllocation(ResourceRef $server, string $allocationId): ProviderResult
    {
        $this->request('DELETE', "/api/client/servers/{$this->identifier($server)}/network/allocations/{$allocationId}", 'client', 'allocations.remove', [], critical: true);

        return ProviderResult::completed($server, ['remote_id' => $allocationId, 'deleted' => true]);
    }

    public function deleteBackup(ResourceRef $server, string $backupId): ProviderResult
    {
        $this->request('DELETE', "/api/client/servers/{$this->identifier($server)}/backups/{$backupId}", 'client', 'backups.delete', [], critical: true);

        return ProviderResult::completed($server, ['remote_id' => $backupId, 'deleted' => true]);
    }

    public function lockBackup(ResourceRef $server, string $backupId, bool $locked): ProviderResult
    {
        $id = $this->identifier($server);
        $current = (bool) ($this->request('GET', "/api/client/servers/{$id}/backups/{$backupId}", 'client', 'backups.get')['attributes']['is_locked'] ?? false);
        if ($current !== $locked) { // the panel toggles; read first so the call is idempotent
            $this->request('POST', "/api/client/servers/{$id}/backups/{$backupId}/lock", 'client', 'backups.lock');
        }

        return ProviderResult::completed($server, ['remote_id' => $backupId, 'locked' => $locked]);
    }

    public function backupDownloadUrl(ResourceRef $server, string $backupId): string
    {
        return (string) ($this->request('GET', "/api/client/servers/{$this->identifier($server)}/backups/{$backupId}/download", 'client', 'backups.download')['attributes']['url'] ?? '');
    }

    // ── migrations (audit §5g-2) ─────────────────────────────────────────────

    public function serverDefinition(ResourceRef $server): array
    {
        $a = $this->request('GET', "/api/application/servers/{$server->remoteId}", 'app', 'servers.get')['attributes'] ?? [];

        return [
            'name' => (string) ($a['name'] ?? ''), 'nest' => (int) ($a['nest'] ?? 0), 'egg' => (int) ($a['egg'] ?? 0), 'user' => (int) ($a['user'] ?? 0), 'node' => (int) ($a['node'] ?? 0),
            'identifier' => (string) ($a['identifier'] ?? ''), 'uuid' => (string) ($a['uuid'] ?? ''), 'external_id' => isset($a['external_id']) ? (string) $a['external_id'] : null,
            'docker_image' => (string) ($a['container']['image'] ?? ''), 'startup' => (string) ($a['container']['startup_command'] ?? ''), 'environment' => (array) ($a['container']['environment'] ?? []),
            'limits' => (array) ($a['limits'] ?? []), 'feature_limits' => (array) ($a['feature_limits'] ?? []), 'allocation' => (int) ($a['allocation'] ?? 0),
        ];
    }

    /**
     * The archive travels daemon to daemon through the control plane: the signed download link of the source daemon
     * is streamed to a temporary file, pushed to the target daemon's signed upload link, unpacked with the panel's
     * decompress call and the archive removed. Neither signed link is logged (both carry a short-lived token).
     */
    public function importArchive(ResourceRef $server, string $sourceUrl, string $fileName = 'onhost-import.tar.gz'): array
    {
        $id = $this->identifier($server);
        $upload = (string) ($this->request('GET', "/api/client/servers/{$id}/files/upload", 'client', 'files.upload')['attributes']['url'] ?? '');
        if ($upload === '') {
            throw new ProviderException('pterodactyl', ProviderErrorCode::PROVIDER_BUG, 'The panel returned no upload link for the server');
        }
        $tmp = (string) tempnam(sys_get_temp_dir(), 'onhost-gmig-');
        try {
            $download = Http::withOptions(['sink' => $tmp])->timeout(6 * 3600)->connectTimeout(30)->get($sourceUrl);
            if ($download->failed()) {
                throw new ProviderException('pterodactyl', ProviderErrorCode::TRANSIENT, "Archive download failed (HTTP {$download->status()})");
            }
            if ((int) filesize($tmp) === 0 && $download->body() !== '') {
                file_put_contents($tmp, $download->body()); // a body that did not go through the sink (stubbed transports)
            }
            $bytes = (int) filesize($tmp);
            $stream = fopen($tmp, 'rb');
            if ($stream === false) {
                throw new ProviderException('pterodactyl', ProviderErrorCode::TRANSIENT, 'The downloaded archive could not be read');
            }
            $uploaded = Http::attach('files', $stream, $fileName)->timeout(6 * 3600)->connectTimeout(30)->post($upload.(str_contains($upload, '?') ? '&' : '?').'directory=%2F');
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($uploaded->failed()) {
                throw new ProviderException('pterodactyl', ProviderErrorCode::TRANSIENT, "Archive upload failed (HTTP {$uploaded->status()})");
            }
        } finally {
            @unlink($tmp);
        }
        $this->request('POST', "/api/client/servers/{$id}/files/decompress", 'client', 'files.decompress', ['root' => '/', 'file' => $fileName], critical: true);
        $this->request('POST', "/api/client/servers/{$id}/files/delete", 'client', 'files.delete', ['root' => '/', 'files' => [$fileName]]);

        return ['bytes' => $bytes, 'file' => $fileName];
    }

    public function panelAccount(ResourceRef $server): array
    {
        $userId = (string) ($server->meta['user_id'] ?? '');
        if ($userId === '') {
            $userId = (string) ($this->request('GET', "/api/application/servers/{$server->remoteId}", 'app', 'servers.get')['attributes']['user'] ?? '');
        }
        $a = (array) ($this->request('GET', "/api/application/users/{$userId}", 'app', 'users.get')['attributes'] ?? []);

        return ['url' => rtrim((string) $this->instance->base_url, '/'), 'username' => $a['username'] ?? null, 'email' => $a['email'] ?? null, 'remote_id' => $userId];
    }

    public function setPanelPassword(ResourceRef $server, string $password): ProviderResult
    {
        $account = $this->panelAccount($server);
        $a = (array) ($this->request('GET', "/api/application/users/{$account['remote_id']}", 'app', 'users.get')['attributes'] ?? []);
        if (! empty($a['root_admin'])) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, 'The server belongs to a panel administrator; its password is not managed here');
        }
        $this->request('PATCH', "/api/application/users/{$account['remote_id']}", 'app', 'users.password', ['email' => (string) ($a['email'] ?? ''), 'username' => (string) ($a['username'] ?? ''), 'first_name' => (string) ($a['first_name'] ?? 'ONhost'), 'last_name' => (string) ($a['last_name'] ?? 'Customer'), 'language' => (string) ($a['language'] ?? 'en'), 'password' => $password], critical: true);

        return ProviderResult::completed($server, ['username' => $a['username'] ?? null, 'url' => $account['url'], 'changed' => true]);
    }

    // ── game tools (control plane) ───────────────────────────────────────────

    public function listServers(): array
    {
        $out = [];
        foreach ($this->pages('/api/application/servers', 'app', 'servers.list') as $s) {
            $a = (array) ($s['attributes'] ?? []);
            $out[] = ['id' => (int) ($a['id'] ?? 0), 'identifier' => (string) ($a['identifier'] ?? ''), 'uuid' => (string) ($a['uuid'] ?? ''), 'external_id' => $a['external_id'] ?? null, 'name' => (string) ($a['name'] ?? ''), 'node' => (int) ($a['node'] ?? 0), 'user' => (int) ($a['user'] ?? 0), 'egg' => (int) ($a['egg'] ?? 0),
                'suspended' => (bool) ($a['suspended'] ?? false), 'installed' => (int) ($a['container']['installed'] ?? 0) === 1, 'status' => $a['status'] ?? null, 'memory' => (int) ($a['limits']['memory'] ?? 0), 'disk' => (int) ($a['limits']['disk'] ?? 0), 'cpu' => (int) ($a['limits']['cpu'] ?? 0), 'allocation' => (int) ($a['allocation'] ?? 0)];
        }

        return $out;
    }

    public function listEggs(): array
    {
        $out = [];
        foreach ($this->pages('/api/application/nests', 'app', 'nests.list') as $nest) {
            $n = (array) ($nest['attributes'] ?? []);
            foreach ($this->pages("/api/application/nests/{$n['id']}/eggs", 'app', 'eggs.list') as $egg) {
                $a = (array) ($egg['attributes'] ?? []);
                $out[] = ['nest_id' => (int) $n['id'], 'nest' => (string) ($n['name'] ?? ''), 'id' => (int) ($a['id'] ?? 0), 'name' => (string) ($a['name'] ?? ''), 'docker_image' => (string) ($a['docker_image'] ?? ''), 'docker_images' => array_map('strval', (array) ($a['docker_images'] ?? [])), 'startup' => (string) ($a['startup'] ?? ''), 'privileged' => (bool) ($a['config']['startup']['privileged'] ?? false)];
            }
        }

        return $out;
    }

    public function nodeAllocations(int $nodeId): array
    {
        $out = [];
        foreach ($this->pages("/api/application/nodes/{$nodeId}/allocations", 'app', 'allocations.list') as $alloc) {
            $a = (array) ($alloc['attributes'] ?? []);
            $out[] = ['id' => (int) ($a['id'] ?? 0), 'ip' => (string) ($a['ip'] ?? ''), 'alias' => $a['alias'] ?? null, 'port' => (int) ($a['port'] ?? 0), 'assigned' => (bool) ($a['assigned'] ?? false)];
        }

        return $out;
    }

    public function nodeDetail(int $nodeId): array
    {
        $a = (array) ($this->request('GET', "/api/application/nodes/{$nodeId}", 'app', 'nodes.detail')['attributes'] ?? []);

        return ['id' => (int) ($a['id'] ?? $nodeId), 'name' => (string) ($a['name'] ?? ''), 'fqdn' => (string) ($a['fqdn'] ?? ''), 'scheme' => (string) ($a['scheme'] ?? 'https'), 'memory' => (int) ($a['memory'] ?? 0), 'memory_overallocate' => (int) ($a['memory_overallocate'] ?? 0), 'disk' => (int) ($a['disk'] ?? 0), 'disk_overallocate' => (int) ($a['disk_overallocate'] ?? 0), 'upload_size' => (int) ($a['upload_size'] ?? 100), 'daemon_listen' => (int) ($a['daemon_listen'] ?? 8080), 'daemon_sftp' => (int) ($a['daemon_sftp'] ?? 2022), 'location_id' => (int) ($a['location_id'] ?? 1), 'maintenance' => (bool) ($a['maintenance_mode'] ?? false), 'allocated_memory' => (int) ($a['allocated_resources']['memory'] ?? 0), 'allocated_disk' => (int) ($a['allocated_resources']['disk'] ?? 0), 'public' => (bool) ($a['public'] ?? true), 'behind_proxy' => (bool) ($a['behind_proxy'] ?? false), 'description' => (string) ($a['description'] ?? '')];
    }

    public function updateNode(int $nodeId, array $fields): ProviderResult
    {
        $current = $this->nodeDetail($nodeId); // the panel's PATCH wants the whole record, so the unchanged fields come from it
        $body = [
            'name' => $current['name'], 'description' => $current['description'], 'location_id' => $current['location_id'], 'public' => $current['public'], 'fqdn' => $current['fqdn'], 'scheme' => $current['scheme'], 'behind_proxy' => $current['behind_proxy'],
            'memory' => (int) ($fields['memory'] ?? $current['memory']), 'memory_overallocate' => (int) ($fields['memory_overallocate'] ?? $current['memory_overallocate']),
            'disk' => (int) ($fields['disk'] ?? $current['disk']), 'disk_overallocate' => (int) ($fields['disk_overallocate'] ?? $current['disk_overallocate']),
            'upload_size' => $current['upload_size'], 'daemon_sftp' => $current['daemon_sftp'], 'daemon_listen' => $current['daemon_listen'], 'maintenance_mode' => (bool) ($fields['maintenance_mode'] ?? $current['maintenance']),
        ];
        $a = (array) ($this->request('PATCH', "/api/application/nodes/{$nodeId}", 'app', 'nodes.update', $body, critical: true)['attributes'] ?? []);

        return ProviderResult::completed(null, ['id' => $nodeId, 'memory' => (int) ($a['memory'] ?? $body['memory']), 'memory_overallocate' => (int) ($a['memory_overallocate'] ?? $body['memory_overallocate']), 'disk' => (int) ($a['disk'] ?? $body['disk']), 'disk_overallocate' => (int) ($a['disk_overallocate'] ?? $body['disk_overallocate']), 'maintenance' => (bool) ($a['maintenance_mode'] ?? $body['maintenance_mode'])]);
    }

    public function nodeSystem(int $nodeId): ?array
    {
        $node = $this->nodeDetail($nodeId);
        $config = $this->request('GET', "/api/application/nodes/{$nodeId}/configuration", 'app', 'nodes.configuration');
        $token = (string) ($config['token'] ?? '');
        if ($token === '' || $node['fqdn'] === '') {
            return null;
        }
        $port = (int) data_get($config, 'api.port', $node['daemon_listen']);
        $scheme = data_get($config, 'api.ssl.enabled', $node['scheme'] === 'https') ? 'https' : 'http';
        $response = $this->http->send(new ProviderRequest(provider: 'pterodactyl', instanceKey: $this->instance->key, method: 'GET', url: "{$scheme}://{$node['fqdn']}:{$port}/api/system", action: 'wings.system', headers: ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'], body: null, bodyType: 'json', query: ['v' => '2'], timeoutSeconds: 8, critical: false, idempotent: true));
        if ($response->status >= 400) {
            return null;
        }
        $json = (array) $response->json();
        $memoryBytes = data_get($json, 'system.memory_bytes');

        return ['memory_mb' => is_numeric($memoryBytes) && $memoryBytes > 0 ? (int) floor(((float) $memoryBytes) / 1048576) : null, 'cpu_threads' => (int) (data_get($json, 'system.cpu_threads', $json['cpu_count'] ?? 0)) ?: null, 'os' => data_get($json, 'system.os', $json['os'] ?? null), 'version' => $json['version'] ?? null];
    }

    public function createAllocations(int $nodeId, string $ip, array $ports, ?string $alias = null): ProviderResult
    {
        $this->request('POST', "/api/application/nodes/{$nodeId}/allocations", 'app', 'allocations.create', array_filter(['ip' => $ip, 'alias' => $alias, 'ports' => array_values(array_map('strval', $ports))], fn ($v) => $v !== null), critical: true);

        return ProviderResult::completed(null, ['node' => $nodeId, 'ip' => $ip, 'ports' => array_values($ports)]);
    }

    public function clientApiStatus(): string
    {
        if ((string) ($this->credentials['client_key'] ?? '') === '') {
            return 'missing';
        }
        try {
            $this->request('GET', '/api/client/account', 'client', 'account.get');

            return 'ok';
        } catch (ProviderException $e) {
            return $e->errorCode === ProviderErrorCode::AUTH ? 'rejected' : 'error';
        }
    }

    /** @return array{remote_id:string,name:string,username:string,host:string,port:int,connections_from:string,password:?string} */
    private function database(array $a, bool $reveal): array
    {
        return ['remote_id' => (string) ($a['id'] ?? ''), 'name' => (string) ($a['name'] ?? ''), 'username' => (string) ($a['username'] ?? ''), 'host' => (string) ($a['host']['address'] ?? ''), 'port' => (int) ($a['host']['port'] ?? 3306), 'connections_from' => (string) ($a['connections_from'] ?? '%'), 'password' => $reveal ? ($a['relationships']['password']['attributes']['password'] ?? null) : null];
    }

    /** @return array{remote_id:string,ip:string,alias:?string,port:int,notes:?string,primary:bool} */
    private function allocation(array $a): array
    {
        return ['remote_id' => (string) ($a['id'] ?? ''), 'ip' => (string) ($a['ip'] ?? ''), 'alias' => $a['ip_alias'] ?? null, 'port' => (int) ($a['port'] ?? 0), 'notes' => $a['notes'] ?? null, 'primary' => (bool) ($a['is_default'] ?? false)];
    }

    // ── transport ────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function request(string $method, string $path, string $api, string $action, array $body = [], array $query = [], bool $critical = false): array
    {
        $key = $api === 'app' ? (string) ($this->credentials['application_key'] ?? '') : (string) ($this->credentials['client_key'] ?? '');
        if ($key === '') {
            throw new ProviderException('pterodactyl', ProviderErrorCode::AUTH, "Pterodactyl {$api} API key is not configured");
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'pterodactyl', instanceKey: $this->instance->key, method: $method, url: rtrim((string) $this->instance->base_url, '/').$path, action: $action,
            headers: ['Authorization' => "Bearer {$key}", 'Accept' => 'Application/vnd.pterodactyl.v1+json', 'Content-Type' => 'application/json'],
            body: $body === [] ? null : $body, bodyType: 'json', query: $query, timeoutSeconds: 20, critical: $critical, idempotent: $method === 'GET',
        ));

        return $this->unwrap($response, $action);
    }

    /** Plain-text transfers of the client API (file contents); the response body is returned as is. */
    private function raw(string $method, string $path, string $action, ?string $body, array $query): string
    {
        $key = (string) ($this->credentials['client_key'] ?? '');
        if ($key === '') {
            throw new ProviderException('pterodactyl', ProviderErrorCode::AUTH, 'Pterodactyl client API key is not configured');
        }
        $response = $this->http->send(new ProviderRequest(
            provider: 'pterodactyl', instanceKey: $this->instance->key, method: $method, url: rtrim((string) $this->instance->base_url, '/').$path, action: $action,
            headers: ['Authorization' => "Bearer {$key}", 'Accept' => 'Application/vnd.pterodactyl.v1+json', 'Content-Type' => 'text/plain'],
            body: $body, bodyType: 'raw', query: $query, timeoutSeconds: 30, critical: false, idempotent: $method === 'GET',
        ));
        if ($response->status >= 400) {
            $this->unwrap($response, $action); // maps the error family and throws
        }
        $this->http->recordSuccess($this->instance->key);

        return $response->rawBody;
    }

    /** @return list<array<string,mixed>> */
    private function pages(string $path, string $api, string $action): array
    {
        $items = [];
        $page = 1;
        do {
            $result = $this->request('GET', $path, $api, $action, [], ['page' => $page, 'per_page' => 100]);
            $items = array_merge($items, (array) ($result['data'] ?? []));
            $total = (int) ($result['meta']['pagination']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $total && $page <= 50);

        return $items;
    }

    private function unwrap(ProviderResponse $response, string $action): array
    {
        $json = $response->json();
        if ($response->status === 204 || ($response->status < 300 && trim($response->rawBody) === '')) { // 204 No Content and 202 Accepted (schedule execute) carry no body
            $this->http->recordSuccess($this->instance->key);

            return [];
        }
        if (in_array($response->status, [401, 403], true)) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::AUTH, "Pterodactyl rejected the key for {$action}", (string) $response->status);
        }
        if ($response->status === 404) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::NOT_FOUND, "Pterodactyl object not found for {$action}", '404');
        }
        if ($response->status === 429) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::RATE_LIMIT, 'Pterodactyl rate limited', '429', retryAfterSeconds: $response->retryAfterSeconds() ?? 15);
        }
        if ($response->status === 409) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::CONFLICT, "Pterodactyl {$action}: ".$this->errorDetail($json), '409', retryAfterSeconds: 30);
        }
        if ($response->status === 422) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, "Pterodactyl {$action}: ".$this->errorDetail($json), '422', ['errors' => $json['errors'] ?? null]);
        }
        if ($response->status >= 500) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::TRANSIENT, "Pterodactyl {$action}: ".$this->errorDetail($json), (string) $response->status);
        }
        if ($response->status >= 400) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::VALIDATION, "Pterodactyl {$action}: ".$this->errorDetail($json), (string) $response->status);
        }
        if (! is_array($json)) {
            throw new ProviderException('pterodactyl', ProviderErrorCode::PROVIDER_BUG, "Pterodactyl {$action} returned a non-JSON body");
        }
        $this->http->recordSuccess($this->instance->key);

        return $json;
    }

    private function errorDetail(mixed $json): string
    {
        if (! is_array($json)) {
            return 'unknown error';
        }
        $first = $json['errors'][0] ?? null;

        return is_array($first) ? (string) ($first['detail'] ?? $first['code'] ?? 'error').(isset($first['meta']['rule']) ? " (rule {$first['meta']['rule']})" : '') : 'unknown error';
    }

    private function findByExternalId(string $externalId): ?array
    {
        try {
            $server = $this->request('GET', '/api/application/servers/external/'.rawurlencode($externalId), 'app', 'servers.external');
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return null;
            }
            throw $e;
        }

        return $server['attributes'] ?? null;
    }

    private function refFrom(array $attributes, string $serviceId): ResourceRef
    {
        return new ResourceRef('server', (string) $attributes['id'], (string) ($attributes['node'] ?? ''), ['uuid' => $attributes['uuid'] ?? null, 'identifier' => $attributes['identifier'] ?? null, 'user_id' => $attributes['user'] ?? null, 'allocation_id' => $attributes['allocation'] ?? null], $serviceId);
    }

    private function identifier(ResourceRef $ref): string
    {
        $identifier = $ref->meta['identifier'] ?? null;
        if (is_string($identifier) && $identifier !== '') {
            return $identifier;
        }
        $server = $this->request('GET', "/api/application/servers/{$ref->remoteId}", 'app', 'servers.get');

        return (string) $server['attributes']['identifier'];
    }
}
