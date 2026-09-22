<?php

declare(strict_types=1);

namespace Onhost\Providers\Proxmox;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Onhost\Providers\Contracts\ActionPlan;
use Onhost\Providers\Contracts\ActualState;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\ProviderHealth;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Contracts\SelfProbing;
use Onhost\Providers\Contracts\Usage;
use Throwable;

/**
 * Proxmox VE executor for VPS/VDS/HA Cloud (blueprint §10). Every mutating call
 * returns a UPID; the adapter never reports "done" before the task status is OK.
 * Idempotency: VMs are tagged `onhost;<service id>;idem-<hash>` and looked up
 * through /cluster/resources before any clone.
 */
final class ProxmoxComputeProvider implements ComputeProvider, SelfProbing
{
    /** The drive a rescue image is attached to; never used for anything else, so detaching it can never take a disk away. */
    private const RESCUE_DRIVE = 'ide2';

    private readonly ProxmoxConnector $api;

    public function __construct(
        private readonly ProviderInstance $instance,
        array $credentials,
        ProviderHttpClient $http,
        private readonly CacheRepository $cache,
    ) {
        $this->api = new ProxmoxConnector($instance, $credentials, $http);
    }

    public static function providerKey(): string
    {
        return 'proxmox';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return ['8.2', '8.3', '8.4', '9.0', '9.1', '9.2'];
    }

    public function capabilities(): array
    {
        return [
            'vm.create' => true, 'vm.resize_cpu' => true, 'vm.resize_memory' => true, 'vm.resize_disk_online' => 'conditional',
            'vm.power' => true, 'snapshot.create' => true, 'snapshot.rollback' => true, 'backup.create' => true, 'backup.restore' => 'delegated_to_pbs',
            'console.vnc' => true, 'console.serial' => true, 'firewall' => true, 'cloud_init' => true, 'ha' => 'conditional', 'ipv6' => true,
        ];
    }

    public function health(): ProviderHealth
    {
        $started = hrtime(true);
        try {
            $version = $this->api->get('/version', [], 'version');
            $status = $this->api->get('/cluster/status', [], 'cluster.status');
            $quorate = null;
            foreach ((array) $status as $row) {
                if (($row['type'] ?? '') === 'cluster') {
                    $quorate = (bool) ($row['quorate'] ?? false);
                }
            }
            $ms = (int) ((hrtime(true) - $started) / 1_000_000);
            $this->cache->put("onhost:pve:version:{$this->instance->id}", (string) ($version['version'] ?? ''), 3600);

            return new ProviderHealth($quorate !== false, (string) ($version['version'] ?? null), $ms, ['release' => $version['release'] ?? null, 'quorate' => $quorate]);
        } catch (ProviderException $e) {
            return ProviderHealth::down($e->getMessage(), (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function vendorVersion(): ?string
    {
        $cached = $this->cache->get("onhost:pve:version:{$this->instance->id}");

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    public function clusterNodes(): array
    {
        $nodes = [];
        foreach ((array) $this->api->get('/nodes', [], 'nodes') as $n) {
            $nodes[] = ['node' => (string) $n['node'], 'status' => (string) ($n['status'] ?? 'unknown'), 'cpu' => isset($n['cpu']) ? (float) $n['cpu'] : null, 'maxcpu' => isset($n['maxcpu']) ? (int) $n['maxcpu'] : null, 'mem' => isset($n['mem']) ? (int) $n['mem'] : null, 'maxmem' => isset($n['maxmem']) ? (int) $n['maxmem'] : null, 'disk' => isset($n['disk']) ? (int) $n['disk'] : null, 'maxdisk' => isset($n['maxdisk']) ? (int) $n['maxdisk'] : null, 'uptime' => isset($n['uptime']) ? (int) $n['uptime'] : null];
        }

        return $nodes;
    }

    public function listGuests(): array
    {
        return array_values(array_map(fn ($g) => [
            'vmid' => (int) $g['vmid'], 'node' => (string) $g['node'], 'name' => (string) ($g['name'] ?? ''), 'status' => (string) ($g['status'] ?? ''),
            'cpus' => (int) ($g['maxcpu'] ?? 0), 'maxmem' => (int) ($g['maxmem'] ?? 0), 'maxdisk' => (int) ($g['maxdisk'] ?? 0), 'tags' => (string) ($g['tags'] ?? ''), 'template' => (int) ($g['template'] ?? 0),
        ], array_filter((array) $this->api->get('/cluster/resources', ['type' => 'vm'], 'resources'), fn ($g) => ($g['type'] ?? '') === 'qemu')));
    }

    public function reserveVmid(): int
    {
        return (int) $this->api->get('/cluster/nextid', [], 'nextid', true);
    }

    public function provision(ResourceSpec $spec): ProviderResult
    {
        // An earlier attempt may have created the guest already: the clone was accepted and the answer got lost, or the worker
        // died before the binding was written. Tags cannot be given to a clone, so a guest in the middle of being cloned is
        // recognised by the description the clone DID get; without that a retried step cloned a second server (H38).
        $existing = $this->findByIdempotencyTag($spec->idempotencyKey) ?? $this->findByCloneMarker($spec);
        if ($existing !== null) {
            if (($existing['lock'] ?? '') !== '') { // still being cloned: come back later, do not start another one
                throw new ProviderException('proxmox', ProviderErrorCode::TRANSIENT, "Guest {$existing['vmid']} of this service is still locked ({$existing['lock']}) by an earlier attempt");
            }

            return ProviderResult::completed(new ResourceRef('qemu', (string) $existing['vmid'], $existing['node'], ['name' => $existing['name']], $spec->serviceId), $existing, alreadyExisted: true);
        }
        $node = $spec->node ?? (string) $this->instance->option('default_node');
        $image = (string) $spec->get('image', 'debian-13');
        $templates = (array) $this->instance->option('templates', []);
        $templateVmid = (int) ($templates[$image] ?? $this->instance->option('template_vmid', 0));
        if ($templateVmid <= 0) {
            throw new ProviderException('proxmox', ProviderErrorCode::VALIDATION, "No golden template configured for image {$image}");
        }
        $vmid = (int) ($spec->get('vmid') ?: $this->reserveVmid());
        $params = array_filter([
            'newid' => $vmid,
            'name' => $this->safeName((string) $spec->get('hostname', $spec->serviceId)),
            'full' => 1,
            'target' => $node,
            'storage' => $spec->get('storage') ?? $this->instance->option('storage'),
            'pool' => $this->instance->option('pool'),
            'description' => self::cloneMarker($spec),
        ], fn ($v) => $v !== null && $v !== '');
        $templateNode = (string) ($templates[$image.'_node'] ?? $this->instance->option('template_node', $node));
        $upid = $this->api->post("/nodes/{$templateNode}/qemu/{$templateVmid}/clone", $params, 'qemu.clone', true);

        return ProviderResult::accepted(
            new AsyncHandle('pve_task', (string) $upid, $templateNode, ['vmid' => $vmid, 'target_node' => $node], 5, 1800),
            new ResourceRef('qemu', (string) $vmid, $node, ['name' => $params['name']], $spec->serviceId),
            ['vmid' => $vmid, 'node' => $node],
        );
    }

    public function applyConfig(ResourceRef $vm, array $config): ProviderResult
    {
        $allowed = ['cores', 'sockets', 'cpu', 'memory', 'balloon', 'tags', 'onboot', 'agent', 'description', 'name', 'net0', 'net1', 'scsihw', 'boot', 'protection', 'cpulimit', 'cpuunits', 'affinity', 'hugepages', 'numa'];
        $params = array_intersect_key($config, array_flip($allowed));
        $result = $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", $params, 'qemu.config');
        if (is_string($result) && str_starts_with($result, 'UPID')) {
            return ProviderResult::accepted(new AsyncHandle('pve_task', $result, $vm->node, [], 3, 600), $vm);
        }

        return ProviderResult::completed($vm, ['applied' => array_keys($params)]);
    }

    public function applyCloudInit(ResourceRef $vm, array $cloudInit): ProviderResult
    {
        $params = array_filter([
            'ciuser' => $cloudInit['user'] ?? null,
            'cipassword' => $cloudInit['password'] ?? null,
            'sshkeys' => isset($cloudInit['sshkeys']) ? rawurlencode(implode("\n", (array) $cloudInit['sshkeys'])) : null,
            'ipconfig0' => $cloudInit['ipconfig0'] ?? null,
            'ipconfig1' => $cloudInit['ipconfig1'] ?? null,
            'nameserver' => $cloudInit['nameserver'] ?? null,
            'searchdomain' => $cloudInit['searchdomain'] ?? null,
            'ciupgrade' => isset($cloudInit['upgrade']) ? (int) $cloudInit['upgrade'] : null,
        ], fn ($v) => $v !== null && $v !== '');
        $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", $params, 'qemu.cloudinit');
        $regen = $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/cloudinit", [], 'qemu.cloudinit.regenerate');
        if (is_string($regen) && str_starts_with($regen, 'UPID')) {
            return ProviderResult::accepted(new AsyncHandle('pve_task', $regen, $vm->node, [], 3, 300), $vm);
        }

        return ProviderResult::completed($vm, ['cloud_init' => array_keys($params)]);
    }

    public function resize(ResourceRef $vm, ResourceSpec $spec): ProviderResult
    {
        $this->applyConfig($vm, array_filter(['cores' => $spec->get('vcpu'), 'memory' => $spec->get('ram_mb'), 'cpulimit' => $spec->get('cpu_limit'), 'tags' => $this->tagsToWrite($vm, $spec)], fn ($v) => $v !== null));
        $diskGb = $spec->get('nvme_gb');
        if ($diskGb !== null) {
            $current = $this->getActualState($vm);
            $currentGb = (int) $current->get('disk_gb', 0);
            if ((int) $diskGb > $currentGb) {
                $delta = (int) $diskGb - $currentGb;
                $result = $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/resize", ['disk' => (string) $this->instance->option('os_disk', 'scsi0'), 'size' => "+{$delta}G"], 'qemu.resize', true);
                if (is_string($result) && str_starts_with($result, 'UPID')) {
                    return ProviderResult::accepted(new AsyncHandle('pve_task', $result, $vm->node, [], 5, 900), $vm, ['disk_delta_gb' => $delta]);
                }
            } elseif ((int) $diskGb < $currentGb) {
                throw new ProviderException('proxmox', ProviderErrorCode::VALIDATION, 'Disk shrink is not supported; create a new VM and migrate data');
            }
        }

        return ProviderResult::completed($vm, ['resized' => true]);
    }

    public function power(ResourceRef $vm, string $action): ProviderResult
    {
        $map = ['start' => 'start', 'stop' => 'stop', 'shutdown' => 'shutdown', 'reboot' => 'reboot', 'reset' => 'reset', 'kill' => 'stop'];
        if (! isset($map[$action])) {
            throw new ProviderException('proxmox', ProviderErrorCode::VALIDATION, "Unsupported power action {$action}");
        }
        $params = $action === 'shutdown' ? ['timeout' => 120, 'forceStop' => 1] : [];
        $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/{$map[$action]}", $params, "qemu.{$map[$action]}", true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['action' => $action], 3, 600), $vm);
    }

    public function getActualState(ResourceRef $vm): ActualState
    {
        try {
            $status = $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/current", [], 'qemu.status');
            $config = $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", [], 'qemu.config.get');
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                return ActualState::missing();
            }
            throw $e;
        }
        $diskSpec = (string) ($config[(string) $this->instance->option('os_disk', 'scsi0')] ?? '');
        preg_match('/size=(\d+)([MGT])/', $diskSpec, $m);
        $diskGb = isset($m[1]) ? (int) match ($m[2]) {
            'M' => intdiv((int) $m[1], 1024), 'T' => (int) $m[1] * 1024, default => (int) $m[1]
        } : (int) round(((int) ($status['maxdisk'] ?? 0)) / 1024 ** 3);

        return new ActualState(true, [
            'vcpu' => (int) ($config['cores'] ?? 0) * (int) ($config['sockets'] ?? 1),
            'ram_mb' => (int) ($config['memory'] ?? 0),
            'disk_gb' => $diskGb,
            'name' => $config['name'] ?? null,
            'tags' => $config['tags'] ?? '',
            'onboot' => (int) ($config['onboot'] ?? 0),
            'agent' => $config['agent'] ?? null,
            'protection' => (int) ($config['protection'] ?? 0),
            'uptime' => (int) ($status['uptime'] ?? 0),
            'status' => (string) ($status['status'] ?? 'unknown'),
            'ha' => $status['ha'] ?? null,
        ], (string) ($status['status'] ?? 'unknown'), now()->toISOString());
    }

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan
    {
        if (! $actual->exists) {
            return new ActionPlan([ActionPlan::drift('existence', 'present', 'missing', 'ONHOST_MANAGED', 'SECURITY_SUSPICIOUS')]);
        }
        $drifts = [];
        foreach ([['vcpu', 'vcpu', 'AUTO_REPAIRABLE'], ['ram_mb', 'ram_mb', 'AUTO_REPAIRABLE'], ['nvme_gb', 'disk_gb', 'REQUIRES_APPROVAL']] as [$want, $have, $class]) {
            $expected = $spec->get($want);
            if ($expected !== null && (int) $expected !== (int) $actual->get($have)) {
                $drifts[] = ActionPlan::drift($want, (int) $expected, (int) $actual->get($have), 'ONHOST_MANAGED', $class);
            }
        }
        // the tags that say whose guest this is; the idempotency tag belongs to the operation that created it, not to the service
        $expectedTags = ['onhost', self::serviceTag($spec->serviceId)];
        $actualTags = array_filter(explode(';', (string) $actual->get('tags', '')));
        if (array_diff($expectedTags, $actualTags) !== []) {
            $drifts[] = ActionPlan::drift('tags', $expectedTags, $actualTags, 'ONHOST_MANAGED', 'AUTO_REPAIRABLE');
        }
        $desiredStatus = $spec->get('power_state');
        if ($desiredStatus !== null && $desiredStatus !== $actual->status) {
            $drifts[] = ActionPlan::drift('power_state', $desiredStatus, $actual->status, 'CUSTOMER_MUTABLE', 'EXPECTED');
        }

        return new ActionPlan($drifts);
    }

    public function suspend(ResourceRef $vm): ProviderResult
    {
        $state = $this->getActualState($vm);
        $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", ['onboot' => 0, 'protection' => 1], 'qemu.suspend.config');
        if ($state->status === 'running') {
            $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/shutdown", ['timeout' => 120, 'forceStop' => 1], 'qemu.suspend.shutdown', true);

            return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, [], 5, 600), $vm);
        }

        return ProviderResult::completed($vm, ['suspended' => true]);
    }

    public function resume(ResourceRef $vm): ProviderResult
    {
        $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", ['onboot' => 1, 'protection' => 0], 'qemu.resume.config');
        if (($vm->meta['start'] ?? true) === false) { // it was off before the suspension (the customer's own shutdown): the lock is lifted, the machine stays as its owner left it
            return ProviderResult::completed($vm, ['resumed' => true, 'started' => false]);
        }
        $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/start", [], 'qemu.resume.start', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, [], 5, 600), $vm);
    }

    public function terminate(ResourceRef $vm): ProviderResult
    {
        $state = $this->getActualState($vm);
        if (! $state->exists) {
            return ProviderResult::completed(null, ['already_deleted' => true], alreadyExisted: true);
        }
        if ($state->status === 'running') {
            $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/stop", [], 'qemu.terminate.stop', true);
        }
        $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", ['protection' => 0], 'qemu.terminate.unprotect');
        $upid = $this->api->delete("/nodes/{$vm->node}/qemu/{$vm->remoteId}", ['purge' => 1, 'destroy-unreferenced-disks' => 1], 'qemu.destroy', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, [], 5, 900), null);
    }

    public function usage(ResourceRef $vm, ?string $periodStart = null, ?string $periodEnd = null): Usage
    {
        $status = $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/status/current", [], 'qemu.usage');

        return new Usage([
            'cpu_pct' => round(((float) ($status['cpu'] ?? 0)) * 100, 2),
            'mem_bytes' => (int) ($status['mem'] ?? 0),
            'mem_max_bytes' => (int) ($status['maxmem'] ?? 0),
            'disk_max_bytes' => (int) ($status['maxdisk'] ?? 0),
            'net_in_bytes' => (int) ($status['netin'] ?? 0),
            'net_out_bytes' => (int) ($status['netout'] ?? 0),
            'disk_read_bytes' => (int) ($status['diskread'] ?? 0),
            'disk_write_bytes' => (int) ($status['diskwrite'] ?? 0),
            'uptime_s' => (int) ($status['uptime'] ?? 0),
        ], now()->toISOString());
    }

    public function snapshot(ResourceRef $vm, string $name, ?string $description = null): ProviderResult
    {
        $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/snapshot", array_filter(['snapname' => $this->safeName($name), 'description' => $description, 'vmstate' => 0]), 'qemu.snapshot', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['snapshot' => $name], 5, 900), $vm);
    }

    public function rollback(ResourceRef $vm, string $name): ProviderResult
    {
        $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/snapshot/{$this->safeName($name)}/rollback", ['start' => 1], 'qemu.rollback', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['snapshot' => $name], 5, 900), $vm);
    }

    public function deleteSnapshot(ResourceRef $vm, string $name): ProviderResult
    {
        $upid = $this->api->delete("/nodes/{$vm->node}/qemu/{$vm->remoteId}/snapshot/{$this->safeName($name)}", [], 'qemu.snapshot.delete', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, [], 5, 900), $vm);
    }

    public function listSnapshots(ResourceRef $vm): array
    {
        $out = [];
        foreach ((array) $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/snapshot", [], 'qemu.snapshot.list') as $s) {
            if (($s['name'] ?? '') === 'current') {
                continue;
            }
            $out[] = ['name' => (string) $s['name'], 'description' => $s['description'] ?? null, 'created_at' => isset($s['snaptime']) ? date('c', (int) $s['snaptime']) : null, 'parent' => $s['parent'] ?? null];
        }

        return $out;
    }

    /**
     * Replace the VM's firewall policy — as a whole, and never through a moment worse than both.
     *
     * It used to delete every rule and then add the new ones, with the firewall already on and `policy_in: DROP`: for
     * as long as the adds took, the VM dropped everything, and when one of them was refused (a bad port expression, a
     * panel that stopped answering) the VM stayed like that — a partial rule set under DROP, the customer's own SSH gone.
     * And each rule was added WITHOUT a position, which Proxmox puts at the top: the customer's list came out upside
     * down, and on a firewall the first match wins ("accept my office, drop everyone else on 22" became the reverse).
     *
     * Now: make before break (H503). The new rules go in first, each at its own explicit position, on top of the old
     * ones; only then are the old ones removed, from the bottom; only then are the options switched. When anything fails
     * the previous policy is put back the same way — rules and options — and the error says so.
     */
    public function applyFirewall(ResourceRef $vm, array $rules, bool $enabled = true): ProviderResult
    {
        $base = "/nodes/{$vm->node}/qemu/{$vm->remoteId}/firewall";
        $before = array_values((array) $this->api->get("{$base}/rules", [], 'fw.rules.get'));
        $options = (array) $this->api->get("{$base}/options", [], 'fw.options.get');
        $wanted = array_map(fn (array $r) => array_filter([
            'action' => strtoupper((string) $r['action']), 'type' => strtolower((string) $r['type']), 'proto' => $r['proto'] ?? null, 'dport' => $r['dport'] ?? null,
            'source' => $r['source'] ?? null, 'enable' => (int) ($r['enable'] ?? true), 'comment' => $r['comment'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''), array_values($rules));
        try {
            $this->replaceRules($base, $wanted);
            $this->api->put("{$base}/options", ['enable' => (int) $enabled, 'policy_in' => 'DROP', 'policy_out' => 'ACCEPT'], 'fw.options');
        } catch (Throwable $e) {
            $restored = true;
            try {
                $this->replaceRules($base, array_map(fn (array $r) => self::ruleParams($r), $before));
                $this->api->put("{$base}/options", array_filter(['enable' => (int) ($options['enable'] ?? 0), 'policy_in' => $options['policy_in'] ?? null, 'policy_out' => $options['policy_out'] ?? null], fn ($v) => $v !== null), 'fw.options');
            } catch (Throwable) {
                $restored = false;
            }
            throw new ProviderException('proxmox', $e instanceof ProviderException ? $e->errorCode : ProviderErrorCode::UNKNOWN,
                'The firewall change was refused ('.mb_substr($e->getMessage(), 0, 120).'); '.($restored
                    ? 'the previous rules were put back.'
                    : 'the previous rules could not be put back either — the server console still works.'));
        }

        return ProviderResult::completed($vm, ['rules' => count($rules), 'enabled' => $enabled]);
    }

    /**
     * Make before break: the new rules are inserted on top while the old ones still stand below them; then the old ones
     * are removed from the bottom so the positions stay true.
     *
     * They are inserted LAST FIRST, each at position 0. A rule created without a position goes to the top, and whether
     * the create call honours `pos` at all is not something to bet a customer's SSH on — last-first at the top gives
     * the customer's order either way.
     *
     * @param  list<array<string,mixed>>  $rules
     */
    private function replaceRules(string $base, array $rules): void
    {
        $present = count((array) $this->api->get("{$base}/rules", [], 'fw.rules.get'));
        foreach (array_reverse($rules) as $rule) {
            $this->api->post("{$base}/rules", $rule + ['pos' => 0], 'fw.rule.add');
        }
        for ($pos = count($rules) + $present - 1; $pos >= count($rules); $pos--) {
            $this->api->delete("{$base}/rules/{$pos}", [], 'fw.rule.delete');
        }
    }

    /** A rule as Proxmox lists it, back into the fields it is created with. @param array<string,mixed> $rule @return array<string,mixed> */
    private static function ruleParams(array $rule): array
    {
        return array_filter(array_intersect_key($rule, array_flip(['type', 'action', 'proto', 'dport', 'sport', 'source', 'dest', 'enable', 'comment', 'macro', 'iface', 'log'])),
            fn ($v) => $v !== null && $v !== '');
    }

    /** `qm migrate` through the API: local disks travel with the VM; the task runs on the source node. */
    public function migrate(ResourceRef $vm, string $targetNode, bool $online = true): ProviderResult
    {
        if ($targetNode === '' || $targetNode === $vm->node) {
            throw new ProviderException('proxmox', ProviderErrorCode::VALIDATION, 'The migration target must be another node of the cluster');
        }
        $upid = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/migrate", ['target' => $targetNode, 'online' => $online ? 1 : 0, 'with-local-disks' => 1], 'qemu.migrate', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['target_node' => $targetNode, 'online' => $online], 5, 3600), $vm, ['target_node' => $targetNode, 'online' => $online]);
    }

    public function guestAgentPing(ResourceRef $vm): bool
    {
        try {
            $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/agent/ping", [], 'agent.ping');

            return true;
        } catch (ProviderException) {
            return false;
        }
    }

    /**
     * The ISO images of the rescue storage (`iso_storage`, `local` by default). Only what the operator put there can
     * ever be booted: the customer picks from this list, never from a path of their own (H233).
     */
    public function listIsoImages(string $node): array
    {
        $storage = (string) $this->instance->option('iso_storage', 'local');
        $out = [];
        foreach ((array) $this->api->get("/nodes/{$node}/storage/{$storage}/content", ['content' => 'iso'], 'storage.content.iso') as $row) {
            $volume = (string) ($row['volid'] ?? '');
            if ($volume === '') {
                continue;
            }
            $out[] = ['volume' => $volume, 'name' => basename(str_replace('\\', '/', $volume)), 'size_bytes' => (int) ($row['size'] ?? 0)];
        }
        usort($out, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    public function bootMedia(ResourceRef $vm): array
    {
        $config = (array) $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", [], 'qemu.config.get');
        $drive = (string) ($config[self::RESCUE_DRIVE] ?? '');
        $volume = $drive === '' || str_starts_with($drive, 'none') ? null : explode(',', $drive)[0];

        return ['iso' => $volume, 'boot' => (string) ($config['boot'] ?? '')];
    }

    public function setBootMedia(ResourceRef $vm, ?string $volume, ?string $bootOrder = null): ProviderResult
    {
        $params = [self::RESCUE_DRIVE => $volume === null ? 'none,media=cdrom' : $volume.',media=cdrom'];
        if ($bootOrder !== null) {
            $params['boot'] = $bootOrder;
        }
        $result = $this->api->put("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", $params, 'qemu.config.boot');
        if (is_string($result) && str_starts_with($result, 'UPID')) {
            return ProviderResult::accepted(new AsyncHandle('pve_task', $result, $vm->node, [], 3, 600), $vm);
        }

        return ProviderResult::completed($vm, ['iso' => $volume, 'boot' => $bootOrder]);
    }

    /** What this cluster really answers (SelfProbing): a backup storage is configured and its content can be listed — every backup and final snapshot is found there. */
    public function probes(?ResourceRef $anyResource = null): array
    {
        $storage = (string) $this->instance->option('backup_storage', '');
        if ($storage === '') {
            return ['backup_storage' => 'missing: the instance option backup_storage is not set; backups and final snapshots have nowhere to go'];
        }
        $node = (string) ($anyResource?->node ?: $this->instance->option('default_node', ''));
        if ($node === '') {
            return ['backup_storage' => 'skipped: no node known to ask'];
        }

        return ['backup_storage' => $this->probe(fn () => $this->api->get("/nodes/{$node}/storage/{$storage}/content", ['content' => 'backup'], 'backup.list'))];
    }

    /** One read-only probe: `ok`, or what the panel said. */
    private function probe(callable $ask): string
    {
        try {
            $ask();

            return 'ok';
        } catch (ProviderException $e) {
            return (in_array($e->errorCode, [ProviderErrorCode::AUTH, ProviderErrorCode::VALIDATION], true) ? 'refused: ' : 'missing: ').mb_substr($e->getMessage(), 0, 160);
        }
    }

    public function backup(ResourceRef $vm, array $policy): ProviderResult
    {
        $upid = $this->api->post("/nodes/{$vm->node}/vzdump", array_filter([
            'vmid' => $vm->remoteId,
            'storage' => $policy['storage'] ?? $this->instance->option('backup_storage'),
            'mode' => $policy['mode'] ?? 'snapshot',
            'notes-template' => $policy['notes'] ?? '{{guestname}} {{node}} {{vmid}} onhost',
            'protected' => isset($policy['protected']) ? (int) $policy['protected'] : null,
            'remove' => 0,
        ], fn ($v) => $v !== null), 'vzdump', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['storage' => $policy['storage'] ?? $this->instance->option('backup_storage')], 15, 4 * 3600), $vm);
    }

    public function listBackups(ResourceRef $vm): array
    {
        $storage = (string) $this->instance->option('backup_storage');
        $out = [];
        foreach ((array) $this->api->get("/nodes/{$vm->node}/storage/{$storage}/content", ['content' => 'backup', 'vmid' => $vm->remoteId], 'backup.list') as $b) {
            $out[] = [
                'remote_id' => (string) $b['volid'],
                'created_at' => date('c', (int) ($b['ctime'] ?? 0)),
                'size_bytes' => isset($b['size']) ? (int) $b['size'] : null,
                'verified' => isset($b['verification']) ? (($b['verification']['state'] ?? '') === 'ok') : null,
                'protected' => isset($b['protected']) ? (bool) $b['protected'] : null,
                'meta' => ['notes' => $b['notes'] ?? null, 'format' => $b['format'] ?? null],
            ];
        }

        return $out;
    }

    public function restore(ResourceRef $vm, string $backupRemoteId, array $options = []): ProviderResult
    {
        $target = (int) ($options['vmid'] ?? $vm->remoteId);
        $inPlace = $target === (int) $vm->remoteId;
        if ($inPlace) {
            $state = $this->getActualState($vm);
            if ($state->status === 'running') {
                throw new ProviderException('proxmox', ProviderErrorCode::CONFLICT, 'In-place restore requires the VM to be stopped');
            }
        }
        $upid = $this->api->post("/nodes/{$vm->node}/qemu", array_filter([
            'vmid' => $target, 'archive' => $backupRemoteId, 'storage' => $options['storage'] ?? $this->instance->option('storage'),
            'force' => $inPlace ? 1 : null, 'unique' => $inPlace ? null : 1, 'start' => (int) ($options['start'] ?? 0),
        ], fn ($v) => $v !== null), 'qmrestore', true);

        return ProviderResult::accepted(new AsyncHandle('pve_task', (string) $upid, $vm->node, ['vmid' => $target, 'in_place' => $inPlace], 15, 4 * 3600), new ResourceRef('qemu', (string) $target, $vm->node, [], $vm->serviceId));
    }

    public function consoleAccess(ResourceRef $vm): array
    {
        $proxy = $this->api->post("/nodes/{$vm->node}/qemu/{$vm->remoteId}/vncproxy", ['websocket' => 1], 'vncproxy', true);
        $ttl = (int) config('onhost.provisioning.console_token_ttl_seconds', 120);
        $token = 'con_'.strtolower((string) Str::ulid());
        // The browser never sees the PVE ticket or the API token: the console relay resolves this ONhost token
        // server-side and opens the vncwebsocket with the Authorization header (blueprint §10.1).
        $this->cache->put("onhost:console:{$token}", [
            'kind' => 'pve_vnc', 'upstream' => $this->api->baseUrl()."/api2/json/nodes/{$vm->node}/qemu/{$vm->remoteId}/vncwebsocket",
            'port' => (int) $proxy['port'], 'vncticket' => (string) $proxy['ticket'], 'instance' => $this->instance->id, 'service_id' => $vm->serviceId,
        ], $ttl);

        return ['kind' => 'novnc', 'url' => rtrim((string) config('onhost.portal_url'), '/').'/console/ws/'.$token, 'token' => $token, 'expires_at' => now()->addSeconds($ttl)->toISOString(), 'meta' => ['node' => $vm->node]];
    }

    public function awaitStatus(AsyncHandle $handle): AsyncStatus
    {
        $node = $handle->node ?? (string) $this->instance->option('default_node');
        try {
            $status = $this->api->get("/nodes/{$node}/tasks/".rawurlencode($handle->handle).'/status', [], 'task.status');
        } catch (ProviderException $e) {
            return $e->errorCode === ProviderErrorCode::NOT_FOUND ? AsyncStatus::unknown('task not found: '.$handle->handle) : throw $e;
        }
        if (($status['status'] ?? '') === 'running') {
            return AsyncStatus::running(null, ['upid' => $handle->handle]);
        }
        $exit = (string) ($status['exitstatus'] ?? '');

        return $exit === 'OK'
            ? AsyncStatus::succeeded(['upid' => $handle->handle, 'exitstatus' => $exit, 'meta' => $handle->meta])
            : AsyncStatus::failed("task {$handle->handle} finished with: {$exit}", ['exitstatus' => $exit]);
    }

    /** @return array{vmid:int,node:string,name:string}|null */
    private function findByIdempotencyTag(string $idempotencyKey): ?array
    {
        $tag = self::idempotencyTag($idempotencyKey);
        foreach ($this->listGuests() as $guest) {
            if (in_array($tag, array_filter(explode(';', $guest['tags'])), true)) {
                return ['vmid' => $guest['vmid'], 'node' => $guest['node'], 'name' => $guest['name']];
            }
        }

        return null;
    }

    /** What a clone is told about itself — the only thing of ours a guest carries while it is still being cloned. */
    public static function cloneMarker(ResourceSpec $spec): string
    {
        return "ONhost service {$spec->serviceId} [".self::idempotencyTag($spec->idempotencyKey).']';
    }

    /** @return array{vmid:int, node:string, name:string, lock:string}|null */
    private function findByCloneMarker(ResourceSpec $spec): ?array
    {
        $name = $this->safeName((string) $spec->get('hostname', $spec->serviceId));
        $needle = "ONhost service {$spec->serviceId}";
        foreach ($this->listGuests() as $guest) {
            if ($guest['template'] === 1 || $guest['name'] !== $name) {
                continue;
            }
            try {
                $config = (array) $this->api->get("/nodes/{$guest['node']}/qemu/{$guest['vmid']}/config", [], 'qemu.config.get');
            } catch (ProviderException $e) {
                if ($e->errorCode === ProviderErrorCode::NOT_FOUND) {
                    continue;
                }
                throw $e;
            }
            $description = rawurldecode((string) ($config['description'] ?? ''));
            if (str_starts_with($description, $needle) && (strlen($description) === strlen($needle) || $description[strlen($needle)] === ' ')) {
                return ['vmid' => $guest['vmid'], 'node' => $guest['node'], 'name' => $guest['name'], 'lock' => (string) ($config['lock'] ?? '')];
            }
        }

        return null;
    }

    /** Tags are added to what the guest has, never taken away: `onhost`, the service, and the idempotency tag of the first writer. */
    private function tagsToWrite(ResourceRef $vm, ResourceSpec $spec): ?string
    {
        try {
            $current = array_values(array_filter(explode(';', (string) (((array) $this->api->get("/nodes/{$vm->node}/qemu/{$vm->remoteId}/config", [], 'qemu.config.get'))['tags'] ?? ''))));
        } catch (ProviderException) {
            return null; // the size is what this call is about; the reconciler reports missing tags
        }
        $wanted = ['onhost', self::serviceTag($spec->serviceId)];
        if (array_filter($current, fn (string $tag) => str_starts_with($tag, 'idem-')) === []) {
            $wanted[] = self::idempotencyTag($spec->idempotencyKey);
        }
        $merged = array_values(array_unique(array_merge($current, $wanted)));

        return $merged === $current ? null : implode(';', $merged);
    }

    /** @return list<string> */
    public function tags(ResourceSpec $spec): array
    {
        return ['onhost', self::serviceTag($spec->serviceId), self::idempotencyTag($spec->idempotencyKey)];
    }

    public static function serviceTag(string $serviceId): string
    {
        return strtolower(str_replace('_', '-', $serviceId));
    }

    public static function idempotencyTag(string $key): string
    {
        return 'idem-'.substr(hash('sha256', $key), 0, 12);
    }

    private function safeName(string $name): string
    {
        $safe = strtolower(preg_replace('/[^A-Za-z0-9.-]+/', '-', $name) ?? 'vm');
        $safe = trim($safe, '-.');

        return $safe === '' ? 'vm' : substr($safe, 0, 63);
    }
}
