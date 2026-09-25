<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Billing\ServiceReinstatement;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ReinstateServiceCommandHandler implements CommandHandler
{
    public function __construct(private readonly ServiceReinstatement $reinstatement) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ReinstateServiceCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        if ($context->actorType === 'ai') {
            throw new DomainError('ai_action_forbidden', 'An assistant may not spend the credit to restore a service.', 403);
        }
        $service = Service::query()->where('organization_id', $command->organizationId)->whereKey((string) $command->get('service_id'))->first();
        if ($service === null) {
            throw DomainError::notFound('service');
        }

        return $this->reinstatement->reinstate($service, $context, $command->idempotencyKey());
    }
}
