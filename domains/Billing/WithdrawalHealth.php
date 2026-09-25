<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Onhost\Domain\Billing\Models\Withdrawal;

/**
 * What the doctor says about consumer withdrawals (TASK-0025): whether the mechanism runs without the lawyer's review the
 * owner asked for, and whether an accepted withdrawal is stuck — the refund is due within fourteen days of the notice.
 */
final class WithdrawalHealth
{
    public function __construct(private readonly WithdrawalPolicy $policy) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool}> */
    public function checks(): array
    {
        $enabled = $this->policy->enabled();
        $reviewed = (bool) config('onhost.withdrawal.legal_reviewed', false);
        $stalled = Withdrawal::query()->whereIn('state', Withdrawal::OPEN)->whereNotNull('error')->count();
        $unrefunded = Withdrawal::query()->where('state', Withdrawal::SUSPENDING)->where('sent_at', '<', now()->subDays(7))->count();

        return [
            ['area' => 'billing', 'check' => 'consumer withdrawal reviewed by a lawyer', 'ok' => ! $enabled || $reviewed, 'blocking' => false,
                'detail' => ! $enabled ? 'rule billing.withdrawal off' : ($reviewed ? 'reviewed (ONHOST_WITHDRAWAL_LEGAL_REVIEWED)' : 'rule billing.withdrawal is on but ONHOST_WITHDRAWAL_LEGAL_REVIEWED is not set — resources/legal/LEGAL_REVIEW_withdrawal.md')],
            ['area' => 'billing', 'check' => 'consumer withdrawals move on', 'ok' => $stalled === 0 && $unrefunded === 0, 'blocking' => false,
                'detail' => $stalled === 0 && $unrefunded === 0 ? 'none stuck' : "{$stalled} with a refused step, {$unrefunded} not refunded 7 days after the notice — GET /v1/staff/withdrawals?state=open, onhost:withdrawals:finish"],
        ];
    }
}
