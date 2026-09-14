<?php

declare(strict_types=1);

namespace Onhost\Domain\Marketplace\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Marketplace actions on the acting organization, dispatched by `op`:
 *  customer — order{listing_id,brief,service_id?} · accept{order_id} · dispute{order_id,reason} · cancel{order_id}
 *  partner  — listing.create{key,title,…} · listing.update{listing_id,…} · listing.state{listing_id,state} · order.start{order_id} · order.deliver{order_id,note}
 * Ordering spends credit and is a step-up action like every money movement.
 */
final class MarketplaceCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'order', 'accept', 'dispute', 'cancel' => 'catalog.order.create',
            default => 'organization.manage',
        };
    }

    public function name(): string
    {
        return 'marketplace.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->op() === 'order' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'order';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
