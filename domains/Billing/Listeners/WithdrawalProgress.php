<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Listeners;

use Onhost\Domain\Billing\WithdrawalService;
use Onhost\Platform\Outbox\OutboxMessage;

/**
 * A withdrawn service went off (`service.suspended`) or was cancelled (`service.deactivated`, `service.terminated`): the
 * withdrawal waiting for it moves on — the refund once the service is off, the cancellation after the refund (TASK-0025).
 */
final class WithdrawalProgress
{
    public function __construct(private readonly WithdrawalService $withdrawals) {}

    public function handle(OutboxMessage $message): void
    {
        if ($message->aggregate_type !== 'service') {
            return;
        }
        $this->withdrawals->onServiceEvent((string) $message->aggregate_id);
    }
}
