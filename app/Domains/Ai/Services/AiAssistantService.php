<?php

declare(strict_types=1);

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Contracts\AiProviderInterface;
use App\Domains\Ai\DTOs\AiResponse;
use App\Domains\Ai\Enums\ApprovalStatus;
use App\Domains\Ai\Models\AiActionApproval;
use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLog;
use App\Domains\Ai\Providers\MockAiProvider;
use App\Models\User;
use InvalidArgumentException;

/**
 * Entry point for every AI feature. Persists the full trail:
 * AiRun + AiMessages + AiUsageLog, and parks high-risk tool actions as
 * AiActionApproval records — approvals never auto-execute in this phase.
 */
final class AiAssistantService
{
    public const CUSTOMER_FEATURES = [
        'plan_recommendation',
        'dns_explanation',
        'invoice_explanation',
        'support_draft',
        'website_brief',
    ];

    public const ADMIN_FEATURES = [
        'incident_summary',
        'provisioning_failure_explanation',
        'ticket_summary',
        'reply_draft',
        'audit_summary',
    ];

    /** @param array<string, mixed> $input */
    public function run(User $user, string $feature, array $input): AiRun
    {
        if (!in_array($feature, [...self::CUSTOMER_FEATURES, ...self::ADMIN_FEATURES], true)) {
            throw new InvalidArgumentException("Unknown AI feature [{$feature}].");
        }

        $provider = $this->provider();
        $started  = hrtime(true);
        $prompt   = $this->promptFor($feature, $input);
        $response = $this->dispatchFeature($provider, $feature, $prompt, $input);

        $run = AiRun::create([
            'customer_id' => $user->customer?->id,
            'user_id'     => $user->id,
            'feature'     => $feature,
            'provider'    => $provider->name(),
            'status'      => 'success',
            'input'       => $this->sanitize($input),
            'tool_calls'  => $response->toolCall !== null ? [$response->toolCall] : null,
            'tokens_in'   => $response->tokensIn,
            'tokens_out'  => $response->tokensOut,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        $run->messages()->createMany([
            ['role' => 'user', 'content' => $prompt],
            ['role' => 'assistant', 'content' => $response->content],
        ]);

        AiUsageLog::create([
            'provider'   => $provider->name(),
            'feature'    => $feature,
            'user_id'    => $user->id,
            'tokens_in'  => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
        ]);

        activity('ai')
            ->performedOn($run)
            ->causedBy($user)
            ->withProperties(['feature' => $feature, 'provider' => $provider->name()])
            ->log('ai.run_completed');

        return $run;
    }

    /**
     * High-risk tool action → approval record. NOTHING executes.
     *
     * @param  array<string, mixed>  $payload
     */
    public function requestAction(User $user, string $actionType, array $payload, ?AiRun $run = null): AiActionApproval
    {
        $approval = AiActionApproval::create([
            'ai_run_id'    => $run?->id,
            'requested_by' => $user->id,
            'action_type'  => $actionType,
            'payload'      => $this->sanitize($payload),
            'status'       => ApprovalStatus::Pending,
        ]);

        $run?->update(['status' => 'needs_approval']);

        activity('ai')
            ->performedOn($approval)
            ->causedBy($user)
            ->withProperties(['action_type' => $actionType])
            ->log('ai.approval_requested');

        return $approval;
    }

    public function review(AiActionApproval $approval, User $admin, bool $approve, ?string $reason = null): void
    {
        if ($approval->status !== ApprovalStatus::Pending) {
            return; // already decided — idempotent
        }

        $approval->update([
            'status'      => $approve ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'reason'      => $reason,
        ]);

        activity('ai')
            ->performedOn($approval)
            ->causedBy($admin)
            ->withProperties(['decision' => $approve ? 'approved' : 'rejected', 'reason' => $reason])
            ->log('ai.approval_reviewed');
    }

    // ---------------------------------------------------------------- internals

    private function provider(): AiProviderInterface
    {
        // Real providers stay behind the env gate (and have no client yet).
        return app(MockAiProvider::class);
    }

    /** @param array<string, mixed> $input */
    private function promptFor(string $feature, array $input): string
    {
        $text = is_string($input['text'] ?? null) ? $input['text'] : '';

        return trim("[{$feature}] {$text}");
    }

    /** @param array<string, mixed> $input */
    private function dispatchFeature(AiProviderInterface $provider, string $feature, string $prompt, array $input): AiResponse
    {
        $text = is_string($input['text'] ?? null) ? $input['text'] : $prompt;

        return match ($feature) {
            'plan_recommendation' => $provider->recommendPlan($input),
            'support_draft', 'reply_draft' => $provider->draftSupportReply($text, $input),
            'provisioning_failure_explanation' => $provider->explainError($text, $input),
            'incident_summary', 'ticket_summary', 'audit_summary' => $provider->summarize($text),
            'dns_explanation' => $provider->chat($text, ['topic' => 'dns']),
            'invoice_explanation' => $provider->chat($text, ['topic' => 'invoice']),
            'website_brief' => $provider->chat($text, ['topic' => 'website_brief']),
            default => $provider->chat($text),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        unset($data['password'], $data['secret'], $data['api_key'], $data['token']);

        return $data;
    }
}
