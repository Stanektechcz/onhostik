<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * A consumer withdraws from a distance contract within fourteen days (TASK-0025): `service` for a delivered service (it is
 * switched off, the unused part comes back to the credit, the service is cancelled), `order` for a paid order nothing of
 * which was delivered. It ends a contract and moves money: the organization's `service.delete`, HIGH, a fresh step-up —
 * never an API token, never an assistant.
 */
final class WithdrawalCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'service.delete';
    }

    public function name(): string
    {
        return 'withdrawal.'.$this->op();
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
