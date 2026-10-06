<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Audit\HashChain;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;

/**
 * H0, owner decision H-R1 (permission program S1-05, D14): a risky action through an API token waits for the organization's owner.
 *
 * A token can never take a step-up (POST /v1/auth/step-up is closed to tokens, StepUpService never matches a token session), so
 * every HIGH or CRITICAL action through a personal token or a service account was refused outright. Now the refusal opens a
 * request instead (403 `approval_required` with its id): the organization's owner, signed in to the portal with a fresh step-up,
 * approves it (`POST /v1/token-approvals/{id}/decision`), and the token repeats the very same request with `approval_ids`.
 *
 * An approval of this kind is bound to more than a staff four-eyes approval: the action and the hash of its payload, the
 * principal that asked (the person behind a personal token, or the service account) AND the one token that asked — another token
 * of the same person or account, and the person in the portal, cannot spend it; a staff four-eyes approval is never spent by a
 * token either (IdentityCommandAuthorizer). It is single use and expires like any approval. Never decided by the token itself
 * (the routes are no token's and `api_token.manage` has no token scope), by the person a personal token belongs to (no
 * self-approval: an owner's own token is approved by nobody — they act in the portal or through a service account), by an
 * administrator who is not the owner, by another organization or by staff (ApprovalDecisionCommandHandler refuses it).
 */
final class TokenApprovals
{
    /** The key in `approvals.payload` that marks a token's request and names the token. */
    public const PAYLOAD_KEY = 'token';

    /** At most this many undecided requests per token: a leaked token cannot flood the owner with requests. */
    public const MAX_PENDING_PER_TOKEN = 20;

    public const REFUSAL = 'Through an API token this action needs the approval of the organization\'s owner: a request for approval was opened. Repeat the same request with approval_ids once the owner approved it in the portal.';

    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly Redactor $redactor,
    ) {}

    /** The token id of a context that came with an API token (`token:<id>`, ApiContext::sessionId), else null. */
    public static function tokenIdOf(?string $sessionId): ?string
    {
        if ($sessionId === null || ! str_starts_with($sessionId, 'token:')) {
            return null;
        }
        $id = substr($sessionId, 6);

        return ctype_digit($id) ? $id : null;
    }

    /** The token a request for approval was opened by; null for every other approval (four eyes, a time lock). */
    public static function tokenOf(Approval $approval): ?string
    {
        $id = data_get($approval->payload, self::PAYLOAD_KEY.'.id');

        return is_scalar($id) && (string) $id !== '' ? (string) $id : null;
    }

    /**
     * Spends the approved request of exactly this command of this principal through exactly this token, once: its id, or null when
     * there is none (or another request spent it a moment ago). The ids the caller offered are tried first; without them the one
     * approved for this very request is found, as for four eyes.
     *
     * @param  list<string>  $offered
     */
    public static function spend(Command $command, CommandContext $context, array $offered): ?string
    {
        $tokenId = self::tokenIdOf($context->sessionId);
        $requester = (string) $context->actorId;
        if ($tokenId === null || $requester === '') {
            return null;
        }
        $hash = HashChain::hashPayload($command->toAudit());
        $usable = fn (?Approval $a): bool => $a !== null && $a->isUsableFor($command->name(), $hash, $requester) && self::tokenOf($a) === $tokenId
            && ($context->organizationId === null || $a->organization_id === $context->organizationId);
        $approval = null;
        foreach ($offered as $id) {
            $candidate = Approval::query()->find($id);
            if ($usable($candidate)) {
                $approval = $candidate;
                break;
            }
        }
        $approval ??= Approval::query()->where('action', $command->name())->where('payload_hash', $hash)->where('requested_by', $requester)
            ->where('state', 'approved')->whereNull('consumed_at')->where('expires_at', '>', now())->get()->first($usable);
        if ($approval === null) {
            return null;
        }
        $spent = Approval::query()->whereKey($approval->id)->where('state', 'approved')->whereNull('consumed_at')->update(['consumed_at' => now(), 'state' => 'consumed']);

        return $spent === 1 ? (string) $approval->id : null;
    }

    /**
     * Opens (or finds) the pending request of this command of this principal through this token; null when the token already has
     * MAX_PENDING_PER_TOKEN undecided requests (the action stays refused, nothing new is opened).
     */
    public function request(Command $command, CommandContext $context): ?Approval
    {
        $tokenId = self::tokenIdOf($context->sessionId);
        $requester = (string) $context->actorId;
        if ($tokenId === null || $requester === '') {
            return null;
        }
        $hash = HashChain::hashPayload($command->toAudit());
        $pending = Approval::query()->where('requested_by', $requester)->where('state', 'pending')->where('expires_at', '>', now())->get()
            ->filter(fn (Approval $a) => self::tokenOf($a) === $tokenId);
        $existing = $pending->first(fn (Approval $a) => $a->action === $command->name() && $a->payload_hash === $hash);
        if ($existing instanceof Approval) {
            return $existing;
        }
        if ($pending->count() >= self::MAX_PENDING_PER_TOKEN) {
            return null;
        }
        $token = PersonalAccessToken::query()->find($tokenId);
        $scope = $command->scope();
        $approval = Approval::query()->create([
            'action' => $command->name(), 'organization_id' => $context->organizationId ?? $scope?->organizationId,
            'subject_type' => $scope === null || $scope->isGlobal() ? null : $scope->type, 'subject_id' => $scope?->id,
            'payload' => [
                'command' => $this->redactor->redact($command->toAudit()), 'permission' => $command->permission(),
                'scope' => $scope === null ? null : ['type' => $scope->type, 'id' => $scope->id, 'organization_id' => $scope->organizationId, 'project_id' => $scope->projectId],
                self::PAYLOAD_KEY => ['id' => $tokenId, 'name' => $token === null ? null : (string) $token->name, 'principal_type' => $context->actorType, 'principal_id' => $requester],
            ],
            'payload_hash' => $hash, 'requested_by' => $requester, 'reason' => self::reasonOf($command), 'state' => 'pending',
            'expires_at' => now()->addHours(max(1, (int) config('onhost.identity.approval_ttl_hours', 24))),
        ]);
        $this->audit->record($context, 'iam.token_approval.request', 'succeeded', ['approval_id' => $approval->id, 'action' => $approval->action, 'permission' => $command->permission(), 'token_id' => $tokenId], 'approval', $approval->id);
        $this->outbox->publish(GenericEvent::of('iam.token_approval.requested', 'approval', $approval->id, [
            'approval_id' => $approval->id, 'action' => $approval->action, 'token_name' => $token === null ? null : (string) $token->name,
            'requester' => self::principalName($context->actorType, $requester), 'expires_at' => $approval->expires_at?->toIso8601String(),
        ], $approval->organization_id));

        return $approval;
    }

    /**
     * The owner's decision. Approving needs the owner of the approval's organization, who could take the action themselves and is
     * not the principal that asked; turning a request down is open to the owner always (their own token's included).
     */
    public function decide(Approval $approval, Organization $organization, User $decider, string $decision, ?string $note, CommandContext $context): Approval
    {
        if (self::tokenOf($approval) === null || $approval->organization_id !== $organization->id) {
            throw DomainError::notFound('approval');
        }
        if ((string) $organization->owner_user_id !== $decider->id) {
            throw DomainError::forbidden('A request of an API token is decided by the owner of the organization.');
        }
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainError('decision_invalid', 'decision must be approved or rejected.', 422, ['field' => 'decision']);
        }
        if ($approval->state !== 'pending') {
            throw new DomainError('approval_not_pending', 'This request was already decided, used or has expired.', 409, ['state' => $approval->state]);
        }
        if ($approval->expires_at !== null && $approval->expires_at->isPast()) {
            $approval->forceFill(['state' => 'expired'])->save();

            throw new DomainError('approval_expired', 'This request has expired; the token has to ask again.', 409);
        }
        if ($decision === 'approved') {
            if ($approval->requested_by === $decider->id) {
                throw new DomainError('approval_own_request', 'A request of your own token is not approved by you: act in the portal, or give the automation a service account.', 403);
            }
            $permission = (string) data_get($approval->payload, 'permission', '');
            if ($permission === '' || ! $this->authorizer->can($decider, $permission, ApprovalService::scopeOf($approval))) {
                throw new DomainError('approver_lacks_permission', "Approving this takes {$permission}, which you do not hold.", 403, ['permission' => $permission]);
            }
        }
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 1000);
        $approval->forceFill(['state' => $decision, 'decided_by' => $decider->id, 'decided_at' => now(), 'decision_note' => $note])->save();
        $this->outbox->publish(GenericEvent::of('iam.token_approval.decided', 'approval', $approval->id, [
            'approval_id' => $approval->id, 'action' => $approval->action, 'decision' => $decision, 'token_name' => data_get($approval->payload, self::PAYLOAD_KEY.'.name'),
            'decider' => (string) $decider->name, 'note' => $note,
        ], $approval->organization_id));

        return $approval;
    }

    /** @return array<string,mixed> */
    public static function present(Approval $approval): array
    {
        $state = $approval->state === 'pending' && $approval->expires_at !== null && $approval->expires_at->isPast() ? 'expired' : $approval->state;
        $token = (array) data_get($approval->payload, self::PAYLOAD_KEY, []);

        return [
            'id' => $approval->id, 'action' => $approval->action, 'state' => $state, 'reason' => $approval->reason,
            'permission' => data_get($approval->payload, 'permission'), 'payload' => data_get($approval->payload, 'command'),
            'token' => ['id' => $token['id'] ?? null, 'name' => $token['name'] ?? null, 'principal_type' => $token['principal_type'] ?? null,
                'principal_id' => $token['principal_id'] ?? null, 'principal_name' => self::principalName((string) ($token['principal_type'] ?? ''), (string) ($token['principal_id'] ?? ''))],
            'decided_by' => $approval->decided_by, 'decision_note' => $approval->decision_note,
            'created_at' => $approval->created_at?->toIso8601String(), 'decided_at' => $approval->decided_at?->toIso8601String(),
            'expires_at' => $approval->expires_at?->toIso8601String(), 'consumed_at' => $approval->consumed_at?->toIso8601String(),
        ];
    }

    private static function principalName(string $type, string $id): ?string
    {
        if ($id === '') {
            return null;
        }
        $name = $type === 'service_account' ? ServiceAccount::query()->whereKey($id)->value('name') : User::query()->whereKey($id)->value('name');

        return $name === null ? null : (string) $name;
    }

    private static function reasonOf(Command $command): ?string
    {
        $reason = data_get($command->toAudit(), 'payload.reason') ?? data_get($command->toAudit(), 'payload.params.reason');

        return is_string($reason) && trim($reason) !== '' ? mb_substr(trim($reason), 0, 500) : null;
    }
}
