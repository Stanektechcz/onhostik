<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OwnershipTransfers;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class OwnershipCommandHandler implements CommandHandler
{
    public function __construct(private readonly OwnershipTransfers $transfers) {}

    /** @return array{transfer: array<string,mixed>} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof OwnershipCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');

        return ['transfer' => OwnershipTransfers::present(match ($command->op()) {
            'offer' => $this->transfers->offer($organization, User::query()->find((string) $command->get('user_id')) ?? throw DomainError::notFound('user'), $context),
            'accept' => $this->transfers->accept($organization, $context),
            'decline' => $this->transfers->decline($organization, $context),
            'cancel' => $this->transfers->cancel($organization, $context),
            default => throw new DomainError('ownership_op_unknown', "Unknown ownership operation {$command->op()}.", 422),
        })];
    }
}
