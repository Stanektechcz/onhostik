<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Commands;

use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Compliance\Models\DataRequest;
use Onhost\Domain\Incidents\Presenters;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class DataRequestCommandHandler implements CommandHandler
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof DataRequestCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');
        $reason = $command->get('reason');

        return ['data' => Presenters::dataRequest(match ($command->op()) {
            'request' => $this->compliance->requestData($organization, (string) $command->get('kind', ''), $context, is_string($reason) ? $reason : null),
            'cancel' => $this->compliance->cancelDataRequest(
                DataRequest::query()->where('organization_id', $organization->id)->find((string) $command->get('data_request_id', '')) ?? throw DomainError::notFound('data_request'),
                $context,
            ),
            default => throw new DomainError('data_request_op_invalid', 'Unknown data request operation.', 422, ['field' => 'op']),
        })];
    }
}
