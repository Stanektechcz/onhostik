<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * H0 (owner decision H-R1): the organization's owner decides a request an API token opened — payload {approval_id, decision:
 * approved|rejected, note?}. A credential decision: `api_token.manage` (no token scope, so no token decides one) with a fresh
 * step-up; the handler asks for the owner or an administrator of the organization in a portal session (TokenApprovals::decide).
 */
final class TokenApprovalDecisionCommand extends OrganizationCommand implements RiskAwareCommand
{
    public function permission(): string
    {
        return 'api_token.manage';
    }

    public function name(): string
    {
        return 'iam.token_approval.decide';
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
