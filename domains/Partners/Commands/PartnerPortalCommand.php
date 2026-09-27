<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Partner-portal actions on the partner's own organization, dispatched by `op`:
 *  apply{company?,clients?,site?,note?,model?} · payout.request{amount,iban?,method?} · payout_account.set{iban} ·
 *  payout_account.cancel{} · whitelabel{domain?,hide_brand?,own_mail?,own_prices?,own_support?} · change.request{kind,value,note?} ·
 *  model.request{model,note?}
 */
final class PartnerPortalCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['apply', 'payout.request', 'payout_account.set', 'payout_account.cancel', 'whitelabel', 'change.request', 'model.request'];

    protected const AUDIT_STRIP = ['password', 'secret', 'token', 'iban'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    /** TASK-0040: where commissions are paid is the owner's decision alone (PermissionCatalog::PARTNER_OWNER_ONLY). */
    public function permission(): ?string
    {
        return str_starts_with($this->op(), 'payout_account.') ? 'partner.payout_account.manage' : 'organization.manage';
    }

    public function name(): string
    {
        return 'partner.portal.'.$this->op();
    }

    /**
     * Money leaving the platform and the account it goes to take a fresh step-up (TASK-0040, audit TD-4: a payout request
     * was an ordinary action, and it carried the IBAN). The account change is HIGH by its permission as well.
     */
    public function riskLevel(): string
    {
        return in_array($this->op(), ['payout.request', 'payout_account.set', 'payout_account.cancel'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->riskLevel() === PermissionCatalog::HIGH;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
