<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;

/** Reads the registry truth back, activates the domain, creates the renewal subscription and emits domain.registered. */
final class ActivateDomainStep extends DomainStep
{
    public function label(): string
    {
        return 'Aktivace domény';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $info = $this->adapter($context)->domainInfo($domain->fqdn_ascii);
        $service = $context->container->make(DomainService::class);
        $domain = $service->applyRegistryInfo($domain, $info);
        if ($domain->state !== DomainStateMachine::ACTIVE) {
            return StepResult::fail("Registry reports {$domain->state} for {$domain->fqdn_ascii}", $domain->state === DomainStateMachine::PENDING_REGISTRY, ['status' => $info['status']]);
        }
        $service->activate($domain, $context->actor, (string) $context->desired('event', 'domain.registered'), $context->operation);

        return StepResult::done(['expires_at' => $domain->expires_at?->toIso8601String()]);
    }
}
