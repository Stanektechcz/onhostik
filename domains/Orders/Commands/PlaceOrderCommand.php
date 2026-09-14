<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: quote_id, consents{key:{version?,person?}}, payment{mode,provider?,method?,return_urls?}, source */
final class PlaceOrderCommand extends OrganizationCommand
{
    public function permission(): ?string
    {
        return 'catalog.order.create';
    }

    public function name(): string
    {
        return 'order.place';
    }
}
