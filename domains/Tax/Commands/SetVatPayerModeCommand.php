<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Writes the seller's VAT mode to the legal entity (G2): `payer` true|false, `reason`. The mode decides whether every following
 * document states VAT and is a tax document — money and tax law — so it is CRITICAL like the other tax settings: a fresh step-up
 * and a second person for a member of staff. The operator runs it as `php artisan onhost:vat:payer-mode --apply`, which writes
 * what the configuration declares (ONHOST_VAT_PAYER). Handled by SetVatPayerModeCommandHandler (the bus's naming convention).
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
