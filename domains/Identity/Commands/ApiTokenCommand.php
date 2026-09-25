<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Platform\Commands\OrganizationCommand;

/** op: create{name, scopes[], expires_in_days?} · revoke{token_id}. Tokens are personal, scoped to an organization and to documented abilities. */
final class ApiTokenCommand extends OrganizationCommand implements RiskAwareCommand
{
    /** The scopes a token can be issued with — owned by the one permission → scope map (C13-H2c). */
    public const SCOPES = TokenScopes::ALL;

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return 'api_token.manage';
    }

    public function name(): string
    {
        return 'api_token.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return $this->op() === 'create';
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
