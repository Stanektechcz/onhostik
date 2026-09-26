<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Services\Models\Service;
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
     *
     * The project is the stored service's, never the payload's: a dispatcher that forwarded a caller's `project_id` would have
     * let a developer of another project into this console (TASK-0030 review round 1 — a customer value reaching the scope).
     * A service of another organization gets no project; the handler answers 404 for it.
     */
    public function scope(): CommandScope
    {
        $serviceId = $this->get('service_id');
        if (! is_string($serviceId) || $serviceId === '') {
            return CommandScope::organization($this->organizationId);
        }
        $projectId = Service::query()->whereKey($serviceId)->where('organization_id', $this->organizationId)->value('project_id');

        return CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null);
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
