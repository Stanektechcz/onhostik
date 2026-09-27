<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * The one place that decides who may grant, change or remove which role for whom (permission program D1, P0-07).
 *
 * Every grant path used to decide for itself: `change_role` took any user id and made a stranger a member (TD-2), nothing
 * compared the role of the person removed or demoted with the one doing it (TD-5), project roles skipped the check
 * entirely (TD-3), and an accepted invitation could overwrite the owner's binding (TD-1). The invariants applied here
 * (program §3, I1–I4, I8, I9, I11; the rest land with S1-01):
 *  · I1  a grantor gives only what they effectively hold at the target scope or an ancestor;
 *  · I2  nobody grants to or changes themselves — leaving (removing yourself) is the one change of your own;
 *  · I3  nobody changes or removes somebody whose current role they do not cover;
 *  · I4  the owner binding is never lowered here — ownership moves by a transfer;
 *  · I8  a role change, a removal or a transfer targets a current member only; the way in is an invitation;
 *  · I9  accepting an invitation never lowers a current membership;
 *  · I11 a project role comes from an allow-list.
 * Unknown role keys fail closed: they grant nothing, and a member holding one is covered only by somebody who holds every
 * customer permission (the owner). The system actor (sweeps, an accepted invitation) is bound by the target invariants but
 * has no grantor to compare. Staff acting for a customer are not compared either — that is P0-08 (`StaffActor`, IF-8).
 */
final class GrantPolicy
{
    /**
     * Roles a person can hold inside one project (I11). Organization-wide powers — the members, the money, the organization
     * itself — are not a project's to hand out: an `org_admin` "inside a project" managed the organization's members.
     */
    public const PROJECT_ROLES = ['developer', 'cloud_operator', 'game_operator', 'mail_manager', 'dns_manager', 'domain_manager', 'security_auditor', 'support_contact', 'viewer'];

    public function __construct(private readonly Authorizer $authorizer) {}

    /** Inviting (I1, I4): the role must exist, be an organization role and be covered by the inviter. */
    public function assertMayInvite(Organization $organization, CommandContext $context, string $roleKey): void
    {
        self::assertOrganizationRole($roleKey);
        if ($roleKey === 'owner') {
            throw new DomainError('owner_role_locked', 'The owner role is not granted here; ownership moves by a transfer.', 403, ['field' => 'role']);
        }
        $this->assertGrantorHolds($organization, $context, $roleKey, CommandScope::organization($organization->id));
    }

    /**
     * A new role (or a new end of the access) for a current member (I1–I4, I8). Neither is changed for oneself: a member on
     * time-limited access sending `access_until: null` for themselves stayed for good (H343).
     */
    public function assertMayChangeRole(Organization $organization, CommandContext $context, User $target, string $roleKey): OrganizationMembership
    {
        $membership = self::currentMembership($organization, $target);
        self::assertOrganizationRole($roleKey);
        if ($roleKey === 'owner' && $organization->owner_user_id !== $target->id) {
            throw new DomainError('owner_role_locked', 'The owner role is not granted here; ownership moves by a transfer.', 403, ['field' => 'role']);
        }
        $actor = $this->grantor($context);
        if ($actor === null) {
            return $membership;
        }
        if ($actor instanceof User && $target->id === $actor->id && $organization->owner_user_id !== $actor->id) { // the owner's own membership is locked by the service itself (owner role, no end)
            throw new DomainError('self_membership_locked', 'Nobody changes their own role or the end of their own access; ask another administrator.', 403);
        }
        $this->assertCovers($organization, $actor, $membership->role_key);
        $this->assertGrantorHolds($organization, $context, $roleKey, CommandScope::organization($organization->id));

        return $membership;
    }

    /** Removing a member (I3, I4, I8). A lapsed membership still waiting for its sweep is a member too; leaving is allowed. */
    public function assertMayRemove(Organization $organization, CommandContext $context, User $target): OrganizationMembership
    {
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $target->id)->first();
        if ($membership === null) {
            throw DomainError::notFound('member');
        }
        if ($organization->owner_user_id === $target->id) {
            throw new DomainError('owner_cannot_be_removed', 'Transfer ownership before removing the owner.');
        }
        $actor = $this->grantor($context);
        if ($actor === null || ($actor instanceof User && $actor->id === $target->id)) {
            return $membership; // the system, or somebody leaving: giving up your own access needs no cover
        }
        $this->assertCovers($organization, $actor, $membership->role_key);

        return $membership;
    }

    /** Ownership goes to a current member only (I8); who may transfer is `organization.close` (CRITICAL) at the bus. */
    public function assertMayTransferOwnership(Organization $organization, User $target): OrganizationMembership
    {
        return self::currentMembership($organization, $target);
    }

    /** A project role for a current member of the organization (I1, I2, I3, I11). */
    public function assertMayGrantProjectRole(Organization $organization, Project $project, CommandContext $context, User $target, string $roleKey): void
    {
        if (! in_array($roleKey, self::PROJECT_ROLES, true)) {
            throw new DomainError('invalid_role', "Role {$roleKey} cannot be assigned inside a project.", 422, ['field' => 'role', 'allowed' => self::PROJECT_ROLES]);
        }
        $actor = $this->grantor($context);
        if ($actor === null) {
            return;
        }
        if ($actor instanceof User && $target->id === $actor->id) {
            throw new DomainError('self_membership_locked', 'Nobody gives themselves a project role; ask another administrator.', 403);
        }
        $current = ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $target->id)->value('role_key');
        if (is_string($current)) {
            $this->assertCovers($organization, $actor, $current, CommandScope::project($project->id, $organization->id));
        }
        $this->assertGrantorHolds($organization, $context, $roleKey, CommandScope::project($project->id, $organization->id));
    }

    /** Taking a project role back (I3, I8): only a role that exists, and only one the remover covers. */
    public function assertMayRemoveProjectRole(Organization $organization, Project $project, CommandContext $context, User $target): void
    {
        $current = ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $target->id)->value('role_key');
        if (! is_string($current)) {
            throw DomainError::notFound('project member');
        }
        $actor = $this->grantor($context);
        if ($actor === null || ($actor instanceof User && $actor->id === $target->id)) {
            return;
        }
        $this->assertCovers($organization, $actor, $current, CommandScope::project($project->id, $organization->id));
    }

    /**
     * I9: does accepting an invitation for `$offered` (ending at `$offeredUntil`) give the member strictly more than the
     * current membership? Anything else — a smaller or sideways role, an access that would end sooner — keeps what they have.
     */
    public static function widens(OrganizationMembership $current, string $offered, ?CarbonInterface $offeredUntil): bool
    {
        $have = self::permissionsOf($current->role_key);
        $get = self::permissionsOf($offered);
        if ($have === null || $get === null || array_diff($have, $get) !== [] || array_diff($get, $have) === []) {
            return false;
        }
        if ($offeredUntil === null) {
            return true;
        }

        return $current->expires_at !== null && $offeredUntil->greaterThanOrEqualTo($current->expires_at);
    }

    /**
     * What a role carries: the catalogue's line joined with what the database says the role holds (an IAM edit adds rows
     * the catalogue does not know). Null for a key the catalogue does not know — the caller fails closed.
     *
     * @return list<string>|null
     */
    public static function permissionsOf(string $roleKey): ?array
    {
        if (! RoleCatalog::exists($roleKey)) {
            return null;
        }
        $stored = DB::table('role_permissions')->where('role_key', $roleKey)->pluck('permission_key')->map(fn ($p) => (string) $p)->all();

        // role definitions come through RoleResolver, not the raw catalogue (TASK-0037, program D6/P0-11; RiskFloorTest's
        // allow-list only shrinks) — the key is known here, so grantable() is exactly the catalogue's line
        return array_values(array_unique(array_merge(RoleResolver::grantable($roleKey), $stored)));
    }

    private static function assertOrganizationRole(string $roleKey): void
    {
        // a known, non-staff, non-resource role — the same set as customerRoleKeys(), without reading the raw catalogue (TASK-0037 P0-11)
        if (! in_array($roleKey, RoleCatalog::customerRoleKeys(), true)) {
            throw new DomainError('invalid_role', "Role {$roleKey} cannot be assigned inside an organization.", 422, ['field' => 'role']);
        }
    }

    private static function currentMembership(Organization $organization, User $target): OrganizationMembership
    {
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $target->id)->current()->first();
        if ($membership === null) {
            throw DomainError::notFound('member'); // the way into an organization is an invitation that proves the mailbox, never an id
        }

        return $membership;
    }

    /** I1: every permission of the role is one the grantor holds right now at the scope (or an ancestor of it). */
    private function assertGrantorHolds(Organization $organization, CommandContext $context, string $roleKey, CommandScope $scope): void
    {
        $actor = $this->grantor($context);
        if ($actor === null) {
            return;
        }
        $granted = self::permissionsOf($roleKey);
        if ($granted === null) {
            throw new DomainError('invalid_role', "Role {$roleKey} does not exist.", 422, ['field' => 'role']);
        }
        $missing = array_values(array_diff($granted, $this->authorizer->permissionsAt($actor, $scope)));
        if ($missing !== []) {
            throw new DomainError('role_above_own', 'A role can be granted only by somebody whose own role covers it.', 403, ['field' => 'role', 'missing' => array_slice($missing, 0, 5)]);
        }
    }

    /** I3: the actor holds everything the target's current role carries; a role nobody knows is covered by the owner alone. */
    private function assertCovers(Organization $organization, User|ServiceAccount $actor, string $targetRole, ?CommandScope $scope = null): void
    {
        $held = $this->authorizer->permissionsAt($actor, $scope ?? CommandScope::organization($organization->id));
        $theirs = self::permissionsOf($targetRole) ?? array_values(array_filter(PermissionCatalog::keys(), fn ($k) => PermissionCatalog::all()[$k]['audience'] === 'customer'));
        if (array_diff($theirs, $held) !== []) {
            throw new DomainError('member_above_own', 'You can change or remove only somebody whose role your own role covers.', 403, ['role' => $targetRole]);
        }
    }

    /**
     * The person whose rights a grant is compared with: null for the system (nothing to compare) and for staff acting for a
     * customer (IF-8 replaces that shortcut). An actor that cannot be found grants nothing.
     */
    private function grantor(CommandContext $context): User|ServiceAccount|null
    {
        if ($context->actorType === 'system') {
            return null;
        }
        if ($context->actorType === 'service_account') {
            return ServiceAccount::query()->find((string) $context->actorId) ?? throw DomainError::forbidden('Unknown actor.');
        }
        $user = User::query()->find((string) ($context->onBehalfOfUserId ?? $context->actorId));
        if ($user === null) {
            throw DomainError::forbidden('Unknown actor.');
        }

        return $user->is_staff ? null : $user;
    }
}
