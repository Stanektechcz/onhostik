<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff partner administration, dispatched by `op`:
 *  approve{partner_id} · state{partner_id,state,reason} · payout.approve{payout_id} · payout.reject{payout_id,reason} ·
 *  payout.pay{payout_id,reference} · payout.freeze{payout_id,reason} · payout.unfreeze{payout_id,reason} · tiers.recompute{} ·
 *  model.decide{request_id,decision,note?} · masking.notice{partner_id}
 */
final class PartnerCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['approve', 'state', 'payout.approve', 'payout.reject', 'payout.pay', 'payout.freeze', 'payout.unfreeze', 'tiers.recompute', 'model.decide', 'masking.notice'];

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

    /**
     * Paying out money is CRITICAL (TASK-0040, permission program §7 "payments, refunds, partner payouts"): a second person
     * signs the payment, the sole operator waits the time lock. It used to be HIGH — one person with a step-up paid any
     * payout, even one they had approved themselves (audit P8, TD-4). The rest keeps the floor of `partner.manage` (HIGH).
     */
    public function riskLevel(): string
    {
        return $this->op() === 'payout.pay' ? PermissionCatalog::CRITICAL : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'payout.pay';
    }

    public function requiresApproval(): bool
    {
        return $this->op() === 'payout.pay';
    }
}
