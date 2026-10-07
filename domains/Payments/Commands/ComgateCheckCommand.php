<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * The administration's Comgate check (owner decision H-R8, 2026-10-07), dispatched by `op`:
 *  check{kind: connection|payment} — an authenticated read, or a 1 Kč TEST payment created and read back (HIGH: a fresh step-up);
 *  test_mode.set{enabled: true|false|null, reason} — the gateway's mode, overriding `COMGATE_TEST` (null = the deployment decides
 *  again). CRITICAL (a step-up and a second person): a production gateway in test mode hands out services for test cards, and a
 *  rehearsal in live mode takes real money.
 * Permission `provider.instance.manage` (a gateway is a provider connection of the platform). Audited by the bus.
 */
final class ComgateCheckCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['check', 'test_mode.set'];

    protected const AUDIT_STRIP = [];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'provider.instance.manage';
    }

    public function name(): string
    {
        return 'payments.comgate.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'check' ? PermissionCatalog::HIGH : PermissionCatalog::CRITICAL; // an unknown op is the strictest
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return $this->riskLevel() === PermissionCatalog::CRITICAL;
    }
}
