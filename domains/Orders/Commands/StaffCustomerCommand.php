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
 *
 * Whoever services the customer may place the order; paying it is the customer's act — a proforma they pay themselves.
 * Spending their credit or putting the order on their invoice account moves their money without them, so those two
 * modes ask for the finance permission and a fresh step-up, exactly like a manual credit (Brain card H348).
 */
final class StaffCustomerCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['wallet.credit', 'order.assisted'];

    /** Payment modes of an assisted order that move the customer's money without the customer. */
    public const MONEY_MODES = ['wallet', 'postpaid'];

    public function movesMoney(): bool
    {
        return $this->op() === 'wallet.credit' || ($this->op() === 'order.assisted' && in_array((string) $this->get('payment', 'bank'), self::MONEY_MODES, true));
    }

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return $this->movesMoney() ? 'billing.credit.adjust' : 'staff.order.manage';
    }

    public function name(): string
    {
        return 'staff.customer.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->movesMoney() ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->movesMoney();
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
