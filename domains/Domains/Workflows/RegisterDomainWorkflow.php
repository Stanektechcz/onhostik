<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Workflows\Steps\ActivateDomainStep;
use Onhost\Domain\Domains\Workflows\Steps\EnsureDnsZoneStep;
use Onhost\Domain\Domains\Workflows\Steps\EnsureNssetStep;
use Onhost\Domain\Domains\Workflows\Steps\EnsureRegistrarContactsStep;
use Onhost\Domain\Domains\Workflows\Steps\SubmitRegistrationStep;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\Workflow;

/** Registration saga (blueprint §46.2): contacts → NSSET → DNS zone → domain-create → activate. */
final class RegisterDomainWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'domain.register';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-registrar';
    }

    public function steps(Operation $operation): array
    {
        return [new EnsureRegistrarContactsStep, new EnsureNssetStep, new EnsureDnsZoneStep, new SubmitRegistrationStep, new ActivateDomainStep];
    }

    public function compensate(StepContext $context): void
    {
        $domain = Domain::query()->find($context->operation->domain_id ?? $context->desired('domain_id'));
        if ($domain === null) {
            return;
        }
        if ($context->get('registered') !== true) {
            if ($context->get('dns_zone_created') === true && $domain->dns_zone_id !== null) {
                $zone = DnsZone::query()->find($domain->dns_zone_id);
                if ($zone !== null) {
                    try {
                        $context->container->make(DnsService::class)->deleteZone($zone, $context->actor, 'registration failed');
                    } catch (\Throwable) {
                        // the reconciler removes orphan zones; compensation must not throw
                    }
                }
                $domain->forceFill(['dns_zone_id' => null]);
            }
            $context->container->make(DomainService::class)->fail($domain, $context->actor, (string) ($context->operation->error['message'] ?? 'registration failed'), $context->operation);
        }
    }
}
