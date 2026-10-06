<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * The owner's way into their Penpot (TASK-0123). payload: service_id, project_id, op `owner.password`, password.
 *
 * A Penpot account opens every design of the instance, so setting its password is what a password of a game panel account is
 * to a game server: `service.console` on the service, HIGH with a fresh step-up. The password never reaches the audit
 * (AUDIT_STRIP) or the bus's stored answer.
 */
final class PenpotAccessCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['owner.password'];

    /**
     * The owner, an organization admin or whoever holds the service's console (security review of PR #119, M3): the Penpot
     * account opens every design of the instance, as a shell opens a site — `svc_manage` ("without a shell") does not reach it.
     */
    public const PERMISSION = 'service.console';

    public function scope(): CommandScope
    {
        $serviceId = $this->get('service_id');
        $projectId = $this->get('project_id');

        return is_string($serviceId) && $serviceId !== ''
            ? CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null)
            : CommandScope::organization($this->organizationId);
    }

    public function permission(): string
    {
        return self::PERMISSION;
    }

    public function name(): string
    {
        return 'penpot.'.(string) $this->get('op', 'owner.password');
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
