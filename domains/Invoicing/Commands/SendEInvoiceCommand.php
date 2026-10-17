<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * W7-1 (Peppol layer 1): hand one issued invoice of the organization to the configured e-invoice driver, payload
 * {invoice_id}. Medium risk (the permission catalog's `normal` level: no step-up, no second person) — the driver `null`
 * sends nothing, and layer 1 has no route and no automatic call; it is dispatched by staff tooling and tests only.
 */
final class SendEInvoiceCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): ?string
    {
        return 'billing.invoice.manage';
    }

    public function name(): string
    {
        return 'invoice.einvoice.send';
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
