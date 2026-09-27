<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Listeners;

use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * `onhost.organization.member.removed` / `onhost.organization.member.role_changed` (TASK-0035, permission program IF-15, audit
 * G1/G11): the doors into the organization that are not the panel go with the membership. A Discord link and an action hook
 * are credentials of one person; the membership was deleted and they went on working (the link read every service, the hook
 * waited, enabled, for its creator to be let back in).
 *
 * Removed: every link and every hook of the person in the organization. Role changed: what the new role could not have made —
 * a link needs `organization.manage`, a hook the permission of its own action. Both are idempotent (the outbox may deliver
 * twice), audited as the system, and never delete: a switched-off hook stays in the owner's list with the reason. The pending
 * invitations the person sent go the same way (a withdrawn link, GrantPolicy::backs).
 */
final class RevokeMemberSideDoors
{
    public function __construct(private readonly DiscordService $discord, private readonly ActionHookService $hooks, private readonly OrganizationService $organizations) {}

    public function handle(OutboxMessage $message): void
    {
        $organizationId = (string) $message->aggregate_id;
        $userId = (string) data_get($message->payload, 'user_id', '');
        if ($organizationId === '' || $userId === '') {
            return;
        }
        $removed = $message->name === 'organization.member.removed';
        $context = CommandContext::system($removed ? 'member removed' : 'member role changed');
        foreach ($this->discord->orphans($organizationId, $userId, withPending: true) as $orphan) {
            $this->discord->revoke($orphan['link'], $removed ? 'member_removed' : $orphan['reason'], $context);
        }
        foreach ($this->hooks->orphans($organizationId, $userId) as $orphan) {
            $this->hooks->disable($orphan['hook'], $removed ? 'member_removed' : $orphan['reason'], $context);
        }
        // red-team round of the Phase-0 chain (I6): the links the person sent and could no longer send — all of them after a
        // removal, those above the new role after a demotion. An admin's second mailbox was their way back in.
        $organization = Organization::query()->find($organizationId);
        if ($organization !== null) {
            $this->organizations->revokeUnbackedInvitations($organization, $userId, $removed ? 'member_removed' : 'role_changed', $context);
        }
    }
}
