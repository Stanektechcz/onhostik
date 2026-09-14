<?php

declare(strict_types=1);

namespace Onhost\Domain\Integrations\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/** payload: op (discord.link_code | discord.unlink | hook.create | hook.delete), params{} */
final class IntegrationCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['discord.link_code', 'discord.unlink', 'hook.create', 'hook.delete'];

    public function permission(): ?string
    {
        return str_starts_with((string) $this->get('op'), 'hook.') ? 'service.manage' : 'organization.manage';
    }

    public function name(): string
    {
        return 'integration.'.(string) $this->get('op', 'op');
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
