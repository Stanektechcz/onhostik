<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\Steps\AllocateAddressesStep;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\VirtualMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Providers\Contracts\ComputeProvider;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Onhost\Providers\Contracts\PowerCapable;

/**
 * VPS/VDS/managed-database KVM saga on Proxmox (blueprint §5.3, §7): place → IPs →
 * clone golden template → size + cloud-init + firewall → start → verify → ACTIVE.
 */
final class ProvisionVpsWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'provision.vps';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-proxmox';
    }

    public function steps(Operation $operation): array
    {
        return [
            new ScheduleNodeStep('compute', 'proxmox'),
            new AllocateAddressesStep('vps'),
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Klon šablony';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $spec = $context->spec('vm', [
                        'hostname' => $context->desired('hostname', $service->hostname ?? $service->id), 'image' => $context->desired('image', 'debian-13'),
                        'tags' => ['onhost', $service->id, (string) $service->organization_id, 'sla-'.$service->sla_class],
                    ]);
                    $result = $infra->provision($spec);
                    if ($result->ref !== null) {
                        $context->bind($context->instance(), 'qemu', $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);
                    }

                    return $this->settle($result, ['vmid' => $result->ref?->remoteId, 'vm_node' => $result->ref?->node, 'already_existed' => $result->alreadyExisted]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Zdroje a disk';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $ent = (array) $service->entitlements;

                    // The Proxmox adapter reads vcpu / ram_mb / nvme_gb / cpu_limit from the spec (same keys as plan entitlements).
                    return $this->settle($infra->resize($this->ref($context, 'qemu'), $context->spec('vm', ['vcpu' => (int) ($ent['vcpu'] ?? 1), 'ram_mb' => (int) ($ent['ram_mb'] ?? 1024), 'nvme_gb' => (int) ($ent['nvme_gb'] ?? 20), 'cpu_limit' => ($ent['cpu_class'] ?? 'shared') === 'dedicated' ? null : (int) ($ent['vcpu'] ?? 1)])), ['sized' => true]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Cloud-init';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $compute = $this->capability($context, ComputeProvider::class);
                    $addresses = (array) $context->get('addresses', []);
                    $cloudInit = array_filter([
                        'user' => (string) $context->desired('admin_user', 'onhost'),
                        'sshkeys' => (array) $context->desired('ssh_keys', []),
                        'ipconfig0' => isset($addresses['ipv4']) ? sprintf('ip=%s/%d,gw=%s', $addresses['ipv4']['address'], $addresses['ipv4']['prefix'], $addresses['ipv4']['gateway']) : 'ip=dhcp',
                        'ipconfig1' => isset($addresses['ipv6']) ? sprintf('ip6=%s/%d,gw6=%s', $addresses['ipv6']['address'], $addresses['ipv6']['prefix'], $addresses['ipv6']['gateway']) : null,
                        'nameserver' => implode(' ', array_merge((array) ($addresses['ipv4']['dns'] ?? []), (array) ($addresses['ipv6']['dns'] ?? []))) ?: null,
                        'searchdomain' => (string) config('onhost.provisioning.search_domain', 'onhost.cz'),
                        'upgrade' => true,
                    ], fn ($v) => $v !== null && $v !== '' && $v !== []);
                    $result = $compute->applyCloudInit($this->ref($context, 'qemu'), $cloudInit);
                    $service->forceFill(['hostname' => $context->desired('hostname', $service->hostname)])->save();

                    return $this->settle($result, ['cloud_init' => ['user' => $cloudInit['user'], 'ipconfig0' => $cloudInit['ipconfig0'] ?? null, 'ipconfig1' => $cloudInit['ipconfig1'] ?? null, 'ssh_keys' => count($cloudInit['sshkeys'] ?? [])]]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Firewall';
                }

                public function run(StepContext $context): StepResult
                {
                    $compute = $this->capability($context, ComputeProvider::class);
                    $rules = (array) $context->desired('firewall', config('onhost.provisioning.default_firewall', []));

                    return $this->settle($compute->applyFirewall($this->ref($context, 'qemu'), $rules, (bool) $context->desired('firewall_enabled', true)), ['firewall_rules' => count($rules)]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Start';
                }

                public function run(StepContext $context): StepResult
                {
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $ref = $this->ref($context, 'qemu');
                    if ($infra->getActualState($ref)->status === 'running') {
                        return StepResult::done(['started' => true]);
                    }

                    return $this->settle($this->capability($context, PowerCapable::class)->power($ref, 'start'), ['started' => true]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Ověření a aktivace';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $infra = $this->capability($context, InfrastructureProvider::class);
                    $ref = $this->ref($context, 'qemu');
                    $state = $infra->getActualState($ref);
                    if (! $state->exists) {
                        return StepResult::fail('VM disappeared after provisioning', false);
                    }
                    if ($state->status !== 'running') {
                        return StepResult::fail("VM is {$state->status}, expected running", true, $state->attributes, 15);
                    }
                    $ent = (array) $service->entitlements;
                    $addresses = (array) $context->get('addresses', []);
                    VirtualMachine::query()->updateOrCreate(['service_id' => $service->id], [
                        'vmid' => (int) $ref->remoteId, 'node' => (string) $ref->node, 'cores' => (int) ($ent['vcpu'] ?? 1), 'cpu_class' => (string) ($ent['cpu_class'] ?? 'shared'), 'memory_mb' => (int) ($ent['ram_mb'] ?? 1024), 'disk_gb' => (int) ($ent['nvme_gb'] ?? 20),
                        'image' => (string) $context->desired('image', 'debian-13'), 'hostname' => $service->hostname, 'ipv4_address_id' => $addresses['ipv4']['id'] ?? null, 'ipv6_address_id' => $addresses['ipv6']['id'] ?? null,
                        'ssh_keys' => (array) $context->desired('ssh_keys', []), 'cloud_init' => (array) $context->get('cloud_init', []), 'firewall' => (array) $context->desired('firewall', []), 'agent' => $state->get('agent', false) === true, 'state' => 'running', 'last_status' => $state->attributes,
                    ]);
                    $context->container->make(ServiceService::class)->activate($service, $context->actor, $context->operation, ['ipv4' => $addresses['ipv4']['address'] ?? null, 'ipv6' => $addresses['ipv6']['address'] ?? null, 'vmid' => $ref->remoteId, 'node' => $ref->node]);

                    return StepResult::done(['activated' => true]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null) {
            return;
        }
        // only the machine THIS operation cloned, and only once the panel confirms it is that machine (CompensationGuard): the vmid is
        // bound when the clone is accepted — a clone that failed because the number was taken leaves the binding on a stranger's VM
        $context->container->make(CompensationGuard::class)->takeBack($context, $service, 'qemu');
        $ipam = $context->container->make(IpamService::class);
        foreach (IpAddress::query()->where('service_id', $service->id)->where('state', 'allocated')->get() as $address) {
            $ipam->release($address);
        }
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }
}
