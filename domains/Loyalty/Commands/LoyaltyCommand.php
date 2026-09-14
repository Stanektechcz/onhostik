<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/** Staff side of the loyalty programme: the level table (`levels`) and manual awards (`award`). */
final class LoyaltyCommand extends GlobalCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'staff.customer.manage';
    }

    public function name(): string
    {
        return 'loyalty.'.$this->op();
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
