<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Staff side of paid work on a ticket (Brain card H29), dispatched by `op`:
 *  propose{ticket_id, scope, description, price_net, minutes?} · withdraw{offer_id, reason} · complete{offer_id}
 *
 * `complete` bills the customer, but only what the customer approved: the amount is not a parameter. That is why a
 * support agent may run it without the permissions that move money at will (H348).
 */
final class WorkOfferStaffCommand extends GlobalCommand implements RiskAwareCommand
{
    public const OPS = ['propose', 'withdraw', 'complete'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'support.ticket.manage';
    }

    public function name(): string
    {
        return 'ticket.work_offer.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::NORMAL;
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
