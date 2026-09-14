<?php

declare(strict_types=1);

namespace Onhost\Platform\Audit;

use Illuminate\Support\Facades\DB;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Redaction\Redactor;

final class AuditRecorder
{
    public function __construct(private readonly Redactor $redactor) {}

    /**
     * @param  array<string,mixed>  $detail
     * @param  list<string>  $approvalIds
     */
    public function record(
        CommandContext $context,
        string $action,
        string $result,
        array $detail = [],
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?string $permission = null,
        ?string $stepUp = null,
        array $approvalIds = [],
        ?array $before = null,
        ?array $after = null,
    ): AuditEvent {
        $safeDetail = $this->redactor->redact($detail);
        $payload = [
            'actor_type' => $context->actorType,
            'actor_id' => $context->actorId,
            'on_behalf_of' => $context->onBehalfOfUserId,
            'organization_id' => $context->organizationId,
            'project_id' => $context->projectId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'permission' => $permission ?? ($detail['permission'] ?? null),
            'action' => $action,
            'result' => $result,
            'request_id' => $context->requestId,
            'correlation_id' => $context->correlationId ?? CommandContext::currentCorrelationId(),
            'before_hash' => $before === null ? null : HashChain::hashPayload($this->redactor->redact($before)),
            'after_hash' => $after === null ? null : HashChain::hashPayload($this->redactor->redact($after)),
            'detail' => $safeDetail,
            'reason' => $context->reason,
            'ticket_ref' => $context->ticketRef,
            'ip' => $context->ip,
            'user_agent' => $context->userAgent === null ? null : mb_substr($context->userAgent, 0, 250),
            'session_id' => $context->sessionId,
            'step_up_method' => $stepUp ?? $context->stepUpMethod,
            'approval_ids' => $approvalIds !== [] ? $approvalIds : $context->approvalIds,
            'created_at' => now(),
        ];

        // Serialize chain writers: the previous hash must be read and the new row written atomically.
        return DB::transaction(function () use ($payload) {
            $previous = AuditEvent::query()->orderByDesc('created_at')->orderByDesc('id')->lockForUpdate()->first();
            $payload['prev_hash'] = $previous?->hash ?? HashChain::GENESIS;
            $payload['hash'] = HashChain::next($payload['prev_hash'], $payload);

            return AuditEvent::query()->create($payload);
        }, 3);
    }
}
