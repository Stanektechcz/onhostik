<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Illuminate\Support\Carbon;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Notifications\CalendarFeed;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
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
    public function __construct(private readonly OrganizationService $organizations, private readonly ProjectService $projects, private readonly CalendarFeed $calendar, private readonly GrantPolicy $grants) {}

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
            // every grant, change and removal of a membership is decided by GrantPolicy first (permission program D1, P0-07)
            'invite' => $this->invite($organization, $command, $context),
            'cancel_invitation' => ['invitation' => $this->organizations->cancelInvitation($organization, (string) $command->get('invitation_id'), $context), 'cancelled' => true],
            'change_role' => $this->changeRole($organization, $command, $context),
            'remove_member' => (function () use ($organization, $command, $context) {
                $target = $this->user($command);
                $this->grants->assertMayRemove($organization, $context, $target);
                $this->organizations->removeMember($organization, $target, $context);

                return ['removed' => true];
            })(),
            'create_project' => ['project' => $this->organizations->createProject($organization, array_diff_key($command->payload, array_flip(['op'])), $context)],
            'update_project' => ['project' => $this->projects->update($organization, $this->project($organization, $command), array_diff_key($command->payload, array_flip(['op', 'project_id'])), $context)],
            'archive_project' => ['project' => $this->projects->archive($organization, $this->project($organization, $command), $context)],
            'restore_project' => ['project' => $this->projects->restore($organization, $this->project($organization, $command), $context)],
            'add_project_member' => (function () use ($organization, $command, $context) {
                [$project, $target, $role] = [$this->project($organization, $command), $this->user($command), (string) $command->get('role', 'viewer')];
                $this->grants->assertMayGrantProjectRole($organization, $project, $context, $target, $role);

                return ['membership' => $this->projects->addMember($organization, $project, $target, $role, $context, self::until($command))];
            })(),
            'remove_project_member' => (function () use ($organization, $command, $context) {
                [$project, $target] = [$this->project($organization, $command), $this->user($command)];
                $this->grants->assertMayRemoveProjectRole($organization, $project, $context, $target);
                $this->projects->removeMember($organization, $project, $target, $context);

                return ['removed' => true];
            })(),
            'assign_service_project' => ['service' => $this->projects->assignService($organization, $this->service($organization, $command), $command->get('project_id') ? $this->project($organization, $command) : null, $context)],
            'rotate_calendar_feed' => ['feed' => $this->calendar->rotate($organization, $context)],
            'transfer_ownership' => (function () use ($organization, $command, $context) {
                $heir = $this->user($command);
                $this->grants->assertMayTransferOwnership($organization, $context, $heir);

                return ['organization' => $this->organizations->transferOwnership($organization, $heir, $context)];
            })(),
            default => throw new DomainError('organization_op_unknown', "Unknown organization operation {$command->op()}.", 422),
        };
    }

    /** @return array{invitation: OrganizationInvitation, token: string} */
    private function invite(Organization $organization, OrganizationCommand $command, CommandContext $context): array
    {
        $role = (string) $command->get('role', 'viewer');
        $this->grants->assertMayInvite($organization, $context, $role);

        return $this->organizations->invite($organization, (string) $command->get('email'), $role, $context, self::until($command));
    }

    /** An absent access_until keeps the end the member has; `null` removes it. @return array{membership: OrganizationMembership} */
    private function changeRole(Organization $organization, OrganizationCommand $command, CommandContext $context): array
    {
        $target = $this->user($command);
        $role = (string) $command->get('role');
        $this->grants->assertMayChangeRole($organization, $context, $target, $role);

        return ['membership' => $this->organizations->changeRole($organization, $target, $role, $context, array_key_exists('access_until', $command->payload), self::until($command))];
    }

    /** The date an access ends (H343); the controller has validated it as a future date. */
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
