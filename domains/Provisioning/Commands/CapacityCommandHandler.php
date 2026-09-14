<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Commands;

use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class CapacityCommandHandler implements CommandHandler
{
    public function __construct(private readonly CapacityPlanner $planner) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        assert($command instanceof CapacityCommand);
        $request = CapacityRequest::query()->find((string) $command->get('request_id')) ?? throw DomainError::notFound('capacity_request');

        return CapacityPlanner::present($this->planner->decide($request, (string) $command->get('decision'), $command->get('note') !== null ? (string) $command->get('note') : null, $context, $command->get('node_name') !== null ? (string) $command->get('node_name') : null));
    }
}
