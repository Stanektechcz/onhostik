<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** The customer's own loyalty actions: `referral.code` (allocate the invite code) and `missions.evaluate` (count what is done now). */
final class AccountLoyaltyCommand extends OrganizationCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return $this->op() === 'referral.code' ? 'organization.manage' : 'organization.read';
    }

    public function name(): string
    {
        return 'loyalty.account.'.$this->op();
    }
}
