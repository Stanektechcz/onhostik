<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Sharing one service with another person, dispatched by `op`:
 *  share{service_id, email, capabilities[], access_until?, note?} · revoke{service_id, grant_id}
 *
 * It lets somebody new into the organization's resources, so it takes the right that manages members (HIGH: a fresh
 * proof of identity) — not the right that manages the service. A developer can work on a service; who else may is the
 * owner's decision.
 */
final class ServiceAccessCommand extends OrganizationCommand
{
    public const OPS = ['share', 'revoke'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'organization.members.manage';
    }

    public function name(): string
    {
        return 'service.access.'.$this->op();
    }
}
