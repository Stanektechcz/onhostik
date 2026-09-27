<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * A staff account with a global role, asked for from the command line (`onhost:staff:create`): payload {email, name, role,
 * password}. TASK-0041 (P0-16 red team, owning task TASK-0037, permission program IF-10): the account used to be written
 * straight into the database. A further holder of `iam.approval.decide` is a second person for every critical action, so its
 * making is CRITICAL — from the command line, where nobody signed in and nobody can be asked, it waits the time lock
 * (StaffAccountCommandHandler). The password never reaches the audit trail or the approval (AUDIT_STRIP).
 */
final class StaffAccountCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'iam.role.manage';
    }

    public function name(): string
    {
        return 'identity.staff.create';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::CRITICAL;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return true;
    }
}
