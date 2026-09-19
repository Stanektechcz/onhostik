<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Listeners;

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/**
 * A person who loses a role loses what that role put on the panels: collaborator accounts on game servers (H333) and
 * SSH keys on shell accounts (H185). Leaving the organization — by hand or because the access ended on its date
 * (H343) — covers every service; losing a project role covers that project's services, and only those the person can
 * no longer manage through another role.
 */
final class RevokeDelegatedAccess
{
    public function __construct(private readonly DelegatedAccessReview $review, private readonly SshKeyLedger $keys, private readonly Authorizer $authorizer) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name === 'operation.succeeded' && data_get($m->payload, 'kind') === 'service.action') {
            $this->keys->confirmed((string) $m->aggregate_id); // a key removal the panel applied from its queue is closed now, not at the next scheduled pass

            return;
        }
        if (! in_array($m->name, ['organization.member.removed', 'project.member.removed'], true)) {
            return;
        }
        $projectRole = $m->name === 'project.member.removed';
        $organization = Organization::query()->find($projectRole ? (string) data_get($m->payload, 'organization_id', '') : (string) $m->aggregate_id);
        if ($organization === null) {
            return;
        }
        $userId = (string) data_get($m->payload, 'user_id', '');
        $email = (string) data_get($m->payload, 'email', '');
        $services = $projectRole ? $this->lostServices($organization, (string) $m->aggregate_id, $userId) : null;
        if ($services === []) {
            return; // the person still manages every service of the project through another role
        }
        $reason = $projectRole ? 'project role ended' : 'member removed';
        $context = CommandContext::system($reason);
        if ($userId !== '') {
            $this->keys->revokeForUser($organization, $userId, $context, $reason, $services); // their SSH keys on shell accounts (H185)
        }
        if ($email !== '') {
            $this->review->revokeForMember($organization, $email, $context, $services); // their collaborator accounts on game servers (H333)
        }
    }

    /**
     * The services of the project the person can no longer manage. Somebody who is a developer of the whole
     * organization keeps their keys when one project role ends: nothing was lost.
     *
     * @return list<string>
     */
    private function lostServices(Organization $organization, string $projectId, string $userId): array
    {
        $user = $userId === '' ? null : User::query()->find($userId);
        if ($user !== null) {
            $this->authorizer->forget($user); // the binding was deleted a moment ago; the answer must not come from a cache
        }

        return array_values(Service::query()->where('organization_id', $organization->id)->where('project_id', $projectId)->get()
            ->reject(fn (Service $service) => $user !== null && $this->authorizer->can($user, 'service.manage', CommandScope::resource($service->id, $service->organization_id, $service->project_id)))
            ->pluck('id')->all());
    }
}
