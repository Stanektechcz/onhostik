<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * `loyalty.redeem` (owner decision G-R2): the customer asks to redeem points on their cart — `cart_id`, `points` (0 = none).
 * Whoever may place an order may choose the discount on it; nothing is spent until the order is paid, so the risk is NORMAL and
 * no step-up is asked (the same as placing the order).
 */
final class RedeemPointsCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'catalog.order.create';
    }

    public function name(): string
    {
        return 'loyalty.redeem';
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
