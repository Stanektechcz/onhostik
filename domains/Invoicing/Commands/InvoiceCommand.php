<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Invoice operations, dispatched by `op`:
 *  pay_from_wallet{invoice_id} (customer, postpaid invoices) · credit_note{invoice_id,reason,line_ids?,incident_ref?} (staff) ·
 *  mark_paid{invoice_id,method,reference} (staff, manual bank match)
 */
final class InvoiceCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['pay_from_wallet', 'pay_by_bank', 'pay_by_card', 'credit_note', 'mark_paid'];

    private const CUSTOMER_OPS = ['pay_from_wallet', 'pay_by_bank', 'pay_by_card'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return in_array($this->op(), self::CUSTOMER_OPS, true) ? 'billing.wallet.topup' : 'billing.invoice.manage';
    }

    public function name(): string
    {
        return 'invoice.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), self::CUSTOMER_OPS, true) ? PermissionCatalog::NORMAL : PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return ! in_array($this->op(), self::CUSTOMER_OPS, true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
