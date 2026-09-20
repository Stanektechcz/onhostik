<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/**
 * payload: order_id, reason? — the customer cancels an order nobody paid yet. It asks for the permission that placed the
 * order: reading the organization (the old check) let a read-only member or an auditor cancel the orders of others.
 */
final class CancelOrderCommand extends OrganizationCommand
{
    public function permission(): string
    {
        return 'catalog.order.create';
    }

    public function name(): string
    {
        return 'order.cancel';
    }
}
