<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Workflows\Steps\ActivateDomainStep;
use Onhost\Domain\Domains\Workflows\Steps\EnsureNssetStep;
use Onhost\Domain\Domains\Workflows\Steps\EnsureRegistrarContactsStep;
use Onhost\Domain\Domains\Workflows\Steps\SubmitTransferStep;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\Workflow;

/** Inbound transfer saga (blueprint §46.5). */
final class TransferDomainInWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'domain.transfer_in';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-registrar';
    }

    public function steps(Operation $operation): array
    {
        return [new EnsureRegistrarContactsStep, new EnsureNssetStep, new SubmitTransferStep, new ActivateDomainStep];
    }

    public function compensate(StepContext $context): void
    {
        $domain = Domain::query()->find($context->operation->domain_id ?? $context->desired('domain_id'));
        if ($domain !== null && $context->get('transferred') !== true) {
            $context->container->make(DomainService::class)->fail($domain, $context->actor, (string) ($context->operation->error['message'] ?? 'transfer failed'), $context->operation);
        }
    }
}
