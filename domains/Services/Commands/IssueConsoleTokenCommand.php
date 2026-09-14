<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/** payload: service_id. Returns a short-lived console token; the provider credential never leaves the server. */
final class IssueConsoleTokenCommand extends OrganizationCommand
{
    public function permission(): ?string
    {
        return 'service.console';
    }

    public function name(): string
    {
        return 'service.console.token';
    }
}
