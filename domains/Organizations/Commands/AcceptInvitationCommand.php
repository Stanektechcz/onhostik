<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Platform\Commands\GlobalCommand;

/**
 * payload: token — the invitation link is clicked (permission program I8, S1-02; audit TD-7). The only way into an organization
 * runs through the bus like every other grant: audited as the person who clicked, idempotent, and re-checking at that moment
 * that whoever sent the link could still send it (GrantPolicy::backs). Before, the controller called the service directly —
 * nothing in the audit trail said who joined which organization by which link. The token never reaches the audit (AUDIT_STRIP).
 * Any signed-in person may click a link addressed to them; the organization is the link's, not the caller's.
 */
final class AcceptInvitationCommand extends GlobalCommand
{
    public function permission(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'organization.invitation.accept';
    }
}
