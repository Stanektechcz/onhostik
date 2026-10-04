<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * payload: user_id, reason — support resets another person's MFA (`iam.mfa.reset`, staff, HIGH). A customer OWNER is refused:
 * their account is the organization's, and a reset asked for over the phone is the classic takeover route (permission program
 * D21, TASK-0042); an owner who lost access is recovered through OwnerRecoveryCommand — second person, a week of notice, any
 * org_admin can cancel.
 *
 * Review round 1 (TASK-0042): the same route one role below the owner. A staff account, or somebody who manages the members of a
 * customer organization (org_admin), is reset only after a second person approves (CRITICAL; the sole approver waits the time
 * lock) — one iam_admin was enough to hand a caller another company's administrator or a colleague's staff access.
 *
 * TASK-0044 (S1-07 red team): one role below that still. A developer with the console on every service, a billing admin with the
 * payment methods, a cloud operator who deletes VMs — reset on one iam_admin's word, and the caller held a shell or the money of a
 * company. So the second person is asked of anybody holding a HIGH or CRITICAL customer permission, or the console (NORMAL in
 * the catalogue, but a shell — TASK-0029, PA-05), through any live binding of theirs in any organization (holdsKeyWorthASecondPerson).
 */
final class MfaResetCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'iam.mfa.reset';
    }

    public function name(): string
    {
        return 'identity.mfa.reset';
    }

    public function riskLevel(): string
    {
        return $this->asksSecondPerson() ? PermissionCatalog::CRITICAL : PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return $this->asksSecondPerson();
    }

    /**
     * A staff account, or somebody holding a key worth a second person in any customer organization: a second person decides. An
     * owner is not asked about — the handler refuses them outright (the owner recovery), so no approval is opened for a request
     * that cannot run.
     */
    public function asksSecondPerson(): bool
    {
        $user = User::query()->find((string) $this->get('user_id', ''));
        if ($user === null || Organization::query()->where('owner_user_id', $user->id)->exists()) {
            return false;
        }

        return StaffActor::account($user) || self::holdsKeyWorthASecondPerson($user->id);
    }

    /**
     * TASK-0044: a HIGH or CRITICAL customer permission, or the console, through any live binding of the person in any organization
     * — the organization role, a project role, a single-service share. (Member management is HIGH: the org_admin of review round 1
     * is one case of this.)
     */
    public static function holdsKeyWorthASecondPerson(string $userId): bool
    {
        $catalog = PermissionCatalog::all();
        $roles = PolicyBinding::query()->where('principal_type', 'user')->where('principal_id', $userId)->whereNotNull('organization_id')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('role_key')->unique();
        foreach ($roles as $role) {
            foreach (GrantPolicy::permissionsOf((string) $role) ?? [] as $permission) {
                $entry = $catalog[$permission] ?? null;
                if ($permission === 'service.console' || ($entry !== null && $entry['audience'] === 'customer' && in_array($entry['risk'], [PermissionCatalog::HIGH, PermissionCatalog::CRITICAL], true))) {
                    return true;
                }
            }
        }

        return false;
    }
}
