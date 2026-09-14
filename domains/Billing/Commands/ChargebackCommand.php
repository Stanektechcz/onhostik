<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * The customer's side of a chargeback: `request` asks support to approve leaving a service early; `cancel`
 * (after the approval) terminates the service and returns the agreed share of the unused period as credit.
 * Cancelling destroys a running service: HIGH risk, fresh step-up.
 */
final class ChargebackCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return $this->op() === 'cancel' ? 'service.delete' : 'service.manage';
    }

    public function name(): string
    {
        return 'chargeback.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'cancel' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'cancel';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
