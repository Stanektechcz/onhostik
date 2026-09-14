<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Workflows\Steps\ConfirmRenewalStep;
use Onhost\Domain\Domains\Workflows\Steps\SubmitRenewalStep;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\Workflow;

/** Renewal saga (blueprint §46.3). Money is held before the operation starts and captured only after the registry confirms. */
final class RenewDomainWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'domain.renew';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-registrar';
    }

    public function steps(Operation $operation): array
    {
        return [new SubmitRenewalStep, new ConfirmRenewalStep];
    }

    public function compensate(StepContext $context): void
    {
        $domain = Domain::query()->find($context->operation->domain_id ?? $context->desired('domain_id'));
        if ($domain === null) {
            return;
        }
        $context->container->make(DomainService::class)->renewalFailed($domain, $context->operation, $context->actor, (string) ($context->operation->error['message'] ?? 'renewal failed'), $context->get('renewed') === true);
    }
}
