<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Illuminate\Support\Carbon;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ServiceAccessCommandHandler implements CommandHandler
{
    public function __construct(private readonly ServiceAccessService $access) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ServiceAccessCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');
        $service = Service::query()->where('organization_id', $organization->id)->find((string) $command->get('service_id')) ?? throw DomainError::notFound('service');

        return match ($command->op()) {
            'share' => ['grant' => $this->access->present($this->access->share(
                $organization, $service, (string) $command->get('email', ''), array_values(array_map('strval', (array) $command->get('capabilities', []))), $context,
                $command->get('access_until') !== null ? Carbon::parse((string) $command->get('access_until')) : null, $command->get('note') !== null ? (string) $command->get('note') : null,
            ))],
            'revoke' => ['grant' => $this->access->present($this->access->revoke($organization, $service, (string) $command->get('grant_id', ''), $context))],
            default => throw new DomainError('service_access_op_unknown', "Unknown operation {$command->op()}.", 422),
        };
    }
}
