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
        return PermissionCatalog::NORMAL; // nothing here destroys data: disconnecting a repository keeps the deployed files, monitors and schedules are re-creatable
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
