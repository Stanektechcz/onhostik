<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Listeners;

use Onhost\Domain\Billing\ChargebackService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/** A terminated service with an approved-and-cancelled chargeback gets its credit the moment the termination is confirmed. */
final class ChargebackSettlement
{
    public function __construct(private readonly ChargebackService $chargebacks) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if ($m->name !== 'service.terminated' || $m->aggregate_type !== 'service') {
            return;
        }
        $this->chargebacks->settleForService((string) $m->aggregate_id, CommandContext::system('chargeback'));
    }
}
