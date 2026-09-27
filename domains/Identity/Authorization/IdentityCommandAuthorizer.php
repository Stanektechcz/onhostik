<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Platform\Audit\HashChain;
use Onhost\Platform\Commands\AuthorizationDecision;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandAuthorizer;
use Onhost\Platform\Commands\CommandContext;

/**
 * Policy gate in front of the command bus:
 *  1. capability at scope,
 *  2. step-up for high/critical permissions (valid grant in this session),
 *  3. two-person approval for critical permissions (approval id in context, matching payload hash, different approver);
 *     with ONHOST_FOUR_EYES=false only the sole approver's own critical action is spared the second person, and it waits a
 *     time lock instead (TASK-0037).
 * The risk is the higher of what the command declares and its permission's floor (PermissionCatalog::effectiveRisk).
 * System and AI actors: system commands need no permission; AI actors can only run
 * commands explicitly marked as AI-safe with a confirming human in the context (§69.3).
 */
final class IdentityCommandAuthorizer implements CommandAuthorizer
{
    public function __construct(
        private readonly Authorizer $authorizer,
        private readonly StepUpService $stepUp,
    ) {}

    public function authorize(Command $command, CommandContext $context): AuthorizationDecision
    {
        $permission = $command->permission();
        if ($permission === null) {
            return $context->actorType === 'system' || $context->actorType === 'user' || $context->actorType === 'service_account'
                ? AuthorizationDecision::allow()
                : AuthorizationDecision::deny('AI actors cannot run unscoped commands');
        }

        if ($context->actorType === 'system') {
            return AuthorizationDecision::allow();
        }

        $principal = $this->principal($context);
        if ($principal === null) {
            return AuthorizationDecision::deny('Unauthenticated');
        }

        // TASK-0037 (program IF-13, principle 6): the risk is never below the permission's — max(declared, catalogue floor).
        // A command used to be trusted with its own riskLevel(): `publish_ds` put a DS record at the registry under the HIGH
        // `dns.dnssec.manage` with no step-up, and a CRITICAL staff permission was only protected by a special case here (a legal
        // hold placed and lifted by one person alone). An operation may still declare MORE than its permission (a resize, a
        // money move under an ordinary one); less only through PermissionCatalog::LOWERED_RISK, which is empty.
        $risk = PermissionCatalog::effectiveRisk($permission, $command instanceof RiskAwareCommand ? $command->riskLevel() : null, $command->name());

        if ($context->actorType === 'ai' && $risk !== PermissionCatalog::NORMAL) {
            return AuthorizationDecision::deny('AI actors may not execute high-risk commands autonomously', 'human');
        }

        if (! $this->authorizer->can($principal, $permission, $command->scope())) {
            return AuthorizationDecision::deny("Missing permission {$permission}");
        }

        $needsStepUp = $risk === PermissionCatalog::HIGH || $risk === PermissionCatalog::CRITICAL;
        $needsApproval = $risk === PermissionCatalog::CRITICAL;
        if ($command instanceof RiskAwareCommand) {
            $needsStepUp = $needsStepUp || $command->requiresStepUp();
            $needsApproval = $needsApproval || $command->requiresApproval();
        }
        // TASK-0037 (program IF-10, D8): one operator runs the platform alone (ONHOST_FOUR_EYES=false on the server). The waiver
        // used to cover EVERY actor — anybody holding a critical permission acted alone. It covers only the one person who could
        // be the second person (the sole holder of iam.approval.decide); everybody else asks them. And their own critical action
        // is not run at once: it waits a time lock (onhost.identity.time_lock_hours, 24 h, program §10 O4) with a notice, and
        // anybody who may decide approvals — the operator included — can cancel it meanwhile.
        $waived = $needsApproval && ApprovalService::waivesFor((string) $context->actorId);
        $needsApproval = $needsApproval && ! $waived;

        $stepUpMethod = null;
        if ($needsStepUp && $principal instanceof User) {
            $grant = $this->stepUp->activeGrant($principal, $context->sessionId);
            if ($grant === null) {
                return AuthorizationDecision::deny('Step-up authentication required for this action', 'step_up');
            }
            $stepUpMethod = $grant->method;
        } elseif ($needsStepUp && $principal instanceof ServiceAccount) {
            return AuthorizationDecision::deny('Service accounts cannot perform actions that require step-up', 'step_up');
        }

        if ($waived) {
            // the time lock of the sole approver (TASK-0037): the request opened on the first attempt (ApprovalService::request)
            // is spent by the first repeat after its delay; before that, and after a cancellation, the action stays refused
            $hash = HashChain::hashPayload($command->toAudit());
            if (ApprovalService::releaseTimeLock($command->name(), $hash, (string) $context->actorId) === null) {
                return AuthorizationDecision::deny(ApprovalService::timeLockMessage($command->name(), $hash, (string) $context->actorId), 'approval');
            }

            return AuthorizationDecision::allow($stepUpMethod, ['waived:single-operator']); // the audit says why nobody else signed
        }

        $approvalIds = [];
        if ($needsApproval) {
            $hash = HashChain::hashPayload($command->toAudit());
            $approval = null;
            foreach ($context->approvalIds as $id) {
                $candidate = Approval::query()->find($id);
                if ($candidate !== null && $candidate->isUsableFor($command->name(), $hash, (string) $context->actorId)) {
                    $approval = $candidate;
                    break;
                }
            }
            // …or the one somebody else gave to exactly this command of this person: the console repeats the action as it was, it
            // does not have to carry the id (the approval is bound to the requester, the command and the hash of its payload either way)
            $approval ??= Approval::query()->where('action', $command->name())->where('payload_hash', $hash)->where('requested_by', (string) $context->actorId)
                ->where('state', 'approved')->whereNull('consumed_at')->where('expires_at', '>', now())->get()
                ->first(fn (Approval $candidate) => $candidate->isUsableFor($command->name(), $hash, (string) $context->actorId));
            if ($approval === null) {
                return AuthorizationDecision::deny('This action takes a second person: a request for approval was opened. Repeat it with approval_ids once somebody else has approved it.', 'approval');
            }
            // spent once: two requests that both read it as unused race here, and only the one whose update changes the row goes on
            // (TASK-0022 review round 2 — a plain save let one approval carry two free raises or one price change twice)
            $spent = Approval::query()->whereKey($approval->id)->where('state', 'approved')->whereNull('consumed_at')->update(['consumed_at' => now(), 'state' => 'consumed']);
            if ($spent !== 1) {
                return AuthorizationDecision::deny('This approval has just been used by another request. Ask for a new one.', 'approval');
            }
            $approvalIds[] = $approval->id;
        }

        return AuthorizationDecision::allow($stepUpMethod, $approvalIds);
    }

    private function principal(CommandContext $context): User|ServiceAccount|null
    {
        if ($context->actorId === null) {
            return null;
        }

        return match ($context->actorType) {
            'user', 'ai' => User::query()->find($context->onBehalfOfUserId ?? $context->actorId) ?? ($context->actorType === 'ai' ? null : null),
            'service_account' => ServiceAccount::query()->find($context->actorId),
            default => null,
        };
    }
}
