<?php

declare(strict_types=1);

namespace App\Domains\Automation\Services;

use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Automation\Models\AutomationRuleLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class AutomationEngine
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function fire(string $trigger, array $context = []): void
    {
        $rules = AutomationRule::where('trigger', $trigger)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->matches($context)) {
                $this->logSkip($rule, $context);
                continue;
            }

            try {
                $this->executeAction($rule, $context);
                $rule->increment('run_count');
                $rule->update(['last_run_at' => now()]);
                $this->logOutcome($rule, $context, 'ok', 'Action executed');
            } catch (Throwable $e) {
                $this->logOutcome($rule, $context, 'error', $e->getMessage());
                Log::error("AutomationRule #{$rule->id} failed: " . $e->getMessage());
            }
        }
    }

    /** @param  array<string, mixed>  $context */
    private function executeAction(AutomationRule $rule, array $context): void
    {
        $params = $rule->action_params ?? [];

        match ($rule->action) {
            'send_notification' => $this->actionSendNotification($params, $context),
            'log_event'         => $this->actionLogEvent($params, $context, $rule),
            'webhook_call'      => $this->actionWebhookCall($params, $context),
            default             => throw new \InvalidArgumentException("Unknown action: {$rule->action}"),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    private function actionSendNotification(array $params, array $context): void
    {
        $to      = $params['to'] ?? data_get($context, 'user_email');
        $subject = $params['subject'] ?? 'OnHost notifikace';
        $body    = $params['body'] ?? '';

        if (empty($to)) {
            throw new \RuntimeException('send_notification: missing recipient');
        }

        // In production this would use a Mailable; here we log intent
        Log::info("AutomationEngine: send_notification to={$to} subject=\"{$subject}\"");
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    private function actionLogEvent(array $params, array $context, AutomationRule $rule): void
    {
        Log::info("AutomationRule #{$rule->id} log_event: " . ($params['message'] ?? 'triggered'), $context);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     */
    private function actionWebhookCall(array $params, array $context): void
    {
        $url = $params['url'] ?? '';
        if (empty($url)) {
            throw new \RuntimeException('webhook_call: missing url');
        }

        // Actual HTTP call would go here; log intent in non-prod
        Log::info("AutomationEngine: webhook_call url={$url}");
    }

    /** @param  array<string, mixed>  $context */
    private function logSkip(AutomationRule $rule, array $context): void
    {
        AutomationRuleLog::create([
            'automation_rule_id' => $rule->id,
            'entity_type'        => data_get($context, '_entity_type'),
            'entity_id'          => data_get($context, '_entity_id'),
            'outcome'            => 'skipped',
            'message'            => 'Conditions not met',
        ]);
    }

    /** @param  array<string, mixed>  $context */
    private function logOutcome(AutomationRule $rule, array $context, string $outcome, string $message): void
    {
        AutomationRuleLog::create([
            'automation_rule_id' => $rule->id,
            'entity_type'        => data_get($context, '_entity_type'),
            'entity_id'          => data_get($context, '_entity_id'),
            'outcome'            => $outcome,
            'message'            => $message,
        ]);
    }
}
