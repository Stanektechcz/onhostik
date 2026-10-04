<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\ServiceAccounts;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * An organization's service account and its tokens (TASK-0079, audit 2026-10 package D6).
 *
 * op: create{name, description?, role, scopes[], expires_in_days?, token_name?} · update{account_id, name?, description?} ·
 * delete{account_id} · issue_token{account_id, name, scopes[], expires_in_days?} · revoke_token{account_id, token_id}.
 *
 * Every op is a credential decision: HIGH with a fresh step-up (the catalogue floor of `api_token.manage` says so as well), and
 * the owner's alone — the handler refuses anybody else, an organization admin included (ServiceAccountCommandHandler::owner).
 * The handler is found by name (CommandBus: `<Command>Handler`).
 */
final class ServiceAccountCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        return 'api_token.manage';
    }

    public function name(): string
    {
        return 'service_account.'.$this->op();
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    public function requiresApproval(): bool
    {
        return false;
    }
}
