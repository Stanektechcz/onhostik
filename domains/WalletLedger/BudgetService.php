<?php

declare(strict_types=1);

namespace Onhost\Domain\WalletLedger;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/**
 * The customer's own spending limit (Brain card H30): a monthly budget for the whole organization, with warnings at
 * chosen shares of it and — when it is a hard one — a stop. A hard budget refuses new orders, and renewals and metered
 * usage that would go over it are treated like charges the credit does not cover: reminders first, then suspension.
 * Settling an invoice that already exists is never refused. The budget counts from the first of the month.
 */
final class BudgetService
{
    public function __construct(private readonly WalletService $wallets, private readonly AuditRecorder $audit) {}

    /** @return array<string,mixed>|null the organization-wide budget in its currency, or null when none is set */
    public function show(Organization $organization): ?array
    {
        $budget = $this->find($organization);

        return $budget === null ? null : $this->present($this->wallets->rollOver($budget));
    }

    /**
     * @param  array<string,mixed>  $in  limit (decimal; 0 or null removes the budget), hard?, alert_thresholds?, max_single_service?
     * @return array<string,mixed>|null
     */
    public function set(Organization $organization, array $in, CommandContext $context): ?array
    {
        $currency = (string) ($organization->currency ?? 'CZK');
        $limit = Money::decimal((string) ($in['limit'] ?? '0'), $currency);
        $existing = $this->find($organization);
        if (! $limit->isPositive()) {
            $existing?->delete();
            $this->audit->record($context->withScope($organization->id), 'billing.budget.remove', 'succeeded', ['had' => $existing?->limit_minor], 'organization', $organization->id);

            return null;
        }
        $thresholds = array_values(array_unique(array_map('intval', (array) ($in['alert_thresholds'] ?? $existing->alert_thresholds ?? [50, 75, 90, 100]))));
        sort($thresholds);
        if ($thresholds === [] || count($thresholds) > 6 || min($thresholds) < 1 || max($thresholds) > 100) {
            throw new DomainError('budget_thresholds_invalid', 'Warnings are one to six shares of the budget between 1 and 100 %.', 422, ['field' => 'alert_thresholds']);
        }
        $single = isset($in['max_single_service']) && $in['max_single_service'] !== '' ? Money::decimal((string) $in['max_single_service'], $currency) : null;
        if ($single !== null && ! $single->isPositive()) {
            $single = null;
        }
        $budget = $existing ?? new Budget(['organization_id' => $organization->id, 'project_id' => null, 'currency' => $currency, 'spent_minor' => 0, 'notified' => [], 'period_start' => now()->startOfMonth()->toDateString()]);
        $before = $existing === null ? null : ['limit_minor' => $existing->limit_minor, 'hard' => (bool) $existing->hard];
        $raised = $existing !== null && $limit->minor > (int) $existing->limit_minor;
        $budget->forceFill([
            'limit_minor' => $limit->minor, 'hard' => (bool) ($in['hard'] ?? $existing->hard ?? false), 'alert_thresholds' => $thresholds, 'max_single_service_minor' => $single?->minor,
            // a raised limit moves the thresholds: the ones the spending no longer reaches may warn again
            'notified' => $raised ? array_values(array_filter((array) ($existing->notified ?? []), fn ($t) => (int) $existing->spent_minor * 100 >= $limit->minor * (int) $t)) : ($existing->notified ?? []),
        ])->save();
        $this->audit->record($context->withScope($organization->id), 'billing.budget.set', 'succeeded', ['before' => $before, 'limit_minor' => $limit->minor, 'hard' => (bool) $budget->hard, 'thresholds' => $thresholds], 'organization', $organization->id);

        return $this->present($this->wallets->rollOver($budget));
    }

    private function find(Organization $organization): ?Budget
    {
        return Budget::query()->where('organization_id', $organization->id)->whereNull('project_id')->where('currency', (string) ($organization->currency ?? 'CZK'))->first();
    }

    /** @return array<string,mixed> */
    private function present(Budget $b): array
    {
        $left = max(0, (int) $b->limit_minor - (int) $b->spent_minor);

        return [
            'currency' => $b->currency, 'limit' => Money::minor((int) $b->limit_minor, $b->currency), 'spent' => Money::minor((int) $b->spent_minor, $b->currency), 'left' => Money::minor($left, $b->currency),
            'used_pct' => $b->limit_minor > 0 ? (int) floor((int) $b->spent_minor * 100 / (int) $b->limit_minor) : 0, 'hard' => (bool) $b->hard, 'alert_thresholds' => array_values((array) $b->alert_thresholds),
            'max_single_service' => $b->max_single_service_minor === null ? null : Money::minor((int) $b->max_single_service_minor, $b->currency),
            'period_start' => $b->period_start?->toDateString(), 'resets_on' => now()->startOfMonth()->addMonth()->toDateString(),
        ];
    }
}
