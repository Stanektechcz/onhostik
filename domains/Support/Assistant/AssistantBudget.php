<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Assistant;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Models\AiRun;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * What the language model may cost. The chat route had the general API limit and nothing else: 120 questions a minute for one
 * user or token, each a model call with a few thousand tokens of context (several, when the model uses its tools) — a script
 * with a customer's API token could run up the operator's bill without ever touching a service. Nothing is refused when a
 * limit is reached: the assistant answers from the help centre and the platform's own records (the path it takes whenever the
 * model does not answer), a person can still be asked for, and operations are told once a day.
 *
 *  · a person: `user_per_hour` model answers (a member of staff `staff_per_hour`);
 *  · an organization: `organization_per_day` — staff working on its account do not spend it;
 *  · the platform: `tokens_per_day`, input and output together (0 = no ceiling).
 */
final class AssistantBudget
{
    public function __construct(private readonly CacheRepository $cache, private readonly OutboxPublisher $outbox) {}

    /** The limit that is used up (`user_per_hour` | `staff_per_hour` | `organization_per_day` | `tokens_per_day`), or null when the model may be asked. */
    public function exhausted(?Organization $organization, ?User $user, bool $staff): ?string
    {
        $limits = (array) config('onhost.ai.budget', []);
        $day = AccountingClock::now()->startOfDay()->utc(); // the day at the seller's seat, as the instant the rows are stamped with (UTC)
        $modelRuns = fn () => AiRun::query()->where('provider', '!=', 'rules');

        $tokens = (int) ($limits['tokens_per_day'] ?? 0);
        if ($tokens > 0 && (int) $modelRuns()->where('created_at', '>=', $day)->sum(DB::raw('input_tokens + output_tokens')) >= $tokens) {
            return $this->say('tokens_per_day', 'platform', null, $tokens);
        }
        $perHour = (int) ($limits[$staff ? 'staff_per_hour' : 'user_per_hour'] ?? 0);
        if ($user !== null && $perHour > 0 && $modelRuns()->where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count() >= $perHour) {
            return $this->say($staff ? 'staff_per_hour' : 'user_per_hour', 'user:'.$user->id, $organization?->id, $perHour);
        }
        $perDay = (int) ($limits['organization_per_day'] ?? 0);
        if (! $staff && $organization !== null && $perDay > 0
            && $modelRuns()->where('organization_id', $organization->id)->where('created_at', '>=', $day)->where(fn ($q) => $q->whereNull('session_id')->orWhere('session_id', 'not like', 'staff:%'))->count() >= $perDay) {
            return $this->say('organization_per_day', 'org:'.$organization->id, $organization->id, $perDay);
        }

        return null;
    }

    /** @return array{tokens_today:int, tokens_per_day:int, model_runs_today:int} */
    public function today(): array
    {
        $day = AccountingClock::now()->startOfDay()->utc(); // the day at the seller's seat, as the instant the rows are stamped with (UTC)
        $runs = AiRun::query()->where('provider', '!=', 'rules')->where('created_at', '>=', $day);

        return ['tokens_today' => (int) (clone $runs)->sum(DB::raw('input_tokens + output_tokens')), 'tokens_per_day' => (int) config('onhost.ai.budget.tokens_per_day', 0), 'model_runs_today' => (clone $runs)->count()];
    }

    /** Said once a day for one limit and one subject; the limit is returned either way. */
    private function say(string $limit, string $subject, ?string $organizationId, int $value): string
    {
        if ($this->cache->add("ai-budget:{$limit}:{$subject}:".AccountingClock::now()->format('Ymd'), 1, 86400)) {
            $this->outbox->publish(GenericEvent::of('assistant.budget.exhausted', 'ai_budget', $subject, ['limit' => $limit, 'subject' => $subject, 'value' => $value], $organizationId));
        }

        return $limit;
    }
}
