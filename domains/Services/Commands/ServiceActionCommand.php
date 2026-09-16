<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * payload: service_id, action (power|suspend|resume|resize|terminate|backup|restore|snapshot|rollback_snapshot), params{}
 * Destructive actions are HIGH risk: fresh step-up required; nothing here is ever auto-approved for AI actors.
 */
final class ServiceActionCommand extends OrganizationCommand implements RiskAwareCommand
{
    /** The service is the resource; its project rides along so a project-scoped developer may act on it. */
    public function scope(): ?CommandScope
    {
        $serviceId = $this->get('service_id');
        $projectId = $this->get('project_id');

        return is_string($serviceId) && $serviceId !== ''
            ? CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null)
            : CommandScope::organization($this->organizationId);
    }

    public function permission(): ?string
    {
        return match ((string) $this->get('action')) {
            'terminate', 'purge' => 'service.delete',
            'restore', 'rollback_snapshot' => 'backup.restore',
            default => 'service.manage',
        };
    }

    public function name(): string
    {
        return 'service.'.(string) $this->get('action', 'action');
    }

    public function riskLevel(): string
    {
        return in_array((string) $this->get('action'), ['terminate', 'purge', 'restore', 'rollback_snapshot', 'resize', 'reinstall', 'panel.password'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array((string) $this->get('action'), ['terminate', 'purge', 'restore', 'rollback_snapshot', 'reinstall', 'panel.password'], true); // a reinstall wipes the server, the panel password opens every server of the account
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
