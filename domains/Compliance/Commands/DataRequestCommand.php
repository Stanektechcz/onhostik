<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Commands;

use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Platform\Commands\OrganizationCommand;

/**
 * The customer's GDPR / Data Act requests, dispatched by `op`:
 *  request{kind: export|deletion|switching, reason?} · cancel{data_request_id}
 *
 * Erasing the account is the one request that cannot be taken back, and it went straight to the service with the
 * permission to edit the organization's profile — any administrator could have the owner locked out of their own
 * account half an hour later. It now takes what the catalogue always said it takes: `organization.close`, which only
 * the owner holds, and a fresh step-up. Not a second person: an organization of one has nobody to ask, and the right
 * to erasure cannot depend on having a colleague. What stands in for the second look is time — the erasure waits a
 * grace period (`onhost.compliance.deletion_grace_days`) in which anybody who manages the organization can stop it.
 */
final class DataRequestCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const OPS = ['request', 'cancel'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): string
    {
        // stopping an erasure is a way back, and needs no more than managing the organization; going through with one does
        return $this->erasure() ? 'organization.close' : 'organization.manage';
    }

    public function name(): string
    {
        return 'compliance.data_request.'.$this->op();
    }

    public function riskLevel(): string
    {
        return $this->erasure() ? PermissionCatalog::HIGH : PermissionCatalog::NORMAL;
    }

    public function requiresStepUp(): bool
    {
        return $this->erasure();
    }

    public function requiresApproval(): bool
    {
        return false;
    }

    private function erasure(): bool
    {
        return $this->op() === 'request' && $this->get('kind') === 'deletion';
    }
}
