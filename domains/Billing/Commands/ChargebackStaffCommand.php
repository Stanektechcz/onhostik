<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Support decides a chargeback request (`decide`): the amount is never theirs to choose, it follows the share in force.
 * The share itself (`settings`) is money policy for every customer at once, so it belongs to finance, behind a fresh
 * step-up — a role that services a web site or a game server must not be able to move it (Brain card H348).
 */
final class ChargebackStaffCommand extends GlobalCommand implements RiskAwareCommand
{
    public const DECIDE = 'staff.chargeback.decide';

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        // B6 (audit 2026-10; TASK-0043): a decision is the support desk's (`staff.chargeback.decide`), no longer every role that may
        // restart any service; the clustering run (`analyse`) stays with the service teams
        return match ($this->op()) {
            'settings' => 'billing.credit.adjust',
            'decide' => self::DECIDE,
            default => 'staff.service.manage',
        };
    }

    public function name(): string
    {
        return 'chargeback.staff.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'settings' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'settings';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
