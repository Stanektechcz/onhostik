<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * payload: order_id, reason — staff cancel an order: an unpaid one, or a paid one nothing of which runs (held by the
 * review, failed). What was paid goes back where it came from and every tax document of the order is credited. It moves
 * no money out of the customer's hands, so it is an ordinary action of whoever services orders — with a reason.
 */
final class StaffCancelOrderCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'staff.order.manage';
    }

    public function name(): string
    {
        return 'order.cancel.staff';
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
