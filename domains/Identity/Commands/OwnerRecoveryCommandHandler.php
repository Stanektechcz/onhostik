<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OwnerRecoveries;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class OwnerRecoveryCommandHandler implements CommandHandler
{
    public function __construct(private readonly OwnerRecoveries $recoveries) {}

    /** @return array{recovery: array<string,mixed>} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof OwnerRecoveryCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->find((string) $command->get('organization_id')) ?? throw DomainError::notFound('organization');
        $newOwner = $command->get('new_owner_user_id');

        return ['recovery' => OwnerRecoveries::present(match ($command->op()) {
            'open' => $this->recoveries->open($organization, (string) $command->get('mode'), is_string($newOwner) && $newOwner !== '' ? $newOwner : null, (string) $command->get('reason', ''), (string) $command->get('ticket_ref', ''), $context),
            'complete' => $this->recoveries->complete($organization, $context),
            'cancel' => $this->recoveries->cancel($organization, $context),
            default => throw new DomainError('owner_recovery_op_unknown', "Unknown owner recovery operation {$command->op()}.", 422),
        }, staff: true)];
    }
}
