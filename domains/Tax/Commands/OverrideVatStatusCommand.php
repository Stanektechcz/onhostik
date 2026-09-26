<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Finance sets an organization's VAT status by hand (TASK-0031, D31.5): VIES has been down for days, or the customer proves
 * the registration another way. Payload: `organization_id`, `status` valid|invalid, `reason`, `evidence`, `days` (how long it
 * counts, at most `onhost.vies.override_days`). It decides whether invoices are reverse-charged — money — so it is CRITICAL:
 * a fresh step-up and a second person, unless the platform runs with one operator (ONHOST_FOUR_EYES=false). It uses the tax
 * permission finance already holds; no new catalogue key.
 */
final class OverrideVatStatusCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'billing.tax_rule.manage';
    }

    public function name(): string
    {
        return 'tax.vat_status.override';
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
