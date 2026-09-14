<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/** payload: order_id, decision (release|reject), reason? — a staff decision on an order held by the intake pre-check (audit §5f-8) */
final class ReviewOrderCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): ?string
    {
        return 'staff.order.manage';
    }

    public function name(): string
    {
        return 'order.review';
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
