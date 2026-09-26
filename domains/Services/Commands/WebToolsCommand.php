<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Configuration writes of the web toolkit that are not node operations: uptime monitors, the git deploy source
 * and its webhook secret, the backup schedule. payload: service_id, op, params{}.
 */
final class WebToolsCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['monitoring.set', 'monitoring.delete', 'deploy.configure', 'deploy.rotate_secret', 'deploy.disconnect', 'backup_schedule.set'];

    public function permission(): ?string
    {
        return 'service.manage';
    }

    public function name(): string
    {
        return 'service.tools.'.(string) $this->get('op', 'op');
    }

    public function riskLevel(): string
    {
        // disconnecting a repository keeps the deployed files, monitors are re-creatable. The one op that can destroy data — a
        // backup schedule that keeps fewer days or generations, which the next tick prunes to — asks `backup.delete` with a fresh
        // step-up of its own in WebToolsCommandHandler (TASK-0029 review round 2); keeping or raising them is managing
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
