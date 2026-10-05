<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\CustomIso;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Keeps an uploaded, scanned image in the organization's library (TASK-0110). payload: service_id, project_id, token (what
 * `CustomIsoLibrary::stage()` handed out — never a path).
 *
 * Managing the server, NORMAL risk: an image in the library does nothing until it is attached, and attaching it is the console's
 * (`iso.attach` asks `service.console`, as a rescue system does).
 */
final class UploadCustomIsoCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function scope(): CommandScope
    {
        $serviceId = (string) $this->get('service_id', '');
        $projectId = $this->get('project_id');

        return $serviceId !== ''
            ? CommandScope::resource($serviceId, $this->organizationId, is_string($projectId) && $projectId !== '' ? $projectId : null)
            : CommandScope::organization($this->organizationId);
    }

    public function permission(): string
    {
        return 'service.manage';
    }

    public function name(): string
    {
        return 'service.iso.upload';
    }

    public function riskLevel(): string
    {
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
