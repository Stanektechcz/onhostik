<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Partner-portal actions on the partner's own organization, dispatched by `op`:
 *  apply{company?,clients?,site?,note?,model?} · payout.request{amount,iban,method?} · whitelabel{domain?,hide_brand?,own_mail?,own_prices?,own_support?}
 */
final class PartnerPortalCommand extends OrganizationCommand
{
    protected const AUDIT_STRIP = ['password', 'secret', 'token', 'iban'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'organization.manage';
    }

    public function name(): string
    {
        return 'partner.portal.'.$this->op();
    }
}
