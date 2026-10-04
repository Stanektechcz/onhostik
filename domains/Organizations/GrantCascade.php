<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations;

use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\Project;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * What happens to the grants of somebody who lost the right to give them (permission program I6, S1-02; audit TD-6).
 *
 * A removed or demoted admin's grants used to run on as if nothing had happened: the memberships, project roles and shares they
 * handed out while they could. Two kinds, two answers:
 *  · PENDING — a share still waiting for its address — is cancelled at once, always: nobody has it yet, so nobody loses anything
 *    (the pending invitations go the same way in OrganizationService::revokeUnbackedInvitations);
 *  · ACTIVE — somebody works with it today — is recorded (`organization.grant.cascade.flag`, one audit row per grant, listed by
 *    `operator:grants:cascade --dry-run`) and revoked only when the operator switched `onhost.grants.cascade_enabled` on. The
 *    program names the risk: a colleague who onboarded the whole team leaves and the team is locked out. Existing customers are
 *    never changed en masse without a default-off switch (owner rule, principle 11); a revocation here takes an access snapshot
 *    first like every removal (I10), so a wrong one is one restore away.
 * An ownership transfer is no loss: the previous owner stays an org_admin and the organization keeps what they gave.
 */
final class GrantCascade
{
    public const SWITCH = 'onhost.grants.cascade_enabled';

    public function __construct(
        private readonly GrantPolicy $policy,
        private readonly OrganizationService $organizations,
        private readonly ProjectService $projects,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config(self::SWITCH, false);
    }

    /** @return array{pending_cancelled: int, flagged: int, revoked: int} */
    public function onGrantorLoss(Organization $organization, string $grantorId, string $reason, CommandContext $context): array
    {
        $stats = ['pending_cancelled' => $this->cancelPendingShares($organization, $grantorId, $context), 'flagged' => 0, 'revoked' => 0];
        $dependents = $this->policy->dependents($organization, $grantorId);
        foreach ($dependents as $dependent) {
            if (self::enabled()) {
                $stats['revoked'] += $this->revoke($organization, $dependent, $grantorId, $reason, $context) ? 1 : 0;

                continue;
            }
            if ($this->alreadyFlagged($organization, $dependent, $grantorId)) {
                continue; // the outbox may deliver the loss twice: one record per grant
            }
            $this->audit->record($context->withScope($organization->id), 'organization.grant.cascade.flag', 'recorded', $dependent + ['grantor_id' => $grantorId, 'reason' => $reason], 'organization', $organization->id);
            $stats['flagged']++;
        }
        if ($stats['flagged'] + $stats['revoked'] > 0) {
            $this->outbox->publish(GenericEvent::of('organization.grants.unbacked', 'organization', $organization->id, [
                'grantor_id' => $grantorId, 'count' => $stats['flagged'] + $stats['revoked'], 'revoked' => $stats['revoked'] > 0,
                'kinds' => array_values(array_unique(array_column($dependents, 'kind'))), 'reason' => $reason,
            ], $organization->id));
        }

        return $stats;
    }

    /** Shares the grantor made that still wait for their address and that the grantor could not make now. */
    private function cancelPendingShares(Organization $organization, string $grantorId, CommandContext $context): int
    {
        $cancelled = 0;
        $pending = ServiceAccessGrant::query()->where('organization_id', $organization->id)->where('granted_by', $grantorId)->where('state', ServiceAccessGrant::PENDING)->get();
        foreach ($pending as $grant) {
            $service = Service::query()->find($grant->service_id);
            if ($service === null || $this->policy->backsShare($organization, $grant)) {
                continue;
            }
            app(ServiceAccessService::class)->revoke($organization, $service, $grant->id, $context);
            $cancelled++;
        }

        return $cancelled;
    }

    /** @param array{kind: string, user_id: string, role: string, ref: string, scope_id: ?string} $dependent */
    private function revoke(Organization $organization, array $dependent, string $grantorId, string $reason, CommandContext $context): bool
    {
        try {
            if ($dependent['kind'] === 'service_account_role') {
                // TASK-0044: a service account has no membership and no access snapshot — its binding goes, and the audit row below
                // keeps the role and the scope it had, which is what re-creates it
                PolicyBinding::query()->whereKey($dependent['ref'])->where('principal_type', 'service_account')->where('organization_id', $organization->id)->delete();
                $this->audit->record($context->withScope($organization->id), 'organization.grant.cascade.revoke', 'succeeded', $dependent + ['grantor_id' => $grantorId, 'reason' => $reason], 'organization', $organization->id);

                return true;
            }
            $user = User::query()->find($dependent['user_id']);
            if ($user === null) {
                return false;
            }
            match ($dependent['kind']) {
                'membership' => $this->organizations->removeMember($organization, $user, $context),
                'project_role' => ($project = Project::query()->where('organization_id', $organization->id)->find((string) $dependent['scope_id'])) !== null
                    ? $this->projects->removeMember($organization, $project, $user, $context) : null,
                default => ($service = Service::query()->where('organization_id', $organization->id)->find((string) $dependent['scope_id'])) !== null
                    ? app(ServiceAccessService::class)->revoke($organization, $service, $dependent['ref'], $context) : null,
            };
            $this->audit->record($context->withScope($organization->id), 'organization.grant.cascade.revoke', 'succeeded', $dependent + ['grantor_id' => $grantorId, 'reason' => $reason], 'organization', $organization->id);

            return true;
        } catch (Throwable $e) {
            report($e); // one grant that cannot be taken back does not stop the others; the dry-run still lists it

            return false;
        }
    }

    /**
     * Asked of the database by the grant and the grantor themselves (review round 1): a scan of the latest 500 flags missed the
     * earlier record in a busy organization and flagged the same grant again.
     *
     * @param  array{kind: string, ref: string}  $dependent
     */
    private function alreadyFlagged(Organization $organization, array $dependent, string $grantorId): bool
    {
        return AuditEvent::query()->where('organization_id', $organization->id)->where('action', 'organization.grant.cascade.flag')
            ->where('detail->ref', $dependent['ref'])->where('detail->grantor_id', $grantorId)->exists();
    }
}
