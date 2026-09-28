<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/**
 * The invitee joins (TASK-0042, S1-02): the membership (OrganizationService::acceptInvitation — the sender re-checked, I6; never
 * lower than what the person has, I9; no later than the sender's own end, I5), then whatever was shared with the address while
 * it had no membership, as far as whoever shared it could still share it (ServiceAccessService::activatePending, I6).
 * Only the person themselves: nobody accepts for somebody else, and an API token does not accept (a token never grants, D3).
 */
final class AcceptInvitationCommandHandler implements CommandHandler
{
    public function __construct(private readonly OrganizationService $organizations, private readonly ServiceAccessService $access) {}

    /** @return array{organization_id: string, role: string, shared_services: int} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof AcceptInvitationCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        if ($context->actorType !== 'user' || $context->actorId === null || ($context->onBehalfOfUserId !== null && $context->onBehalfOfUserId !== $context->actorId)) {
            throw DomainError::forbidden('An invitation is accepted by the person it was sent to, in person.');
        }
        if (str_starts_with((string) $context->sessionId, 'token:')) {
            throw DomainError::forbidden('An API token does not accept invitations; sign in to the portal.');
        }
        $user = User::query()->find($context->actorId) ?? throw DomainError::forbidden('Unknown actor.');
        $membership = $this->organizations->acceptInvitation((string) $command->get('token', ''), $user, $context);
        $organization = Organization::query()->findOrFail($membership->organization_id);

        return ['organization_id' => $membership->organization_id, 'role' => $membership->role_key, 'shared_services' => $this->access->activatePending($user, $organization)];
    }
}
