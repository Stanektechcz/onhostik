<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Pay and restore (TASK-0025): the customer brings a cancelled service back inside its restore window and pays what it
 * owes from the organization's credit. Spending the credit needs `billing.wallet.spend` (the owner and the billing admin,
 * not every administrator or a guest of one service); the amount is the stated quote for the customer's own service, the
 * same risk as paying an invoice from the credit: NORMAL, no step-up.
 */
final class ReinstateServiceCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'billing.wallet.spend';
    }

    public function name(): string
    {
        return 'service.reinstate';
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
