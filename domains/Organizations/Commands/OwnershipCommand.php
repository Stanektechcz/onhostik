<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand as BaseOrganizationCommand;

/**
 * The organization's ownership, in two steps (permission program I4, S1-02; audit TD-9), dispatched by `op`:
 *  offer{user_id} · cancel{} — the owner (organization.close, owner-only; HIGH for a customer)
 *  accept{} · decline{} — the member it was offered to (organization.read; accepting is HIGH: the heir proves it is them)
 */
final class OwnershipCommand extends BaseOrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['offer', 'cancel', 'accept', 'decline'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return in_array($this->op(), ['accept', 'decline'], true) ? 'organization.read' : 'organization.close';
    }

    public function name(): string
    {
        return 'organization.ownership.'.$this->op();
    }

    public function riskLevel(): string
    {
        return in_array($this->op(), ['offer', 'accept'], true) ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
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
