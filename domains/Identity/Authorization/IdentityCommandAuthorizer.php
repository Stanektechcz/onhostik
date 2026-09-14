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
 *  3. two-person approval for critical permissions (approval id in context, matching payload hash, different approver).
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

        if ($context->actorType === 'ai') {
            $risk = $command instanceof RiskAwareCommand ? $command->riskLevel() : PermissionCatalog::risk($permission);
            if ($risk !== PermissionCatalog::NORMAL) {
                return AuthorizationDecision::deny('AI actors may not execute high-risk commands autonomously', 'human');
            }
        }

        if (! $this->authorizer->can($principal, $permission, $command->scope())) {
            return AuthorizationDecision::deny("Missing permission {$permission}");
        }

        $risk = $command instanceof RiskAwareCommand ? $command->riskLevel() : PermissionCatalog::risk($permission);
        $needsStepUp = $risk === PermissionCatalog::HIGH || $risk === PermissionCatalog::CRITICAL;
        $needsApproval = $risk === PermissionCatalog::CRITICAL;
        if ($command instanceof RiskAwareCommand) {
            $needsStepUp = $needsStepUp || $command->requiresStepUp();
            $needsApproval = $needsApproval || $command->requiresApproval();
        }

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
            if ($approval === null) {
                return AuthorizationDecision::deny('Two-person approval required for this action', 'approval');
            }
            $approval->forceFill(['consumed_at' => now(), 'state' => 'consumed'])->save();
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
