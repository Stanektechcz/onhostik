<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * payload: user_id, reason — support resets another person's MFA (`iam.mfa.reset`, staff, HIGH). A customer OWNER is refused:
 * their account is the organization's, and a reset asked for over the phone is the classic takeover route (permission program
 * D21, TASK-0042); an owner who lost access is recovered through OwnerRecoveryCommand — second person, a week of notice, any
 * org_admin can cancel.
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
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
