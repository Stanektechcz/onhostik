<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\AccessSnapshot;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Services\Listeners\RevokeDelegatedAccess;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * An access snapshot precedes every removal and role change (permission program I10, S1-02; audit TD-7 "no audit of prior
 * state"): what the person could do in the organization — the membership and its end, every organization, project and
 * single-service binding of theirs there, their project roles and the shares behind the `svc_*` bindings — in one record, kept
 * 90 days and restorable once, exactly: remove + restore gives back the same rows (AccessRestoreTest compares capture()).
 *
 * What a restore cannot give back is outside the platform's rows: the SSH keys and panel sub-users the listeners took off the
 * panels (the person adds them again), a Discord link or hook that was switched off (it stays in the owner's list with its
 * reason), and the person's API tokens of the organization, which a removal revokes for good (S1-07 red team: a restore gives
 * back access, never a credential that may have leaked). The restore is a grant like any other — GrantPolicy::assertMayRestore decides who may make it — and it takes a
 * snapshot of what it replaces first, so it can be undone the same way.
 */
final class AccessSnapshots
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public static function retentionDays(): int
    {
        return max(1, (int) config('onhost.grants.snapshot_retention_days', 90));
    }

    /**
     * What `$userId` can do in the organization now, in a stable order (the equality of two captures is the proof of a restore).
     *
     * @return array{membership: ?array{role: string, state: string, expires_at: ?string}, bindings: list<array{role: string, scope_type: string, scope_id: ?string, expires_at: ?string}>, projects: list<array{project_id: string, role: string, expires_at: ?string}>, shares: list<array{grant_id: string, service_id: string, capabilities: list<string>, expires_at: ?string}>}
     */
    public function capture(Organization $organization, string $userId): array
    {
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $userId)->first();
        $bindings = PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $userId)->where('organization_id', $organization->id)->get()
            ->map(fn (PolicyBinding $b) => ['role' => (string) $b->getAttribute('role_key'), 'scope_type' => (string) $b->getAttribute('scope_type'), 'scope_id' => $b->getAttribute('scope_id') !== null ? (string) $b->getAttribute('scope_id') : null, 'expires_at' => self::iso($b->getAttribute('expires_at'))])
            ->sortBy(fn (array $b) => $b['scope_type'].'|'.$b['scope_id'].'|'.$b['role'])->values()->all();
        $projects = ProjectMembership::query()->where('user_id', $userId)->whereIn('project_id', Project::query()->where('organization_id', $organization->id)->select('id'))->get()
            ->map(fn (ProjectMembership $p) => ['project_id' => $p->project_id, 'role' => $p->role_key, 'expires_at' => self::iso($p->expires_at)])
            ->sortBy(fn (array $p) => $p['project_id'].'|'.$p['role'])->values()->all();
        $shares = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('user_id', $userId)->where('state', ServiceAccessGrant::ACTIVE)->get()
            ->map(fn (ServiceAccessGrant $g) => ['grant_id' => $g->id, 'service_id' => $g->service_id, 'capabilities' => array_values((array) $g->capabilities), 'expires_at' => self::iso($g->expires_at)])
            ->sortBy('grant_id')->values()->all();

        return [
            'membership' => $membership === null ? null : ['role' => $membership->role_key, 'state' => (string) $membership->getAttribute('state'), 'expires_at' => self::iso($membership->expires_at)],
            'bindings' => $bindings, 'projects' => $projects, 'shares' => $shares,
        ];
    }

    /** The snapshot before a change: null when the person has nothing in the organization to lose. */
    public function take(Organization $organization, string $userId, string $reason, CommandContext $context): ?AccessSnapshot
    {
        $access = $this->capture($organization, $userId);
        if ($access['membership'] === null && $access['bindings'] === [] && $access['projects'] === [] && $access['shares'] === []) {
            return null;
        }

        $takenBy = $context->actorType === 'system' ? null : ($context->onBehalfOfUserId ?? $context->actorId);

        return AccessSnapshot::query()->create([
            'organization_id' => $organization->id, 'user_id' => $userId, 'reason' => $reason, 'access' => $access,
            'taken_by' => $takenBy, 'taken_by_role' => $takenBy === null ? null : self::roleOf($organization, (string) $takenBy), 'expires_at' => now()->addDays(self::retentionDays()),
        ]);
    }

    /**
     * S1-07 red team (restore × I3): the organization role the change was made with, which a restore must cover — the owner's for
     * the owner, the membership's (or a service account's organization binding) otherwise, null for somebody with none there.
     */
    private static function roleOf(Organization $organization, string $actorId): ?string
    {
        if ((string) $organization->owner_user_id === $actorId) {
            return 'owner';
        }
        $role = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $actorId)->current()->value('role_key')
            ?? PolicyBinding::query()->where('principal_id', $actorId)->where('scope_type', 'organization')->where('scope_id', $organization->id)->value('role_key');

        return is_string($role) ? $role : null;
    }

    /**
     * Gives the person back exactly what the snapshot recorded (GrantPolicy::assertMayRestore has decided the restorer may): the
     * membership, every binding, the project roles, the shares — each no later than the restorer's own end (I5) and none that has
     * lapsed since. What the person holds now and the snapshot does not is taken away (a snapshot is a state, not an addition).
     *
     * @return array{restored: true, snapshot_id: string, before_restore: ?string, role: ?string}
     */
    public function restore(Organization $organization, AccessSnapshot $snapshot, CommandContext $context): array
    {
        $user = User::query()->find($snapshot->user_id) ?? throw DomainError::notFound('user');
        $access = (array) $snapshot->access;
        $membership = $access['membership'] ?? null;
        if ($membership !== null && ($until = self::carbon($membership['expires_at'] ?? null)) !== null && $until->isPast()) {
            throw new DomainError('snapshot_lapsed', 'The access in this snapshot has ended on its own date since; invite the person again.', 409);
        }
        $policy = app(GrantPolicy::class);
        $restorer = $context->actorType === 'system' ? null : ($context->onBehalfOfUserId ?? $context->actorId);

        return DB::transaction(function () use ($organization, $snapshot, $context, $user, $access, $membership, $policy, $restorer) {
            // claimed first, conditionally (review round 1): two restores of one snapshot — two admins, two idempotency keys — both
            // passed GrantPolicy's look at restored_at before either committed; the second one now finds it taken and changes nothing
            $restoredAt = now();
            if (AccessSnapshot::query()->whereKey($snapshot->id)->whereNull('restored_at')->update(['restored_at' => $restoredAt, 'restored_by' => $restorer]) !== 1) {
                throw new DomainError('snapshot_restored', 'This snapshot has been restored already; a newer one was taken before that restore.', 409);
            }
            $current = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->first();
            $previousRole = $current?->role_key;
            $was = $this->capture($organization, $user->id); // what the restore may take away (S1-07 red team: the listeners must hear it)
            $before = $this->take($organization, $user->id, 'before_restore', $context);

            PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $user->id)->where('organization_id', $organization->id)->delete();
            ProjectMembership::query()->where('user_id', $user->id)->whereIn('project_id', Project::query()->where('organization_id', $organization->id)->select('id'))->delete();
            $keep = array_column((array) ($access['shares'] ?? []), 'grant_id');
            ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->where('state', ServiceAccessGrant::ACTIVE)->whereNotIn('id', $keep)
                ->update(['state' => ServiceAccessGrant::REVOKED, 'revoked_at' => now(), 'revoked_by' => $restorer]);

            if ($membership === null) {
                OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->delete();
            } else {
                $end = $policy->grantEnd($context, CommandScope::organization($organization->id), GrantPolicy::permissionsOf((string) $membership['role']) ?? [], self::carbon($membership['expires_at'] ?? null));
                OrganizationMembership::query()->updateOrCreate(['organization_id' => $organization->id, 'user_id' => $user->id], [
                    'role_key' => (string) $membership['role'], 'state' => (string) ($membership['state'] ?? 'active'), 'expires_at' => $end,
                    'joined_at' => $current?->getAttribute('joined_at') ?? now(), 'invited_by' => $restorer,
                ]);
            }
            foreach ((array) ($access['bindings'] ?? []) as $binding) {
                $scope = self::scopeOf($organization, (string) $binding['scope_type'], $binding['scope_id'] ?? null);
                $end = self::carbon($binding['expires_at'] ?? null);
                if ($scope === null || ($end !== null && $end->isPast())) {
                    continue; // a project or service that is gone, or a binding whose own date has come: nothing to give back
                }
                PolicyBinding::query()->create([
                    'principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => (string) $binding['role'], 'scope_type' => (string) $binding['scope_type'],
                    'scope_id' => $binding['scope_id'] ?? null, 'organization_id' => $organization->id, 'granted_by' => $restorer,
                    'expires_at' => $policy->grantEnd($context, $scope, GrantPolicy::permissionsOf((string) $binding['role']) ?? [], $end),
                ]);
            }
            foreach ((array) ($access['projects'] ?? []) as $project) {
                $end = self::carbon($project['expires_at'] ?? null);
                if (($end !== null && $end->isPast()) || ! Project::query()->where('organization_id', $organization->id)->whereKey((string) $project['project_id'])->exists()) {
                    continue;
                }
                $scope = CommandScope::project((string) $project['project_id'], $organization->id);
                ProjectMembership::query()->create(['project_id' => (string) $project['project_id'], 'user_id' => $user->id, 'role_key' => (string) $project['role'],
                    'expires_at' => $policy->grantEnd($context, $scope, GrantPolicy::permissionsOf((string) $project['role']) ?? [], $end)]);
            }
            foreach ((array) ($access['shares'] ?? []) as $share) {
                $grant = ServiceAccessGrant::query()->where('organization_id', $organization->id)->find((string) $share['grant_id']);
                $service = $grant === null ? null : Service::query()->find($grant->service_id);
                $end = self::carbon($share['expires_at'] ?? null);
                if ($grant === null || $service === null || in_array($service->state, [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING], true) || ($end !== null && $end->isPast())) {
                    continue;
                }
                $grant->forceFill(['state' => ServiceAccessGrant::ACTIVE, 'user_id' => $user->id, 'capabilities' => array_values((array) $share['capabilities']), 'revoked_at' => null, 'revoked_by' => null,
                    'accepted_at' => $grant->accepted_at ?? now(),
                    'expires_at' => $policy->grantEnd($context, CommandScope::resource($service->id, $organization->id, $service->project_id), GrantPolicy::capabilityPermissions(array_values((array) $share['capabilities'])), $end)])->save();
            }
            $snapshot->forceFill(['restored_at' => $restoredAt, 'restored_by' => $restorer])->syncOriginal();
            app(Authorizer::class)->forget($user);

            $role = $membership['role'] ?? null;
            // S1-07 red team: a guest the restore leaves with nothing shared goes, as revoking their last share lets them go
            // (ServiceAccessService::releaseGuest) — a guest membership with nothing behind it is only a way back in
            $released = $this->releaseEmptyGuest($organization, $user, $role, $context);
            $role = $released ? null : $role;
            $this->audit->record($context->withScope($organization->id), 'organization.access.restore', 'succeeded', ['user_id' => $user->id, 'snapshot_id' => $snapshot->id, 'reason' => $snapshot->reason, 'before_restore' => $before?->id, 'role' => $role], 'organization', $organization->id);
            $this->outbox->publish(GenericEvent::of('organization.access.restored', 'organization', $organization->id, [
                'user_id' => $user->id, 'email' => mb_strtolower((string) $user->email), 'name' => $user->name, 'snapshot_id' => $snapshot->id, 'reason' => $snapshot->reason, 'role' => $role,
            ], $organization->id));
            // a restore that lowers a current role tells the listeners the way a role change does (side doors above the new role go)
            if ($previousRole !== null && $role !== null && $previousRole !== $role) {
                $this->outbox->publish(GenericEvent::of('organization.member.role_changed', 'organization', $organization->id, ['user_id' => $user->id, 'email' => mb_strtolower((string) $user->email), 'from' => $previousRole, 'to' => $role, 'via' => 'access_restore'], $organization->id));
            }
            $this->publishWhatItTook($organization, $user, $was, $this->capture($organization, $user->id), $before?->id, $released);

            return ['restored' => true, 'snapshot_id' => $snapshot->id, 'before_restore' => $before?->id, 'role' => $role];
        });
    }

    /**
     * S1-07 red team (TASK-0042; H185/H333 class): a restore takes away what the person holds now and the snapshot does not — it
     * revokes shares, narrows them, drops project roles, deletes the membership — and it published only `role_changed`. So
     * RevokeDelegatedAccess never ran: the person lost the console on paper and kept their SSH keys and game sub-users. Each loss
     * is now told with the event its own command publishes, so the same listeners act (each re-checks that no other role still
     * gives the console): leaving → `organization.member.removed`; a share gone → `service.access.revoked`; a share that lost the
     * console → `service.access.reduced`; a project role gone or changed → `project.member.removed`. All carry `via: access_restore`.
     *
     * @param  array{membership: ?array<string,mixed>, bindings: list<array<string,mixed>>, projects: list<array{project_id: string, role: string, expires_at: ?string}>, shares: list<array{grant_id: string, service_id: string, capabilities: list<string>, expires_at: ?string}>}  $was
     * @param  array{membership: ?array<string,mixed>, bindings: list<array<string,mixed>>, projects: list<array{project_id: string, role: string, expires_at: ?string}>, shares: list<array{grant_id: string, service_id: string, capabilities: list<string>, expires_at: ?string}>}  $now
     */
    private function publishWhatItTook(Organization $organization, User $user, array $was, array $now, ?string $snapshotId, bool $released): void
    {
        $email = mb_strtolower((string) $user->email);
        if ($was['membership'] !== null && $now['membership'] === null) {
            if (! $released) { // a released guest was removed by removeMember, which told the listeners itself
                $this->outbox->publish(GenericEvent::of('organization.member.removed', 'organization', $organization->id, ['user_id' => $user->id, 'email' => $email, 'snapshot_id' => $snapshotId, 'via' => 'access_restore'], $organization->id));
            }

            return; // leaving covers every service of the organization (RevokeDelegatedAccess, CloseServiceAccessGrants)
        }
        $shares = collect($now['shares'])->keyBy('grant_id');
        foreach ($was['shares'] as $share) {
            $service = Service::query()->find($share['service_id']);
            if ($service === null) {
                continue;
            }
            $payload = ['grant_id' => $share['grant_id'], 'user_id' => $user->id, 'email' => $email, 'organization_id' => $organization->id, 'service' => (string) ($service->label ?: ($service->hostname ?: $service->name)), 'via' => 'access_restore'];
            $kept = $shares->get($share['grant_id']);
            if ($kept === null) {
                $this->outbox->publish(GenericEvent::of('service.access.revoked', 'service', $service->id, $payload, $organization->id));

                continue;
            }
            $lost = array_diff(GrantPolicy::capabilityPermissions($share['capabilities']), GrantPolicy::capabilityPermissions($kept['capabilities']));
            if (in_array(RevokeDelegatedAccess::ARTEFACT_PERMISSION, $lost, true)) {
                $this->outbox->publish(GenericEvent::of('service.access.reduced', 'service', $service->id, $payload + ['capabilities' => $kept['capabilities'], 'dropped' => array_values(array_diff($share['capabilities'], $kept['capabilities']))], $organization->id));
            }
        }
        $projects = collect($now['projects'])->keyBy('project_id');
        foreach ($was['projects'] as $project) {
            $to = $projects->get($project['project_id'])['role'] ?? null;
            if ($to !== $project['role']) { // gone, or another role: the listener keeps what the role now still gives the console to
                $this->outbox->publish(GenericEvent::of('project.member.removed', 'project', $project['project_id'], ['user_id' => $user->id, 'email' => $email, 'organization_id' => $organization->id, 'from' => $project['role'], 'to' => $to, 'via' => 'access_restore'], $organization->id));
            }
        }
    }

    private function releaseEmptyGuest(Organization $organization, User $user, ?string $role, CommandContext $context): bool
    {
        if ($role !== 'guest') {
            return false;
        }
        $shared = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->where('state', ServiceAccessGrant::ACTIVE)->exists();
        $projects = ProjectMembership::query()->where('user_id', $user->id)->whereIn('project_id', Project::query()->where('organization_id', $organization->id)->select('id'))->exists();
        if ($shared || $projects) {
            return false;
        }
        app(OrganizationService::class)->removeMember($organization, $user, $context, 'access_restore');

        return true;
    }

    /** Snapshots past their retention are gone for good (onhost:access:expire). @return int how many */
    public function prune(int $limit = 1000): int
    {
        $ids = AccessSnapshot::query()->where('expires_at', '<=', now())->limit($limit)->pluck('id')->all();

        return $ids === [] ? 0 : AccessSnapshot::query()->whereIn('id', $ids)->delete();
    }

    /** @return array<string,mixed> the team page's row, with the exact request that restores it */
    public function present(AccessSnapshot $snapshot, ?User $user = null): array
    {
        $access = (array) $snapshot->access;

        return [
            'id' => $snapshot->id, 'user_id' => $snapshot->user_id, 'email' => $user?->email, 'name' => $user?->name, 'reason' => $snapshot->reason,
            'taken_at' => $snapshot->created_at?->toIso8601String(), 'expires_at' => $snapshot->expires_at->toIso8601String(), 'restored_at' => $snapshot->restored_at?->toIso8601String(),
            'access' => ['role' => $access['membership']['role'] ?? null, 'access_until' => $access['membership']['expires_at'] ?? null, 'projects' => count((array) ($access['projects'] ?? [])), 'shared_services' => count((array) ($access['shares'] ?? [])), 'bindings' => count((array) ($access['bindings'] ?? []))],
            'restore' => $snapshot->restored_at === null && $snapshot->expires_at->isFuture()
                ? ['method' => 'POST', 'path' => "/v1/organizations/{$snapshot->organization_id}/access-snapshots/restore", 'body' => ['snapshot_id' => $snapshot->id]]
                : null,
        ];
    }

    private static function scopeOf(Organization $organization, string $type, mixed $id): ?CommandScope
    {
        return match ($type) {
            'organization' => CommandScope::organization($organization->id),
            'project' => Project::query()->where('organization_id', $organization->id)->whereKey((string) $id)->exists() ? CommandScope::project((string) $id, $organization->id) : null,
            // a service that has ended gets no `svc_*` binding back — its share is skipped below for the same reason (S1-07 red team)
            'resource' => ($service = Service::query()->where('organization_id', $organization->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::TERMINATING])->find((string) $id)) !== null ? CommandScope::resource($service->id, $organization->id, $service->project_id) : null,
            default => null,
        };
    }

    private static function iso(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    private static function carbon(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
