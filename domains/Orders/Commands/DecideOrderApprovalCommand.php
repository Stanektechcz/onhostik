<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * payload: order_id, decision (approve|reject), reason? — the owner or a billing admin decides a credit order another member
 * placed (owner decision 20, TASK-0021). Approving spends the credit exactly as placing the order themselves would, so it
 * carries the same risk: NORMAL, no step-up.
 */
final class DecideOrderApprovalCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return CreditOrderPolicy::PERMISSION;
    }

    public function name(): string
    {
        return 'order.approval.decide';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
