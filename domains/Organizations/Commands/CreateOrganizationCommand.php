<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Platform\Commands\GlobalCommand;

/**
 * payload: name, type (person|company), ico?, dic?, vat_id?, billing_email?, street?, city?, postal_code?, country? —
 * a signed-in user without a customer profile creates one (audit §5z): the panel's order centre asks for it before the
 * first order, the user becomes the owner. Any authenticated person may; AI actors may not (unscoped command).
 */
final class CreateOrganizationCommand extends GlobalCommand
{
    public function permission(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'organization.create';
    }
}
