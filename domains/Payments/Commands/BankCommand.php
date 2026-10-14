<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Finance: record, download or import incoming bank-transfer lines and settle the proformas / top-ups they pay.
 * `bank.statement.import` (J-R7, M3) carries the uploaded statement file as `statement`; the audit keeps only its hash and size.
 */
final class BankCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['bank.line.record', 'bank.sync', 'bank.statement.import'];

    protected const AUDIT_STRIP = ['statement'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'billing.reconcile';
    }

    public function name(): string
    {
        return 'payments.'.$this->op();
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
