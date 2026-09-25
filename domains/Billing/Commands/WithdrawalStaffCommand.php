<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Finance records a withdrawal the consumer sent by e-mail or letter (TASK-0025), with the day it was SENT — which may be
 * before today, and which decides both the deadline and the refund. Staff act without the consumer's step-up and may date
 * the notice back: a refund, CRITICAL, a fresh step-up and a second person (unless the platform runs with one operator).
 */
final class WithdrawalStaffCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'billing.refund.execute';
    }

    public function name(): string
    {
        return 'withdrawal.staff.record';
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
