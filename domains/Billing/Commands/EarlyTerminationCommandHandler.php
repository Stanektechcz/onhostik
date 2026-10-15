<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Billing\EarlyTerminationPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class EarlyTerminationCommandHandler implements CommandHandler
{
    public function __construct(private readonly EarlyTerminationPolicy $policy) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof EarlyTerminationCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $service = Service::query()->where('organization_id', $command->organizationId)->whereKey((string) $command->get('service_id'))->first() ?? throw DomainError::notFound('service');

        return $this->policy->settle($service, $context);
    }
}
