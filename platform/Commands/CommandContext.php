<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Who is acting, from where, with which correlation id, and which step-up /
 * approvals back the action. Attached to every audit event.
 */
final class CommandContext
{
    /** @param list<string> $approvalIds @param list<string> $verifiedApprovalIds */
    public function __construct(
        public readonly string $actorType,   // user | service_account | ai | system
        public readonly ?string $actorId,
        public readonly ?string $organizationId = null,
        public readonly ?string $projectId = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $reason = null,
        public readonly ?string $ticketRef = null,
        public readonly ?string $stepUpMethod = null,
        public readonly array $approvalIds = [],
        public readonly ?string $correlationId = null,
        public readonly ?string $requestId = null,
        public readonly ?string $onBehalfOfUserId = null, // staff impersonation context
        // what the authorizer actually consumed for THIS command (approval ids, or 'waived:single-operator'); set by the bus only.
        // `approvalIds` above is what the caller offered — a handler that must prove its second person reads this one
        public readonly array $verifiedApprovalIds = [],
    ) {}

    public static function system(?string $reason = null): self
    {
        return new self('system', null, reason: $reason, correlationId: self::currentCorrelationId());
    }

    public static function ai(string $runId, ?string $organizationId = null, ?string $confirmedByUserId = null): self
    {
        return new self('ai', $runId, $organizationId, reason: $confirmedByUserId ? "confirmed_by:{$confirmedByUserId}" : null, correlationId: self::currentCorrelationId());
    }

    public static function currentCorrelationId(): string
    {
        $id = Context::get('correlation_id');
        if (! is_string($id) || $id === '') {
            $id = (string) Str::ulid();
            Context::add('correlation_id', $id);
        }

        return $id;
    }

    public function withScope(?string $organizationId, ?string $projectId = null): self
    {
        return new self(
            $this->actorType, $this->actorId, $organizationId ?? $this->organizationId, $projectId ?? $this->projectId,
            $this->ip, $this->userAgent, $this->sessionId, $this->reason, $this->ticketRef, $this->stepUpMethod,
            $this->approvalIds, $this->correlationId, $this->requestId, $this->onBehalfOfUserId, $this->verifiedApprovalIds,
        );
    }

    /** @param list<string> $approvalIds what the authorizer consumed for the command about to run (CommandBus) */
    public function withVerifiedApprovals(array $approvalIds): self
    {
        return new self(
            $this->actorType, $this->actorId, $this->organizationId, $this->projectId,
            $this->ip, $this->userAgent, $this->sessionId, $this->reason, $this->ticketRef, $this->stepUpMethod,
            $this->approvalIds, $this->correlationId, $this->requestId, $this->onBehalfOfUserId, array_values($approvalIds),
        );
    }

    public function withReason(?string $reason, ?string $ticketRef = null): self
    {
        return new self(
            $this->actorType, $this->actorId, $this->organizationId, $this->projectId,
            $this->ip, $this->userAgent, $this->sessionId, $reason ?? $this->reason, $ticketRef ?? $this->ticketRef, $this->stepUpMethod,
            $this->approvalIds, $this->correlationId, $this->requestId, $this->onBehalfOfUserId, $this->verifiedApprovalIds,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'reason' => $this->reason,
            'ticket_ref' => $this->ticketRef,
            'step_up_method' => $this->stepUpMethod,
            'approval_ids' => $this->approvalIds,
            'correlation_id' => $this->correlationId,
            'request_id' => $this->requestId,
            'on_behalf_of' => $this->onBehalfOfUserId,
        ];
    }
}
