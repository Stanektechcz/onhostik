<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Illuminate\Support\Carbon;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Notifications\CalendarFeed;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Organizations\ProjectService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

final class OrganizationsCommandHandler implements CommandHandler
{
    public function __construct(private readonly OrganizationService $organizations, private readonly ProjectService $projects, private readonly CalendarFeed $calendar) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if ($command instanceof CreateOrganizationCommand) { // audit §5z: a customer profile for a signed-in user without one
            $owner = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
            if ($owner === null) {
                throw DomainError::forbidden('Only a signed-in person can create a customer profile.');
            }
            $organization = $this->organizations->create($owner, array_diff_key($command->payload, array_flip(['op'])), $context);

            return ['organization' => $organization->fresh(), 'organization_id' => $organization->id];
        }
        if (! $command instanceof OrganizationCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);

        return match ($command->op()) {
            'update' => ['organization' => $this->organizations->update($organization, array_diff_key($command->payload, array_flip(['op'])), $context)->fresh()],
            'status_page.verify' => app(OrganizationStatusService::class)->verifyDomain($organization, $context), // the customer's own status host (audit §5k-3)
            'invite' => $this->mayGrant($organization, $context, (string) $command->get('role', 'viewer'), null) ?? $this->organizations->invite($organization, (string) $command->get('email'), (string) $command->get('role', 'viewer'), $context, self::until($command)),
            'cancel_invitation' => ['invitation' => $this->organizations->cancelInvitation($organization, (string) $command->get('invitation_id'), $context), 'cancelled' => true],
            'change_role' => $this->mayGrant($organization, $context, (string) $command->get('role'), $this->user($command)) ?? ['membership' => $this->organizations->changeRole($organization, $this->user($command), (string) $command->get('role'), $context, array_key_exists('access_until', $command->payload), self::until($command))], // an absent access_until keeps the end the member has

            'remove_member' => (function () use ($organization, $command, $context) {
                $this->organizations->removeMember($organization, $this->user($command), $context);

                return ['removed' => true];
            })(),
            'create_project' => ['project' => $this->organizations->createProject($organization, array_diff_key($command->payload, array_flip(['op'])), $context)],
            'update_project' => ['project' => $this->projects->update($organization, $this->project($organization, $command), array_diff_key($command->payload, array_flip(['op', 'project_id'])), $context)],
            'archive_project' => ['project' => $this->projects->archive($organization, $this->project($organization, $command), $context)],
            'restore_project' => ['project' => $this->projects->restore($organization, $this->project($organization, $command), $context)],
            'add_project_member' => ['membership' => $this->projects->addMember($organization, $this->project($organization, $command), $this->user($command), (string) $command->get('role', 'viewer'), $context, self::until($command))],
            'remove_project_member' => (function () use ($organization, $command, $context) {
                $this->projects->removeMember($organization, $this->project($organization, $command), $this->user($command), $context);

                return ['removed' => true];
            })(),
            'assign_service_project' => ['service' => $this->projects->assignService($organization, $this->service($organization, $command), $command->get('project_id') ? $this->project($organization, $command) : null, $context)],
            'rotate_calendar_feed' => ['feed' => $this->calendar->rotate($organization, $context)],
            'transfer_ownership' => ['organization' => $this->organizations->transferOwnership($organization, $this->user($command), $context)],
            default => throw new DomainError('organization_op_unknown', "Unknown organization operation {$command->op()}.", 422),
        };
    }

    /** The date an access ends (H343); the controller has validated it as a future date. */
    /**
     * Who may hand out what. `organization.members.manage` said only THAT somebody manages members; nothing compared the
     * role being granted with the role of the one granting it, and nothing stopped a person from editing their own
     * membership. An org admin could make anybody — themselves included — the owner, and a member on time-limited access
     * (H343) could send `access_until: null` for themselves and stay for good.
     *  · the owner role is not handed out here: ownership moves by a transfer;
     *  · nobody changes their own membership, neither the role nor its end;
     *  · a role can be granted only by somebody whose own role covers every permission in it. Staff are not bound.
     * Returns null when the grant is allowed (so it chains with `??`), throws otherwise.
     */
    private function mayGrant(Organization $organization, CommandContext $context, string $roleKey, ?User $target): mixed
    {
        $actor = $context->actorType === 'user' && $context->actorId !== null ? User::query()->find($context->actorId) : null;
        if ($actor === null || $actor->is_staff) {
            return null; // the system (an accepted invitation, a seeder) and staff acting for the customer
        }
        if ($roleKey === 'owner' && ! ($target !== null && $organization->owner_user_id === $target->id)) {
            throw new DomainError('owner_role_locked', 'The owner role is not granted here; ownership moves by a transfer.', 403, ['field' => 'role']);
        }
        if ($target !== null && $target->id === $actor->id && $organization->owner_user_id !== $actor->id) { // the owner's own membership is locked by the service itself (owner role, no end)
            throw new DomainError('self_membership_locked', 'Nobody changes their own role or the end of their own access; ask another administrator.', 403);
        }
        $own = OrganizationMembership::query()->current()->where('organization_id', $organization->id)->where('user_id', $actor->id)->value('role_key');
        $roles = RoleCatalog::all();
        $missing = array_diff((array) ($roles[$roleKey]['permissions'] ?? []), (array) ($roles[(string) $own]['permissions'] ?? []));
        if ($missing !== []) {
            throw new DomainError('role_above_own', 'A role can be granted only by somebody whose own role covers it.', 403, ['field' => 'role', 'missing' => array_values(array_slice($missing, 0, 5))]);
        }

        return null;
    }

    private static function until(OrganizationCommand $command): ?Carbon
    {
        $value = $command->get('access_until');

        return $value === null || $value === '' ? null : Carbon::parse((string) $value);
    }

    private function project(Organization $organization, OrganizationCommand $command): Project
    {
        $project = Project::query()->where('organization_id', $organization->id)->find((string) $command->get('project_id'));
        if ($project === null) {
            throw DomainError::notFound('project');
        }

        return $project;
    }

    private function service(Organization $organization, OrganizationCommand $command): Service
    {
        $service = Service::query()->where('organization_id', $organization->id)->find((string) $command->get('service_id'));
        if ($service === null) {
            throw DomainError::notFound('service');
        }

        return $service;
    }

    private function user(OrganizationCommand $command): User
    {
        $user = User::query()->find((string) $command->get('user_id'));
        if ($user === null) {
            throw DomainError::notFound('user');
        }

        return $user;
    }
}
