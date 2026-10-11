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
 *  model.decide{request_id,decision,note?} · masking.notice{partner_id} ·
 *  commissions.mature{} (R7: pending commissions whose 30 days passed become payable — the hourly job, as the system)
 */
final class PartnerCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['approve', 'state', 'payout.approve', 'payout.reject', 'payout.pay', 'payout.freeze', 'payout.unfreeze', 'tiers.recompute', 'model.decide', 'masking.notice', 'commissions.mature'];

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
     * payout, even one they had approved themselves (audit P8, TD-4). The rest keeps the floor of `partner.manage` (HIGH). Maturing commissions (R7) moves no money — the payout of what matured is
     * still the CRITICAL payment — so it stays at that floor.
     */
    public function riskLevel(): string
    {
        return $this->movesMoney() ? PermissionCatalog::CRITICAL : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->movesMoney();
    }

    public function requiresApproval(): bool
    {
        return $this->movesMoney();
    }

    /**
     * The payment, and the release of a hold that confirms where the money goes (TASK-0041, P0-16 red team; owning task
     * TASK-0040): `payout.unfreeze` with `confirms_account` lets a payout go to an IBAN that was not the partner's confirmed
     * account. One person holding `partner.manage` used to freeze and unfreeze it and so decide alone where the money went. It
     * takes a second person now (the sole operator waits the time lock); a plain release confirms nothing and stays as it was.
     */
    private function movesMoney(): bool
    {
        return $this->op() === 'payout.pay' || ($this->op() === 'payout.unfreeze' && filter_var($this->get('confirms_account', false), FILTER_VALIDATE_BOOLEAN));
    }
}
