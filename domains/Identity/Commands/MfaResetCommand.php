<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\StaffActor;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OwnerRecoveries;
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
     * A staff account, or somebody who manages the members of a customer organization: a second person decides. An owner is not
     * asked about — the handler refuses them outright (the owner recovery), so no approval is opened for a request that cannot run.
     */
    public function asksSecondPerson(): bool
    {
        $user = User::query()->find((string) $this->get('user_id', ''));
        if ($user === null || Organization::query()->where('owner_user_id', $user->id)->exists()) {
            return false;
        }

        return StaffActor::account($user) || OwnerRecoveries::managedOrganizationIds($user->id) !== [];
    }
}
