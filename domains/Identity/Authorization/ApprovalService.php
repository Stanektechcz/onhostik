<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Audit\HashChain;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;

/**
 * The second person of a critical action (four eyes).
 *
 * The gate always existed — `IdentityCommandAuthorizer` asks for an approved, unconsumed approval of exactly this
 * command (its name and the hash of its audited payload) decided by somebody other than the requester — but nothing
 * could ever create one. A critical action was therefore either impossible, or the command quietly declared itself
 * "high" instead and lost its second person.
 *
 * The way it works: whoever is refused with `approval_required` has a pending request opened for them (the refusal
 * carries its id); somebody else who may decide approvals — and who could take the action themselves — approves or
 * rejects it; the requester repeats the same request with `approval_ids: [id]` and the approval is consumed by it.
 * An approval is good for one command with one payload, once, for a day.
 */
final class ApprovalService
{
    public const PERMISSION = 'iam.approval.decide';

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly Redactor $redactor,
    ) {}

    /** Whether critical actions ask for a second person at all (off = one operator runs the platform alone; set on the server, not in the application). */
    public static function enabled(): bool
    {
        return (bool) config('onhost.identity.four_eyes', true);
    }

    /** Opens (or finds) the pending request for this command of this person. */
    public function request(Command $command, CommandContext $context): Approval
    {
        $hash = HashChain::hashPayload($command->toAudit());
        $existing = Approval::query()->where('action', $command->name())->where('payload_hash', $hash)->where('requested_by', (string) $context->actorId)
            ->where('state', 'pending')->where('expires_at', '>', now())->first();
        if ($existing !== null) {
            return $existing;
        }
        $scope = $command->scope();
        $approval = Approval::query()->create([
            'action' => $command->name(), 'organization_id' => $context->organizationId ?? $scope?->organizationId,
            'subject_type' => $scope === null || $scope->isGlobal() ? null : $scope->type, 'subject_id' => $scope?->id,
            // what the approver reads: the audited (redacted) payload, the permission it needs and where
            'payload' => ['command' => $this->redactor->redact($command->toAudit()), 'permission' => $command->permission(), 'scope' => $scope === null ? null : ['type' => $scope->type, 'id' => $scope->id, 'organization_id' => $scope->organizationId, 'project_id' => $scope->projectId]],
            'payload_hash' => $hash, 'requested_by' => (string) $context->actorId, 'reason' => self::reasonOf($command), 'state' => 'pending',
            'expires_at' => now()->addHours(max(1, (int) config('onhost.identity.approval_ttl_hours', 24))),
        ]);
        $this->audit->record($context, 'iam.approval.request', 'succeeded', ['approval_id' => $approval->id, 'action' => $approval->action, 'permission' => $command->permission()], 'approval', $approval->id);
        $this->outbox->publish(GenericEvent::of('iam.approval.requested', 'approval', $approval->id, [
            'approval_id' => $approval->id, 'action' => $approval->action, 'requested_by' => $approval->requested_by, 'requester' => (string) User::query()->whereKey($approval->requested_by)->value('name'),
            'reason' => $approval->reason, 'expires_at' => $approval->expires_at?->toIso8601String(),
        ], $approval->organization_id));

        return $approval;
    }

    public function decide(Approval $approval, User $decider, string $decision, ?string $note, CommandContext $context): Approval
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainError('decision_invalid', 'decision must be approved or rejected.', 422, ['field' => 'decision']);
        }
        if ($approval->state !== 'pending') {
            throw new DomainError('approval_not_pending', 'This request was already decided, used or has expired.', 409, ['state' => $approval->state]);
        }
        if ($approval->expires_at !== null && $approval->expires_at->isPast()) {
            $approval->forceFill(['state' => 'expired'])->save();

            throw new DomainError('approval_expired', 'This request has expired; the action has to be asked for again.', 409);
        }
        if ($approval->requested_by === $decider->id) {
            throw new DomainError('approval_own_request', 'A request is decided by somebody other than the person who made it.', 403);
        }
        // the second person is somebody who could take the action themselves — not merely somebody who may press "approve"
        $permission = (string) data_get($approval->payload, 'permission', '');
        if ($decision === 'approved' && $permission !== '' && ! $this->authorizer->can($decider, $permission, self::scopeOf($approval))) {
            throw new DomainError('approver_lacks_permission', "Approving this takes {$permission}, which you do not hold.", 403, ['permission' => $permission]);
        }
        if ($decision === 'rejected' && trim((string) $note) === '') {
            throw new DomainError('note_required', 'Say why the request is rejected.', 422, ['field' => 'note']);
        }
        $approval->forceFill(['state' => $decision, 'decided_by' => $decider->id, 'decided_at' => now(), 'decision_note' => $note === null ? null : mb_substr(trim($note), 0, 1000)])->save();
        $this->audit->record($context, 'iam.approval.decide', 'succeeded', ['approval_id' => $approval->id, 'action' => $approval->action, 'decision' => $decision, 'requested_by' => $approval->requested_by], 'approval', $approval->id);
        $this->outbox->publish(GenericEvent::of('iam.approval.decided', 'approval', $approval->id, [
            'approval_id' => $approval->id, 'action' => $approval->action, 'decision' => $decision, 'requested_by' => $approval->requested_by, 'decided_by' => $decider->id, 'decider' => (string) $decider->name, 'note' => $approval->decision_note,
        ], $approval->organization_id));

        return $approval;
    }

    /** Requests nobody decided in time. */
    public function expire(): int
    {
        return Approval::query()->where('state', 'pending')->where('expires_at', '<=', now())->update(['state' => 'expired']);
    }

    /**
     * What this person sees: every request when they may decide them, their own otherwise.
     *
     * @return Builder<Approval>
     */
    public function visibleTo(User $user): Builder
    {
        $query = Approval::query();
        if (! $this->authorizer->can($user, self::PERMISSION, CommandScope::global())) {
            $query->where('requested_by', $user->id);
        }

        return $query;
    }

    /**
     * Active staff who may decide approvals.
     *
     * @return Collection<int, User>
     */
    public static function deciders(): Collection
    {
        $roles = DB::table('role_permissions')->where('permission_key', self::PERMISSION)->pluck('role_key')->all();
        $ids = PolicyBinding::query()->where('principal_type', 'user')->where('scope_type', 'global')->whereIn('role_key', $roles)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('principal_id')->unique()->all();

        return User::query()->whereIn('id', $ids)->where('is_staff', true)->where('state', 'active')->get();
    }

    /**
     * @param  Collection<array-key, User>|null  $users  already loaded people, keyed by id
     * @return array<string,mixed>
     */
    public static function present(Approval $approval, ?Collection $users = null): array
    {
        $name = function (?string $id) use ($users): ?string {
            if ($id === null) {
                return null;
            }
            $known = $users?->get($id);
            $value = $known instanceof User ? $known->name : User::query()->whereKey($id)->value('name');

            return $value === null ? null : (string) $value;
        };
        $state = $approval->state === 'pending' && $approval->expires_at !== null && $approval->expires_at->isPast() ? 'expired' : $approval->state;

        return [
            'id' => $approval->id, 'action' => $approval->action, 'state' => $state, 'reason' => $approval->reason, 'organization_id' => $approval->organization_id,
            'permission' => data_get($approval->payload, 'permission'), 'payload' => data_get($approval->payload, 'command'),
            'requested_by' => ['id' => $approval->requested_by, 'name' => $name($approval->requested_by)], 'decided_by' => $approval->decided_by === null ? null : ['id' => $approval->decided_by, 'name' => $name($approval->decided_by)],
            'decision_note' => $approval->decision_note, 'created_at' => $approval->created_at?->toIso8601String(), 'decided_at' => $approval->decided_at?->toIso8601String(),
            'expires_at' => $approval->expires_at?->toIso8601String(), 'consumed_at' => $approval->consumed_at?->toIso8601String(),
        ];
    }

    private static function scopeOf(Approval $approval): CommandScope
    {
        $scope = (array) data_get($approval->payload, 'scope', []);
        $organization = is_string($scope['organization_id'] ?? null) ? (string) $scope['organization_id'] : '';
        $id = is_string($scope['id'] ?? null) ? (string) $scope['id'] : '';

        return match (true) {
            ($scope['type'] ?? '') === 'resource' && $id !== '' && $organization !== '' => CommandScope::resource($id, $organization, is_string($scope['project_id'] ?? null) ? $scope['project_id'] : null),
            ($scope['type'] ?? '') === 'project' && $id !== '' && $organization !== '' => CommandScope::project($id, $organization),
            $organization !== '' => CommandScope::organization($organization),
            default => CommandScope::global(),
        };
    }

    private static function reasonOf(Command $command): ?string
    {
        $reason = data_get($command->toAudit(), 'payload.reason');

        return is_string($reason) && trim($reason) !== '' ? mb_substr(trim($reason), 0, 500) : null;
    }
}
