<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff partner administration, dispatched by `op`:
 *  approve{partner_id} · state{partner_id,state,reason} · payout.approve{payout_id} · payout.reject{payout_id,reason} ·
 *  payout.pay{payout_id,reference} · tiers.recompute{}
 */
final class PartnerCommand extends GlobalCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'partner.manage';
    }

    public function name(): string
    {
        return 'partner.'.$this->op();
    }

    /** Paying out money needs a fresh step-up; the rest is routine administration. */
    public function riskLevel(): string
    {
        return $this->op() === 'payout.pay' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'payout.pay';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
