<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Notifications\CalendarFeed;
use Onhost\Domain\Organizations\Models\Organization;
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
        if (! $command instanceof OrganizationCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }
        $organization = Organization::query()->findOrFail($command->organizationId);

        return match ($command->op()) {
            'update' => ['organization' => $this->organizations->update($organization, array_diff_key($command->payload, array_flip(['op'])), $context)->fresh()],
            'status_page.verify' => app(OrganizationStatusService::class)->verifyDomain($organization, $context), // the customer's own status host (audit §5k-3)
            'invite' => $this->organizations->invite($organization, (string) $command->get('email'), (string) $command->get('role', 'viewer'), $context),
            'cancel_invitation' => ['invitation' => $this->organizations->cancelInvitation($organization, (string) $command->get('invitation_id'), $context), 'cancelled' => true],
            'change_role' => ['membership' => $this->organizations->changeRole($organization, $this->user($command), (string) $command->get('role'), $context)],
            'remove_member' => (function () use ($organization, $command, $context) {
                $this->organizations->removeMember($organization, $this->user($command), $context);

                return ['removed' => true];
            })(),
            'create_project' => ['project' => $this->organizations->createProject($organization, array_diff_key($command->payload, array_flip(['op'])), $context)],
            'update_project' => ['project' => $this->projects->update($organization, $this->project($organization, $command), array_diff_key($command->payload, array_flip(['op', 'project_id'])), $context)],
            'archive_project' => ['project' => $this->projects->archive($organization, $this->project($organization, $command), $context)],
            'restore_project' => ['project' => $this->projects->restore($organization, $this->project($organization, $command), $context)],
            'add_project_member' => ['membership' => $this->projects->addMember($organization, $this->project($organization, $command), $this->user($command), (string) $command->get('role', 'viewer'), $context)],
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
