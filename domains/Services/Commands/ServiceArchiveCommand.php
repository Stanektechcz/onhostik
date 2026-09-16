<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * What the customer does with the archive of a cancelled service (audit §5ab).
 * payload: op (download|restore), backup_id, service_id (restore target).
 */
final class ServiceArchiveCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'backup.restore';
    }

    public function name(): string
    {
        return 'service.archive.'.(string) $this->get('op', 'op');
    }

    public function riskLevel(): string
    {
        return (string) $this->get('op') === 'restore' ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    /** A restore writes over the files of a live service; a download only costs the fee. */
    public function requiresStepUp(): bool
    {
        return (string) $this->get('op') === 'restore';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
