<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Listeners;

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * A person who loses a role loses what that role put on the panels: collaborator accounts on game servers (H333) and
 * SSH keys on shell accounts (H185). Leaving the organization — by hand or because the access ended on its date
 * (H343) — covers every service; losing a project role, or being moved to a smaller role in the organization (H332),
 * covers only the services the person can no longer manage through another role.
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
        if (in_array($m->name, ['service.access.revoked', 'service.access.expired'], true)) {
            $this->sharedServiceEnded($m);

            return;
        }
        if (! in_array($m->name, ['organization.member.removed', 'project.member.removed', 'organization.member.role_changed'], true)) {
            return;
        }
        // a role that could not manage services put nothing on the panels: a key the owner gave to a viewer is not theirs to lose here
        if ($m->name === 'organization.member.role_changed' && ! in_array('service.manage', RoleCatalog::all()[(string) data_get($m->payload, 'from', '')]['permissions'] ?? [], true)) {
            return;
        }
        $projectRole = $m->name === 'project.member.removed';
        $organization = Organization::query()->find($projectRole ? (string) data_get($m->payload, 'organization_id', '') : (string) $m->aggregate_id);
        if ($organization === null) {
            return;
        }
        $userId = (string) data_get($m->payload, 'user_id', '');
        $email = (string) data_get($m->payload, 'email', '');
        $services = match ($m->name) {
            'project.member.removed' => $this->lostServices($organization, (string) $m->aggregate_id, $userId),
            'organization.member.role_changed' => $this->lostServices($organization, null, $userId),
            default => null, // the person left: every service of the organization
        };
        if ($services === []) {
            return; // the person still manages every service in question through another role
        }
        $reason = match ($m->name) {
            'project.member.removed' => 'project role ended', 'organization.member.role_changed' => 'role changed', default => 'member removed',
        };
        $context = CommandContext::system($reason);
        if ($userId !== '') {
            $this->keys->revokeForUser($organization, $userId, $context, $reason, $services); // their SSH keys on shell accounts (H185)
        }
        if ($email !== '') {
            $this->review->revokeForMember($organization, $email, $context, $services); // their collaborator accounts on game servers (H333)
        }
    }

    /** One service was shared with somebody and no longer is: what they put on its panel under their own name goes with it, unless another role still covers it. */
    private function sharedServiceEnded(OutboxMessage $m): void
    {
        $organization = Organization::query()->find((string) data_get($m->payload, 'organization_id', ''));
        $service = Service::query()->find((string) $m->aggregate_id);
        $userId = (string) data_get($m->payload, 'user_id', '');
        $email = (string) data_get($m->payload, 'email', '');
        if ($organization === null || $service === null || $service->organization_id !== $organization->id) {
            return;
        }
        $user = $userId === '' ? null : User::query()->find($userId);
        if ($user !== null) {
            $this->authorizer->forget($user);
            if ($this->authorizer->can($user, 'service.manage', CommandScope::resource($service->id, $service->organization_id, $service->project_id))) {
                return; // still theirs to manage through a role in the organization or the project
            }
        }
        $context = CommandContext::system('shared service access ended');
        if ($userId !== '') {
            $this->keys->revokeForUser($organization, $userId, $context, 'shared service access ended', [$service->id]);
        }
        if ($email !== '') {
            $this->review->revokeForMember($organization, $email, $context, [$service->id]);
        }
    }

    /**
     * The services — of one project, or of the whole organization — the person can no longer manage. Somebody who is a
     * developer of the whole organization keeps their keys when one project role ends: nothing was lost.
     *
     * @return list<string>
     */
    private function lostServices(Organization $organization, ?string $projectId, string $userId): array
    {
        $user = $userId === '' ? null : User::query()->find($userId);
        if ($user !== null) {
            $this->authorizer->forget($user); // the binding was deleted a moment ago; the answer must not come from a cache
        }

        return array_values(Service::query()->where('organization_id', $organization->id)->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))->get()
            ->reject(fn (Service $service) => $user !== null && $this->authorizer->can($user, 'service.manage', CommandScope::resource($service->id, $service->organization_id, $service->project_id)))
            ->pluck('id')->all());
    }
}
