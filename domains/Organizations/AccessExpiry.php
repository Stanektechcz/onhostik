<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Access that ended on its date (Brain card H343). The permission itself stops at the second written on the policy
 * binding — the authorizer never reads an expired one — so nothing here decides who may act. This pass does what an
 * expired row cannot do for itself: it removes the membership through the same path as a removal by hand, which is
 * what takes the person's collaborator accounts (H333) and SSH keys (H185) off the panels, and it tells the
 * organization that the access is over.
 */
final class AccessExpiry
{
    public function __construct(
        private readonly OrganizationService $organizations,
        private readonly ProjectService $projects,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** @return array{memberships:int, project_roles:int, errors:int} */
    public function sweep(): array
    {
        $stats = ['memberships' => 0, 'project_roles' => 0, 'errors' => 0];
        $context = CommandContext::system('access expired');

        // project roles first: an expired membership removes the person from the organization's projects anyway
        foreach (ProjectMembership::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->limit(500)->get() as $role) {
            try {
                $project = Project::query()->find($role->project_id);
                $organization = $project === null ? null : Organization::query()->find($project->organization_id);
                $user = User::query()->find($role->user_id);
                if ($project === null || $organization === null || $user === null) {
                    $role->delete(); // nothing left to take away from

                    continue;
                }
                $this->projects->removeMember($organization, $project, $user, $context);
                $this->announce($organization, $user, (string) $role->role_key, $role->expires_at?->toIso8601String(), $project->name);
                $stats['project_roles']++;
            } catch (Throwable) {
                $stats['errors']++;
            }
        }
        foreach (OrganizationMembership::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->limit(500)->get() as $membership) {
            try {
                $organization = Organization::query()->find($membership->organization_id);
                $user = User::query()->find($membership->user_id);
                if ($organization === null || $user === null) {
                    $membership->delete();

                    continue;
                }
                if ($organization->owner_user_id === $user->id) { // cannot be set through the services; a row edited by hand must not lock an organization out
                    $membership->forceFill(['expires_at' => null])->save();

                    continue;
                }
                foreach (ProjectMembership::query()->where('user_id', $user->id)->whereIn('project_id', Project::query()->where('organization_id', $organization->id)->pluck('id'))->get() as $role) {
                    $project = Project::query()->find($role->project_id);
                    if ($project !== null) {
                        $this->projects->removeMember($organization, $project, $user, $context);
                    }
                }
                $this->organizations->removeMember($organization, $user, $context);
                $this->announce($organization, $user, (string) $membership->role_key, $membership->expires_at?->toIso8601String(), null);
                $stats['memberships']++;
            } catch (Throwable) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function announce(Organization $organization, User $user, string $role, ?string $expiredAt, ?string $project): void
    {
        $this->outbox->publish(GenericEvent::of('organization.member.expired', 'organization', $organization->id, [
            'user_id' => $user->id, 'name' => $user->name, 'email' => mb_strtolower((string) $user->email), 'role' => $role, 'expired_at' => $expiredAt, 'project' => $project,
        ], $organization->id));
    }
}
