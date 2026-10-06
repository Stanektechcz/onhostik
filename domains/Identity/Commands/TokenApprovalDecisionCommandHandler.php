<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\TokenApprovals;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class TokenApprovalDecisionCommandHandler implements CommandHandler
{
    public function __construct(private readonly TokenApprovals $approvals) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof TokenApprovalDecisionCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        // a signed-in person in the portal — never a token (whatever it is), the system or an assistant
        $decider = $context->actorType === 'user' && $context->actorId !== null && TokenApprovals::tokenIdOf($context->sessionId) === null
            ? User::query()->find($context->actorId) : null;
        if ($decider === null) {
            throw DomainError::forbidden('A request of an API token is decided by the owner of the organization, signed in to the portal.');
        }
        $organization = Organization::query()->find($command->organizationId) ?? throw DomainError::notFound('organization');
        $approval = Approval::query()->lockForUpdate()->where('organization_id', $organization->id)->find((string) $command->get('approval_id'));
        if ($approval === null) {
            throw DomainError::notFound('approval');
        }
        $note = $command->get('note');

        return ['data' => TokenApprovals::present($this->approvals->decide($approval, $organization, $decider, (string) $command->get('decision'), is_string($note) ? $note : null, $context))];
    }
}
