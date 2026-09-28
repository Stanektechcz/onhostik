<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * A lost customer owner recovered by support (permission program D21, TASK-0042), dispatched by `op`:
 *  open{organization_id, mode: mfa_reset|transfer, new_owner_user_id?, reason, ticket_ref} — CRITICAL: a second person approves
 *    it (the sole approver waits the time lock), and it still runs only after OwnerRecoveries::delayDays() of notice;
 *  complete{organization_id} — after the notice period (HIGH); cancel{organization_id} — support withdraws it (HIGH).
 * The organization's own admins cancel it with the OrganizationCommand op `cancel_owner_recovery`.
 */
final class OwnerRecoveryCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['open', 'complete', 'cancel'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'iam.mfa.reset';
    }

    public function name(): string
    {
        return 'identity.owner_recovery.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'open' ? PermissionCatalog::CRITICAL : PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return $this->op() === 'open';
    }
}
