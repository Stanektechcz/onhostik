<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows\Steps;

use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Models\IpAddress;
use Onhost\Domain\Provisioning\Models\IpPool;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflows\ServiceStep;
use Onhost\Platform\Errors\DomainError;

/** IPv6 is always allocated; IPv4 only when entitled (scarce resource, blueprint §5.5). */
final class AllocateAddressesStep extends ServiceStep
{
    public function __construct(private readonly string $purpose = 'vps') {}

    public function label(): string
    {
        return 'Přidělení IP adres';
    }

    public function run(StepContext $context): StepResult
    {
        $service = $this->service($context);
        $ipam = $context->container->make(IpamService::class);
        $region = (string) ($service->region_code ?? $context->get('region'));
        $out = [];
        try {
            $v6 = $ipam->allocate(6, $region, $this->purpose, $service->id, $service->organization_id);
            $out['ipv6'] = $this->describe($v6);
            $ent = (array) $service->entitlements;
            $wantsV4 = ! empty($ent['ipv4']) && $ent['ipv4'] !== 'addon';
            if ($wantsV4) {
                $v4 = $ipam->allocate(4, $region, $this->purpose, $service->id, $service->organization_id);
                $out['ipv4'] = $this->describe($v4);
            }
        } catch (DomainError $e) {
            return StepResult::fail($e->getMessage(), true, $e->extra, 1800);
        }
        $hostname = (string) $context->desired('hostname', '');
        if ($hostname !== '') {
            foreach (['ipv4', 'ipv6'] as $family) {
                if (isset($out[$family])) {
                    $ipam->setReverseDns(IpAddress::query()->findOrFail($out[$family]['id']), $hostname);
                }
            }
        }

        return StepResult::done(['addresses' => $out]);
    }

    /** IPv6 pools hand out /64 subnets; the guest gets the first address of its subnet (::1). */
    private function describe(IpAddress $address): array
    {
        $pool = IpPool::query()->find($address->pool_id);
        [$ip] = explode('/', $address->address, 2);
        if ((int) $address->family === 6 && str_ends_with($ip, '::')) {
            $ip .= '1';
        }

        return ['id' => $address->id, 'address' => $ip, 'subnet' => $address->address, 'prefix' => (int) ($address->prefix_length ?? ($address->family === 4 ? 32 : 64)), 'gateway' => $pool?->gateway, 'dns' => (array) ($pool?->dns ?? []), 'vlan' => $pool?->vlan];
    }
}
