<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\GlobalCommand;

/**
 * Operations decide about a capacity request (audit §5n-7): `decide{request_id, decision: approve|cancel|delivered|retry,
 * note?, node_name?}`. An approval may order a node from a vendor, so the command is HIGH risk with a fresh step-up.
 */
final class CapacityCommand extends GlobalCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op', 'decide');
    }

    public function permission(): ?string
    {
        return 'capacity.manage';
    }

    public function name(): string
    {
        return 'capacity.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->get('decision'), ['approve', 'retry'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return in_array($this->get('decision'), ['approve', 'retry'], true);
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
