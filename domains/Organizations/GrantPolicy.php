<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Authorization\RoleResolver;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * The one place that decides who may grant, change or remove which role for whom (permission program D1, P0-07).
 *
 * Every grant path used to decide for itself: `change_role` took any user id and made a stranger a member (TD-2), nothing
 * compared the role of the person removed or demoted with the one doing it (TD-5), project roles skipped the check
 * entirely (TD-3), and an accepted invitation could overwrite the owner's binding (TD-1). The invariants applied here
 * (program §3; I1–I4, I8, I9, I11 since P0-07, the full set I1–I12 since S1-01 / TASK-0042 — GrantMatrixTest proves every entry
 * point against every invariant):
 *  · I1  a grantor gives only what they effectively hold at the target scope or an ancestor;
 *  · I2  nobody grants to or changes themselves — leaving (removing yourself) is the one change of your own;
 *  · I3  nobody changes or removes somebody whose current role they do not cover;
 *  · I4  the owner binding is never lowered here — ownership moves by a transfer;
 *  · I8  a role change, a removal or a transfer targets a current member only; the way in is an invitation;
 *  · I9  accepting an invitation never lowers a current membership;
 *  · I11 a project role comes from an allow-list.
 * TASK-0042 (S1-01) wraps the rest around the same entry points and the ones that decided for themselves (the service share, the
 * API token, the staff account): a grant never outlives its grantor's own access (I5, clamped — no refusal); a grantor's loss
 * cancels what is pending and records — or, behind `onhost.grants.cascade_enabled`, revokes — what is active (I6, GrantCascade);
 * a share cannot be narrowed or revoked by somebody who could not have given it (I3); delegated access goes two levels deep at
 * most (I12); a staff account takes a staff role only (I11). The access snapshot before a removal (I10) is AccessSnapshots.
 * Unknown role keys fail closed: they grant nothing, and a member holding one is covered only by somebody who holds every
 * customer permission (the owner). The system actor (sweeps) is bound by the target invariants but has no grantor to compare.
 * Staff are compared by what they hold in the organization, never by a global binding (red-team round, audit SS-1); staff
 * tooling for customer memberships needs a staff permission and command of its own (P0-08, `StaffActor`, IF-8).
 * An invitation is a grant that has not landed yet: it counts only while its sender could still send it (backs(), I6).
 */
final class GrantPolicy
{
    /**
     * Roles a person can hold inside one project (I11). Organization-wide powers — the members, the money, the organization
     * itself — are not a project's to hand out: an `org_admin` "inside a project" managed the organization's members.
     */
    public const PROJECT_ROLES = ['developer', 'cloud_operator', 'game_operator', 'mail_manager', 'dns_manager', 'domain_manager', 'security_auditor', 'support_contact', 'viewer'];

    /** The invariants of program §3, one sentence each: GrantMatrixTest decides every one of them for every grant entry point. */
    public const INVARIANTS = [
        'I1' => 'A grantor gives only what they effectively hold at the target scope or an ancestor of it.',
        'I2' => 'Nobody grants to or changes themselves, except accepting or giving up their own access.',
        'I3' => 'Nobody changes, narrows or removes a grant whose current permissions they do not cover.',
        'I4' => 'The owner binding stays with organization.owner_user_id and moves only by a two-step transfer.',
        'I5' => 'A grant ends no later than the grantor\'s own access to what it grants.',
        'I6' => 'A grantor\'s loss cancels their pending grants and records (or, switched on, revokes) their active ones.',
        'I7' => 'Adding HIGH or CRITICAL permissions and changing an organization membership is HIGH: a fresh step-up.',
        'I8' => 'Changes and removals target current members only; the way in is an accepted invitation.',
        'I9' => 'Accepting an invitation never lowers a current membership.',
        'I10' => 'An access snapshot precedes every removal and role change; it is kept 90 days and restorable.',
        'I11' => 'A role comes from the allow-list of the scope it is given at.',
        'I12' => 'Delegated access is passed on at most two levels deep, within the resharer\'s own rights.',
    ];

    /** I12: how many share hops may stand between the organization and a person holding a single service. */
    public const MAX_SHARE_DEPTH = 2;

    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * Inviting (I1, I4, I5): the role must exist, be an organization role and be covered by the inviter; the membership it
     * offers ends no later than the inviter's own (returned — the caller stores that end, not the one asked for).
     */
    public function assertMayInvite(Organization $organization, CommandContext $context, string $roleKey, ?CarbonInterface $until = null): ?CarbonInterface
    {
        self::assertOrganizationRole($roleKey);
        if ($roleKey === 'owner') {
            throw new DomainError('owner_role_locked', 'The owner role is not granted here; ownership moves by a transfer.', 403, ['field' => 'role']);
        }
        $scope = CommandScope::organization($organization->id);
        $this->assertGrantorHolds($organization, $context, $roleKey, $scope);

        return $this->grantEnd($context, $scope, self::permissionsOf($roleKey) ?? [], $until);
    }

    /**
     * I5 for a role at the organization: the end a membership in `$roleKey` may have when `$context` gives it. The owner's own
     * membership never ends (attachMember refuses an end for it), so it is not clamped.
     */
    public function membershipEnd(Organization $organization, CommandContext $context, User $target, string $roleKey, ?CarbonInterface $until): ?CarbonInterface
    {
        if ($roleKey === 'owner' || $organization->owner_user_id === $target->id) {
            return $until;
        }

        return $this->grantEnd($context, CommandScope::organization($organization->id), self::permissionsOf($roleKey) ?? [], $until);
    }

    /**
     * I5 (program §3, audit TD-6): the end a grant of `$permissions` at `$scope` may have when `$context` gives it — the one asked
     * for, or the grantor's own end when theirs comes first (an absent end is "for ever", later than any). A member on access
     * until the 10th handed out memberships, project roles, shares and API tokens that ran on for good. Clamped, not refused:
     * what the grantor could give is given, until the moment they could no longer give it. The system has no end to compare.
     *
     * @param  list<string>  $permissions
     */
    public function grantEnd(CommandContext $context, CommandScope $scope, array $permissions, ?CarbonInterface $requested): ?CarbonInterface
    {
        $actor = $this->grantor($context);

        return $actor === null ? $requested : self::clamp($requested, $this->holdsUntil($actor, $scope, $permissions));
    }

    /** The earlier of two ends, where null is "no end". */
    public static function clamp(?CarbonInterface $requested, ?CarbonInterface $limit): ?CarbonInterface
    {
        if ($limit === null) {
            return $requested;
        }

        return $requested === null || $requested->greaterThan($limit) ? $limit : $requested;
    }

    /**
     * Until when `$actor` holds every one of `$permissions` at `$scope` through their own bindings in its organization (never a
     * global one, never an elevation): per permission the latest end of the bindings that carry it, then the earliest of those.
     * Null = no end. A permission not held at all ends now (I1 refuses such a grant before the end matters).
     *
     * @param  list<string>  $permissions
     */
    public function holdsUntil(User|ServiceAccount $actor, CommandScope $scope, array $permissions): ?CarbonInterface
    {
        if ($scope->organizationId === null || $permissions === []) {
            return null;
        }
        $rows = PolicyBinding::query()->where('principal_type', $actor instanceof ServiceAccount ? 'service_account' : 'user')->where('principal_id', (string) $actor->getAuthIdentifier())
            ->where('organization_id', $scope->organizationId)->whereIn('scope_type', ['organization', 'project', 'resource'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        $ends = []; // permission => ?Carbon, null = for ever; a permission not listed is not held
        foreach ($rows as $row) {
            if (! self::bindingCovers((string) $row->getAttribute('scope_type'), $row->getAttribute('scope_id'), $scope)) {
                continue;
            }
            $end = $row->getAttribute('expires_at');
            $end = $end instanceof CarbonInterface ? $end : null;
            foreach (self::permissionsOf((string) $row->getAttribute('role_key')) ?? [] as $permission) {
                $ends[$permission] = ! array_key_exists($permission, $ends) ? $end
                    : ($ends[$permission] === null || $end === null ? null : ($end->greaterThan($ends[$permission]) ? $end : $ends[$permission]));
            }
        }
        $until = null;
        foreach ($permissions as $permission) {
            if (! array_key_exists($permission, $ends)) {
                return now();
            }
            if ($ends[$permission] !== null && ($until === null || $ends[$permission]->lessThan($until))) {
                $until = $ends[$permission];
            }
        }

        return $until;
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

    /**
     * Ownership goes to a current member only (I8), and only the current owner gives it away (I4). `organization.close`
     * (CRITICAL) at the bus is not enough: a global staff binding carries it, and a second person approving the request made
     * anybody the owner of a customer's organization (P0-16 red team, TASK-0041). Nobody acts for the owner here either —
     * the person who asks and the person acted for are both the owner. The system (no grantor) is bound by I8 only.
     */
    public function assertMayTransferOwnership(Organization $organization, CommandContext $context, User $target): OrganizationMembership
    {
        $membership = self::currentMembership($organization, $target);
        if ($context->actorType === 'system') {
            return $membership;
        }
        $owner = (string) $organization->owner_user_id;
        $person = (string) ($context->onBehalfOfUserId ?? $context->actorId);
        if ($context->actorType !== 'user' || $owner === '' || $person !== $owner || (string) $context->actorId !== $owner) {
            throw new DomainError('owner_transfer_only', 'Only the owner of the organization transfers its ownership.', 403);
        }

        return $membership;
    }

    /**
     * A project role for a current member of the organization (I1, I2, I3, I5, I11). Returns the end the role may have: the one
     * asked for, or the grantor's own end at the project when it comes first (ProjectService clamps it to the membership too).
     */
    public function assertMayGrantProjectRole(Organization $organization, Project $project, CommandContext $context, User $target, string $roleKey, ?CarbonInterface $until = null): ?CarbonInterface
    {
        if (! in_array($roleKey, self::PROJECT_ROLES, true)) {
            throw new DomainError('invalid_role', "Role {$roleKey} cannot be assigned inside a project.", 422, ['field' => 'role', 'allowed' => self::PROJECT_ROLES]);
        }
        $actor = $this->grantor($context);
        if ($actor === null) {
            return $until;
        }
        if ($actor instanceof User && $target->id === $actor->id) {
            throw new DomainError('self_membership_locked', 'Nobody gives themselves a project role; ask another administrator.', 403);
        }
        $scope = CommandScope::project($project->id, $organization->id);
        $current = ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $target->id)->value('role_key');
        if (is_string($current)) {
            $this->assertCovers($organization, $actor, $current, $scope);
        }
        $this->assertGrantorHolds($organization, $context, $roleKey, $scope);

        return self::clamp($until, $this->holdsUntil($actor, $scope, self::permissionsOf($roleKey) ?? []));
    }

    /**
     * One service shared with a person (ServiceAccessService::share; I1, I2, I3, I5, I12). The rule of the team page, asked at the
     * service — and since TASK-0042 two more: somebody who could not have given what a person holds there does not narrow it by
     * sharing again with less (I3: an admin without the console took a colleague's console away that way), and a share passed on
     * from a share stops at MAX_SHARE_DEPTH (I12). Returns the end the share may have (I5: the sharer's own end at the service).
     *
     * @param  list<string>  $capabilities  capability keys of ServiceAccessService::CAPABILITIES (already normalised by the caller)
     */
    public function assertMayShareService(Organization $organization, Service $service, CommandContext $context, string $email, array $capabilities, ?CarbonInterface $until): ?CarbonInterface
    {
        $actor = $this->grantor($context);
        if ($actor === null) {
            return $until; // the platform acts under its own permissions (checked by the command)
        }
        if ($actor instanceof User && mb_strtolower((string) $actor->email) === mb_strtolower(trim($email))) {
            throw new DomainError('cannot_share_with_self', 'You already manage this service.', 422, ['field' => 'email']);
        }
        $scope = CommandScope::resource($service->id, $organization->id, $service->project_id);
        $held = $this->authorizer->customerPermissionsAt($actor, $scope);
        $granted = self::capabilityPermissions($capabilities);
        foreach ($granted as $permission) {
            if (! in_array($permission, $held, true)) {
                throw new DomainError('capability_above_own', "You cannot hand out {$permission}: you do not hold it on this service yourself.", 403, ['field' => 'capabilities']);
            }
        }
        if ($this->shareDepth($actor, $service) + 1 > self::MAX_SHARE_DEPTH) {
            throw new DomainError('reshare_too_deep', 'Access that was itself shared can be passed on only once; ask the organization to share it.', 403);
        }
        $open = ServiceAccessGrant::query()->where('service_id', $service->id)->where('email', mb_strtolower(trim($email)))->whereIn('state', [ServiceAccessGrant::PENDING, ServiceAccessGrant::ACTIVE])->first();
        if ($open !== null) {
            $this->assertCoversShare($held, $open);
        }

        return self::clamp($until, $this->holdsUntil($actor, $scope, $granted));
    }

    /** Taking a share back (I3): only somebody who could have given everything it carries. */
    public function assertMayRevokeShare(Organization $organization, Service $service, CommandContext $context, ServiceAccessGrant $grant): void
    {
        $actor = $this->grantor($context);
        if ($actor === null) {
            return;
        }
        $this->assertCoversShare($this->authorizer->customerPermissionsAt($actor, CommandScope::resource($service->id, $organization->id, $service->project_id)), $grant);
    }

    /**
     * Whether whoever shared `$grant` could still share it now (I6): a current member (a service account: an active one of this
     * organization) holding organization.members.manage and every permission of the share on its service. A share the platform
     * made (no sharer on record) has no grantor to lose.
     */
    public function backsShare(Organization $organization, ServiceAccessGrant $grant): bool
    {
        if ($grant->granted_by === null || $grant->granted_by === '') {
            return true;
        }
        $service = Service::query()->find($grant->service_id);
        if ($service === null) {
            return false;
        }

        return $this->grantorBacks($organization, (string) $grant->granted_by, CommandScope::resource($service->id, $organization->id, $service->project_id), self::capabilityPermissions(array_values((array) $grant->capabilities)));
    }

    /**
     * An API token for `$user` in the organization (I5, I8): only a current member's, ending no later than their membership. A
     * token of somebody on access until the 10th stayed valid for the 365 days asked — the membership's end took the bindings,
     * the token outlived them as a credential with an owner who was gone.
     */
    public function assertMayIssueToken(Organization $organization, User $user, CarbonInterface $expires): CarbonInterface
    {
        $membership = OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->first();
        if ($membership === null) {
            throw DomainError::notFound('member');
        }

        return self::clamp($expires, $membership->expires_at) ?? $expires;
    }

    /**
     * A staff account takes a staff role (I11 at global scope). `onhost:staff:create --role=owner` wrote a CUSTOMER role at global
     * scope: the owner line over every organization of the platform, reachable through the staff-reach shadow release (IF-4).
     */
    public static function assertStaffRole(string $roleKey): void
    {
        if (! RoleResolver::exists($roleKey) || in_array($roleKey, RoleCatalog::customerRoleKeys(), true) || RoleCatalog::isResourceRole($roleKey)) {
            throw new DomainError('invalid_role', "Role {$roleKey} is not a staff role; a staff account takes a staff role only.", 422, ['field' => 'role']);
        }
    }

    /**
     * I6 (program §3, audit TD-6): the active grants `$grantorId` gave in the organization that they could not give now — every
     * one after they left, those above their new role after a demotion. What counts as theirs: an organization or project role
     * whose binding names them as granter (for a membership that came through a link before TASK-0042 recorded the sender on the
     * binding: the accepted link they sent for that role), and a single-service share they made. The owner's grants, and the
     * platform's, are never here. GrantCascade records these, or revokes them behind `onhost.grants.cascade_enabled`.
     *
     * @return list<array{kind: 'membership'|'project_role'|'service_share', user_id: string, role: string, ref: string, scope_id: ?string}>
     */
    public function dependents(Organization $organization, string $grantorId): array
    {
        $found = [];
        $orgScope = CommandScope::organization($organization->id);
        $bindings = PolicyBinding::query()->where('principal_type', 'user')->where('organization_id', $organization->id)
            ->whereIn('scope_type', ['organization', 'project'])->where('principal_id', '!=', $grantorId)->where('role_key', '!=', 'owner')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        foreach ($bindings as $binding) {
            $principal = (string) $binding->getAttribute('principal_id');
            $role = (string) $binding->getAttribute('role_key');
            $grantedBy = (string) ($binding->getAttribute('granted_by') ?? '');
            if ($grantedBy !== $grantorId && ! ($grantedBy === $principal && $binding->getAttribute('scope_type') === 'organization' && $this->legacyLinkFrom($organization, $principal, $role, $grantorId))) {
                continue;
            }
            if ($binding->getAttribute('scope_type') === 'organization') {
                if ($organization->owner_user_id === $principal || ! OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $principal)->current()->exists()
                    || $this->grantorBacks($organization, $grantorId, $orgScope, self::permissionsOf($role) ?? [])) {
                    continue;
                }
                $found[] = ['kind' => 'membership', 'user_id' => $principal, 'role' => $role, 'ref' => (string) $binding->getKey(), 'scope_id' => $organization->id];

                continue;
            }
            $projectId = (string) $binding->getAttribute('scope_id');
            if ($this->grantorBacks($organization, $grantorId, CommandScope::project($projectId, $organization->id), self::permissionsOf($role) ?? [])) {
                continue;
            }
            $found[] = ['kind' => 'project_role', 'user_id' => $principal, 'role' => $role, 'ref' => (string) $binding->getKey(), 'scope_id' => $projectId];
        }
        $shares = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('granted_by', $grantorId)->where('state', ServiceAccessGrant::ACTIVE)->whereNotNull('user_id')->get();
        foreach ($shares as $grant) {
            if ($this->backsShare($organization, $grant)) {
                continue;
            }
            $found[] = ['kind' => 'service_share', 'user_id' => (string) $grant->user_id, 'role' => implode(',', (array) $grant->capabilities), 'ref' => $grant->id, 'scope_id' => $grant->service_id];
        }

        return $found;
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
     * Whether the person who sent an invitation could send it NOW (program I6, audit TD-6; red-team round of the Phase-0 chain).
     * A link used to be good for its seven days whoever sent it and whatever became of them: an org_admin invited a second
     * mailbox of their own as org_admin, was removed, clicked and was back. Asked when the link is clicked (fail closed) and by
     * the member listener, which withdraws what no longer holds. The sender must still be a current member (a service account:
     * an active one of this organization) who holds organization.members.manage — what inviting and sharing ask — and every
     * permission of the role (I1), all of it by their bindings IN this organization (customerPermissionsAt). No sender on
     * record, an unknown role or the owner role back nothing.
     */
    public function backs(Organization $organization, OrganizationInvitation $invitation): bool
    {
        $granted = $invitation->role_key === 'owner' ? null : self::permissionsOf((string) $invitation->role_key);
        $sender = self::sender($organization, $invitation);
        if ($granted === null || $sender === null) {
            return false;
        }
        $this->authorizer->forget($sender); // asked right after the change that may have taken it (the listener runs in that request)
        $held = $this->authorizer->customerPermissionsAt($sender, CommandScope::organization($organization->id));

        return in_array('organization.members.manage', $held, true) && array_diff($granted, $held) === [];
    }

    /** I5 at the click: the end the accepted membership may have — the link's, or the sender's own end now when it comes first. */
    public function acceptedEnd(Organization $organization, OrganizationInvitation $invitation): ?CarbonInterface
    {
        $sender = self::sender($organization, $invitation);
        $until = $invitation->access_expires_at;

        return $sender === null ? $until : self::clamp($until, $this->holdsUntil($sender, CommandScope::organization($organization->id), self::permissionsOf((string) $invitation->role_key) ?? []));
    }

    /** The invitation's sender while they still belong to the organization, else null. */
    private static function sender(Organization $organization, OrganizationInvitation $invitation): User|ServiceAccount|null
    {
        $id = (string) ($invitation->invited_by ?? '');
        if ($id === '') {
            return null;
        }
        $user = User::query()->find($id);
        if ($user !== null) {
            return OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->exists() ? $user : null;
        }
        $account = ServiceAccount::query()->find($id);

        return $account !== null && $account->organization_id === $organization->id ? $account : null;
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

    /** @param list<string> $capabilities @return list<string> what the capabilities of a share carry (RoleResolver, D6) */
    public static function capabilityPermissions(array $capabilities): array
    {
        $permissions = [];
        foreach ($capabilities as $capability) {
            $role = ServiceAccessService::CAPABILITIES[$capability] ?? null;
            $permissions = array_merge($permissions, $role === null ? [] : RoleResolver::grantable($role));
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Whether `$grantorId` could give `$permissions` at `$scope` now: a current member of the organization (a service account: an
     * active one of it) who holds organization.members.manage there and every one of the permissions — by their own bindings in
     * this organization, never a global one (customerPermissionsAt). The same question backs() asks of an invitation.
     *
     * @param  list<string>  $permissions
     */
    private function grantorBacks(Organization $organization, string $grantorId, CommandScope $scope, array $permissions): bool
    {
        $user = User::query()->find($grantorId);
        $grantor = $user !== null
            ? (OrganizationMembership::query()->where('organization_id', $organization->id)->where('user_id', $user->id)->current()->exists() ? $user : null)
            : ServiceAccount::query()->where('organization_id', $organization->id)->find($grantorId);
        if ($grantor === null) {
            return false;
        }
        $this->authorizer->forget($grantor); // asked right after the change that may have taken it
        $atOrganization = $this->authorizer->customerPermissionsAt($grantor, CommandScope::organization($organization->id));

        return in_array('organization.members.manage', $atOrganization, true) && array_diff($permissions, $this->authorizer->customerPermissionsAt($grantor, $scope)) === [];
    }

    /** A membership accepted before TASK-0042 names its member as granter: the link they accepted for that role names the sender. */
    private function legacyLinkFrom(Organization $organization, string $userId, string $roleKey, string $senderId): bool
    {
        $email = User::query()->whereKey($userId)->value('email');

        return is_string($email) && OrganizationInvitation::query()->where('organization_id', $organization->id)->where('email', mb_strtolower($email))
            ->where('role_key', $roleKey)->where('invited_by', $senderId)->whereNotNull('accepted_at')->exists();
    }

    /** I3 for a share: whoever narrows or revokes it holds every permission it carries on the service. @param list<string> $held */
    private function assertCoversShare(array $held, ServiceAccessGrant $grant): void
    {
        $theirs = self::capabilityPermissions(array_values(array_intersect((array) $grant->capabilities, array_keys(ServiceAccessService::CAPABILITIES))));
        if (array_diff($theirs, $held) !== []) {
            throw new DomainError('share_above_own', 'This person holds more on the service than you could give; ask somebody who holds it all.', 403, ['grant_id' => $grant->id]);
        }
    }

    /**
     * I12: how many share hops stand between the organization and `$actor` on this service — 0 for somebody whose rights there come
     * from an organization or project role, else one more than whoever shared it with them. Capped: a cycle in bad data ends.
     */
    private function shareDepth(User|ServiceAccount $actor, Service $service, int $guard = 0): int
    {
        $above = $service->project_id !== null ? CommandScope::project($service->project_id, $service->organization_id) : CommandScope::organization($service->organization_id);
        if ($guard > self::MAX_SHARE_DEPTH + 1) {
            return $guard; // a cycle in bad data counts as too deep
        }
        if (! $actor instanceof User || in_array('service.read', $this->authorizer->customerPermissionsAt($actor, $above), true)) {
            return 0;
        }
        $own = ServiceAccessGrant::query()->where('service_id', $service->id)->where('user_id', $actor->id)->where('state', ServiceAccessGrant::ACTIVE)->first();
        $from = $own?->granted_by !== null ? User::query()->find((string) $own->granted_by) : null;

        return $own === null ? 0 : 1 + ($from === null ? 0 : $this->shareDepth($from, $service, $guard + 1));
    }

    /** Authorizer::bindingCovers for a stored row: the organization, the project (or a resource inside it), the resource itself. */
    private static function bindingCovers(string $type, mixed $id, CommandScope $scope): bool
    {
        return match ($type) {
            'organization' => $scope->organizationId !== null && (string) $id === $scope->organizationId,
            'project' => $id !== null && (string) $id === $scope->projectId,
            'resource' => $scope->type === 'resource' && (string) $id === $scope->id,
            default => false,
        };
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
        $missing = array_values(array_diff($granted, $this->authorizer->customerPermissionsAt($actor, $scope)));
        if ($missing !== []) {
            throw new DomainError('role_above_own', 'A role can be granted only by somebody whose own role covers it.', 403, ['field' => 'role', 'missing' => array_slice($missing, 0, 5)]);
        }
    }

    /** I3: the actor holds everything the target's current role carries; a role nobody knows is covered by the owner alone. */
    private function assertCovers(Organization $organization, User|ServiceAccount $actor, string $targetRole, ?CommandScope $scope = null): void
    {
        $held = $this->authorizer->customerPermissionsAt($actor, $scope ?? CommandScope::organization($organization->id));
        $theirs = self::permissionsOf($targetRole) ?? array_values(array_filter(PermissionCatalog::keys(), fn ($k) => PermissionCatalog::all()[$k]['audience'] === 'customer'));
        if (array_diff($theirs, $held) !== []) {
            throw new DomainError('member_above_own', 'You can change or remove only somebody whose role your own role covers.', 403, ['role' => $targetRole]);
        }
    }

    /**
     * The person whose rights a grant is compared with: null for the system (nothing to compare). Staff are compared like
     * everybody else, by what they hold in THIS organization (Authorizer::customerPermissionsAt): a global binding is no
     * customer grant right (red-team round of the Phase-0 chain, audit SS-1 — `is_staff` returned null here and skipped
     * I1–I3). An actor that cannot be found grants nothing.
     */
    private function grantor(CommandContext $context): User|ServiceAccount|null
    {
        if ($context->actorType === 'system') {
            return null;
        }
        if ($context->actorType === 'service_account') {
            return ServiceAccount::query()->find((string) $context->actorId) ?? throw DomainError::forbidden('Unknown actor.');
        }

        return User::query()->find((string) ($context->onBehalfOfUserId ?? $context->actorId)) ?? throw DomainError::forbidden('Unknown actor.');
    }
}
