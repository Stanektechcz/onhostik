<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * Tells one paying web service that its plan's space will count as files + databases + mail together from a date
 * (TASK-0023 web-disk-total). Payload: `service_id`, `effective` (Y-m-d). Only the operator's command sends it
 * (`onhost:usage:disk-total-notice --send`, system actor); the handler refuses anybody else. Idempotency key:
 * `disk-total-notice:{service}:{effective}` — one notice per service and date.
 */
final class AnnounceDiskTotalCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): ?string
    {
        return null; // system-internal: no customer or staff permission reaches it (the handler checks the actor)
    }

    public function name(): string
    {
        return 'service.disk_total.announce';
    }

    /** A notice about a date the operator set; it changes nothing on the service yet. */
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
