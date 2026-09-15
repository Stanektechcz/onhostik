<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff work on a customer's account from the console (audit §5y):
 *   wallet.credit   — organization_id, amount, currency?, kind (manual|promo), note — a manual credit adjustment (HIGH, step-up)
 *   order.assisted  — organization_id, items, payment (wallet|bank|postpaid), commit_months?, note — an order placed on the
 *                     customer's behalf through the same quote and checkout as the panel
 */
final class StaffCustomerCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['wallet.credit', 'order.assisted'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return $this->op() === 'wallet.credit' ? 'billing.credit.adjust' : 'staff.order.manage';
    }

    public function name(): string
    {
        return 'staff.customer.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'wallet.credit' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'wallet.credit';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
