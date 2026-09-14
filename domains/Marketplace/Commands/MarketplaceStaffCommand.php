<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/** Staff curate the marketplace: `listing.state{listing_id,state,reason}` (publish, pause, retire) and `dispute.resolve{order_id,decision,reason}`. */
final class MarketplaceStaffCommand extends GlobalCommand implements RiskAwareCommand
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
        return 'marketplace.staff.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL; // curation and dispute decisions are audited staff actions (like chargeback decisions), not money movements of the staff member
    }

    public function requiresStepUp(): bool
    {
        return false;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
