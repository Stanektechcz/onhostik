<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;

/** Creates the canonical zone before registration so the domain resolves the moment the registry delegates it. */
final class EnsureDnsZoneStep extends DomainStep
{
    public function label(): string
    {
        return 'DNS zóna';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        if ($domain->dns_provider !== 'powerdns' || $context->desired('nameservers') !== null) {
            return StepResult::skip();
        }
        $existed = DnsZone::query()->where('name', $domain->fqdn_ascii)->exists();
        $zone = $context->container->make(DnsService::class)->ensureZone(
            $domain->organization_id, $domain->fqdn_ascii, $context->actor, $domain->id,
            (string) $context->desired('dns_template', 'parking'), (array) ($context->desired('dns_vars') ?? []),
        );
        $domain->forceFill(['dns_zone_id' => $zone->id, 'nameservers' => $zone->nameservers])->save();

        return StepResult::done(['dns_zone_id' => $zone->id, 'dns_zone_created' => ! $existed]);
    }
}
