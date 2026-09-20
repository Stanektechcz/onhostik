<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class ApprovalDecisionCommandHandler implements CommandHandler
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ApprovalDecisionCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $decider = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($decider === null || ! $decider->is_staff) {
            throw DomainError::forbidden('A request for approval is decided by a signed-in member of staff.'); // never a token, the system or an assistant
        }
        $approval = Approval::query()->lockForUpdate()->find((string) $command->get('approval_id'));
        if ($approval === null) {
            throw DomainError::notFound('approval');
        }

        return ApprovalService::present($this->approvals->decide($approval, $decider, (string) $command->get('decision'), $command->get('note') === null ? null : (string) $command->get('note'), $context));
    }
}
