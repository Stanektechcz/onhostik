<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;

/** payload: service_id, project_id?. Returns a short-lived console token; the provider credential never leaves the server. */
final class IssueConsoleTokenCommand extends OrganizationCommand
{
    /**
     * The service is the resource, as for every other action on it. At the organization scope the one role that exists to
     * hand over a console — "Service: console", a binding on that single service — was refused the console it promises
     * (audit §4 "Scope bug in IssueConsoleTokenCommand"). An organization or project role still covers the service
     * (Authorizer::bindingCovers). `service.console` is NORMAL in the catalogue, so no step-up here.
     */
    public function scope(): CommandScope
    {
        $serviceId = $this->get('service_id');
        $projectId = $this->get('project_id');

        return is_string($serviceId) && $serviceId !== ''
            ? CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null)
            : CommandScope::organization($this->organizationId);
    }

    public function permission(): ?string
    {
        return 'service.console';
    }

    public function name(): string
    {
        return 'service.console.token';
    }
}
