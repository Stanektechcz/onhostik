<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/**
 * The customer's answer to an offer of paid work (Brain card H29). payload: offer_id, approve (bool), note?, author_name?
 *
 * Approving commits the organization to pay, so it takes the right that places orders — a member who may only write
 * tickets can read the offer and cannot accept it.
 */
final class WorkOfferDecisionCommand extends OrganizationCommand
{
    public function permission(): string
    {
        return (bool) $this->get('approve') ? 'catalog.order.create' : 'support.ticket.write';
    }

    public function name(): string
    {
        return 'ticket.work_offer.'.((bool) $this->get('approve') ? 'approve' : 'decline');
    }
}
