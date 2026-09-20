<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff side of the loyalty programme: levels, manual awards, campaigns, the mission catalogue, held referral rewards and
 * streak discounts.
 *
 * Four of them move money: a level carries a promo-credit reward booked into the wallet, an award can carry a customer
 * over a level, a released referral pays its reward, an approved streak is a discount. The command used to call itself
 * NORMAL under a HIGH permission, so credit was handed out without a fresh proof of identity. Editing the catalogue of
 * missions or a campaign stays an ordinary operation.
 */
final class LoyaltyCommand extends GlobalCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'staff.customer.manage';
    }

    public function name(): string
    {
        return 'loyalty.'.$this->op();
    }

    public const MOVES_MONEY = ['award', 'levels', 'referral.review', 'streak.approve'];

    public function riskLevel(): string
    {
        return in_array($this->op(), self::MOVES_MONEY, true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->op(), self::MOVES_MONEY, true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
