<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Writes the seller's VAT mode to the legal entity (G2): `payer` true|false, `reason`. The mode decides whether every following
 * document states VAT and is a tax document — money and tax law — so it is CRITICAL like the other tax settings: a fresh step-up
 * and a second person (security review of #106: the command line used to run it as the system, with neither). Staff send it
 * through `POST /v1/staff/tax/vat-payer-mode`; the idempotency key is the approval it consumes. Handled by
 * SetVatPayerModeCommandHandler (the bus's naming convention).
 */
final class SetVatPayerModeCommand extends GlobalCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'billing.tax_rule.manage';
    }

    public function name(): string
    {
        return 'tax.vat_payer_mode.set';
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
