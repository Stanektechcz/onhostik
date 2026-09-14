<?php

declare(strict_types=1);

namespace Onhost\Platform\Outbox;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Delivers pending outbox events right after they were published (order paid → fulfilment, payment → invoices closed,
 * notifications and mails) instead of waiting for the minute scheduler. One job per burst: the publisher debounces
 * dispatches for a few seconds and the handler runs under a short lock, so many events in one request cost one relay.
 */
final class RelayOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public function handle(OutboxPublisher $outbox): void
    {
        do {
            $delivered = $outbox->relayPending(200); // relayPending serialises concurrent relays itself
        } while ($delivered >= 200);
    }
}
