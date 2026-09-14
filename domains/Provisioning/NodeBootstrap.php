<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Vendor node bootstrap (audit §5o-7): a node ordered from a vendor boots with cloud-init user-data that prepares the
 * host (packages, hostname, the operator's SSH key) and reports back to the platform with a one-time token; the request
 * turns `ready`, operations get the playbook line to run, and the node's tags carry what the host reported.
 */
final class NodeBootstrap
{
    public function __construct(private readonly OutboxPublisher $outbox, private readonly CapacityPlanner $planner) {}

    /** @return array{token:string, user_data:string} the token is stored hashed on the request; the user-data carries it in clear */
    public function prepare(CapacityRequest $request): array
    {
        $token = 'nb_'.Str::random(40);
        $request->forceFill(['meta' => array_merge((array) $request->meta, ['ready_token_hash' => hash('sha256', $token)])])->save();

        return ['token' => $token, 'user_data' => $this->userData($request, $token)];
    }

    public function userData(CapacityRequest $request, string $token): string
    {
        $callback = rtrim((string) (config('onhost.provisioning.node_bootstrap.callback_base') ?: config('app.url')), '/')."/v1/probes/capacity/{$request->id}/ready";
        $template = (string) config('onhost.provisioning.node_bootstrap.user_data', '');
        if (trim($template) === '') {
            $template = self::DEFAULT_USER_DATA;
        }

        return str_replace(['{callback}', '{token}', '{request}', '{role}', '{region}', '{ssh_key}'], [$callback, $token, $request->id, $request->role, (string) $request->region_code, (string) config('onhost.provisioning.node_bootstrap.ssh_key', '')], $template);
    }

    /**
     * The host called back: the token must match, the request must be ordered.
     *
     * @param  array<string,mixed>  $facts
     * @return array{request:CapacityRequest, activate_token:string} the activation token the playbook posts when the hypervisor is installed (§5p-7)
     */
    public function ready(CapacityRequest $request, string $token, array $facts): array
    {
        $hash = (string) data_get($request->meta, 'ready_token_hash', '');
        if ($hash === '' || ! hash_equals($hash, hash('sha256', $token))) {
            throw DomainError::notFound('capacity_request'); // never confirm which part was wrong
        }
        if ($request->state !== CapacityRequest::ORDERED) {
            throw new DomainError('capacity_request_not_ordered', 'The request is not waiting for a node.', 409, ['state' => $request->state]);
        }
        $report = array_filter(['hostname' => isset($facts['hostname']) ? mb_substr((string) $facts['hostname'], 0, 120) : null, 'ip' => isset($facts['ip']) ? mb_substr((string) $facts['ip'], 0, 45) : null, 'os' => isset($facts['os']) ? mb_substr((string) $facts['os'], 0, 120) : null, 'cpu_cores' => isset($facts['cpu_cores']) ? (int) $facts['cpu_cores'] : null, 'ram_mb' => isset($facts['ram_mb']) ? (int) $facts['ram_mb'] : null, 'disk_gb' => isset($facts['disk_gb']) ? (int) $facts['disk_gb'] : null], fn ($v) => $v !== null);
        $activate = 'na_'.Str::random(40);
        $request->forceFill(['ready_at' => now(), 'meta' => array_merge(array_diff_key((array) $request->meta, ['ready_token_hash' => true]), ['ready' => $report, 'activate_token_hash' => hash('sha256', $activate)])])->save();
        $node = $request->node_id !== null ? Node::query()->find($request->node_id) : null;
        $node?->forceFill(['tags' => array_merge((array) ($node->tags ?? []), ['bootstrap' => $report + ['ready_at' => now()->toIso8601String()]]), 'last_seen_at' => now()])->save();
        $name = $node?->name ?: $request->node_name ?: $request->id;
        $this->outbox->publish(GenericEvent::of('capacity.request.ready', 'capacity_request', $request->id, CapacityPlanner::present($request) + ['report' => $report, 'playbook' => "ansible-playbook -i inventory.ini site.yml --limit {$name} -e onhost_activate_url=".$this->activateUrl($request).' -e onhost_activate_token=<token from the readiness answer>']));

        return ['request' => $request, 'activate_token' => $activate];
    }

    public function activateUrl(CapacityRequest $request): string
    {
        return rtrim((string) (config('onhost.provisioning.node_bootstrap.callback_base') ?: config('app.url')), '/')."/v1/probes/capacity/{$request->id}/activate";
    }

    /**
     * The playbook finished (§5p-7): the node goes active on its own, the request is delivered, operations hear it.
     *
     * @param  array<string,mixed>  $facts
     */
    public function activate(CapacityRequest $request, string $token, array $facts): CapacityRequest
    {
        $hash = (string) data_get($request->meta, 'activate_token_hash', '');
        if ($hash === '' || ! hash_equals($hash, hash('sha256', $token))) {
            throw DomainError::notFound('capacity_request');
        }
        if ($request->state !== CapacityRequest::ORDERED || $request->node_id === null) {
            throw new DomainError('capacity_request_not_ordered', 'The request is not waiting for a node.', 409, ['state' => $request->state]);
        }
        $node = Node::query()->find($request->node_id) ?? throw DomainError::notFound('node');
        $report = array_filter(['hostname' => isset($facts['hostname']) ? mb_substr((string) $facts['hostname'], 0, 120) : null, 'ip' => isset($facts['ip']) ? mb_substr((string) $facts['ip'], 0, 45) : null, 'os' => isset($facts['os']) ? mb_substr((string) $facts['os'], 0, 120) : null, 'cpu_cores' => isset($facts['cpu_cores']) ? (int) $facts['cpu_cores'] : null, 'ram_mb' => isset($facts['ram_mb']) ? (int) $facts['ram_mb'] : null], fn ($v) => $v !== null);
        $capacity = (array) ($node->capacity ?? []);
        if (! empty($report['ram_mb'])) { // the installed host knows its real size better than the order did
            $capacity['ram_mb'] = (int) $report['ram_mb'];
        }
        if (! empty($report['cpu_cores'])) {
            $capacity['cpu_cores'] = (int) $report['cpu_cores'];
        }
        $node->forceFill(['state' => 'active', 'last_seen_at' => now(), 'capacity' => $capacity, 'tags' => array_merge((array) ($node->tags ?? []), ['bootstrap' => array_merge((array) data_get($node->tags, 'bootstrap', []), $report, ['activated_at' => now()->toIso8601String()])])])->save();
        $request->forceFill(['activated_at' => now(), 'meta' => array_merge(array_diff_key((array) $request->meta, ['activate_token_hash' => true]), ['activated' => $report])])->save();
        $this->planner->track($request->refresh());
        $this->outbox->publish(GenericEvent::of('capacity.request.activated', 'capacity_request', $request->id, CapacityPlanner::present($request->refresh()) + ['report' => $report]));

        return $request;
    }

    public const DEFAULT_USER_DATA = <<<'YAML'
#cloud-config
# ONhost vendor node bootstrap (audit §5o-7): base packages, the operator's key, then the readiness call-back.
package_update: true
packages: [curl, ca-certificates, python3, sudo]
ssh_authorized_keys:
  - "{ssh_key}"
runcmd:
  - [sh, -c, "hostnamectl set-hostname $(hostname) || true"]
  - [sh, -c, "curl -fsS -X POST '{callback}' -H 'Content-Type: application/json' -d \"{\\\"token\\\":\\\"{token}\\\",\\\"hostname\\\":\\\"$(hostname)\\\",\\\"ip\\\":\\\"$(hostname -I | awk '{print $1}')\\\",\\\"os\\\":\\\"$(. /etc/os-release; echo $PRETTY_NAME)\\\",\\\"cpu_cores\\\":$(nproc),\\\"ram_mb\\\":$(free -m | awk '/Mem:/{print $2}'),\\\"disk_gb\\\":$(df -BG --output=size / | tail -1 | tr -dc 0-9)}\" || true"]
YAML;
}
