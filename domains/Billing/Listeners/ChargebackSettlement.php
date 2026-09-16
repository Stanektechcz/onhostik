<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Listeners;

use Onhost\Domain\Billing\ChargebackService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxEventDispatched;

/** A cancelled service with an approved-and-cancelled chargeback gets its credit the moment it is switched off. */
final class ChargebackSettlement
{
    public function __construct(private readonly ChargebackService $chargebacks) {}

    public function __invoke(OutboxEventDispatched $event): void
    {
        $m = $event->message;
        if (! in_array($m->name, ['service.terminated', 'service.deactivated'], true) || $m->aggregate_type !== 'service') { // a cancellation settles when the service goes off, not 30 days later when the data is purged
            return;
        }
        $this->chargebacks->settleForService((string) $m->aggregate_id, CommandContext::system('chargeback'));
    }
}
