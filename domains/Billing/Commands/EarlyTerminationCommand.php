<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Owner decision E3 (2026-10-15): the whole unused months of a terminated, prepaid (yearly) service come back as credit notes
 * (EarlyTerminationPolicy). Money leaves the books: HIGH risk, fresh step-up.
 */
final class EarlyTerminationCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): ?string
    {
        return 'service.delete';
    }

    public function name(): string
    {
        return 'billing.early_termination';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
