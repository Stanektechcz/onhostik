<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Onhost\Domain\Notifications\WebhookDispatcher;

/**
 * One attempt at one webhook delivery, on the queue (D4). The outbox relay used to make the HTTP call itself: a slow or
 * dead customer endpoint held every other listener of the relay for up to eleven seconds per event. The relay now only
 * writes the delivery row and queues this job. Retries are the platform's own backoff (`onhost:webhooks:retry` queues
 * the due ones), so the queue never retries; a duplicate job is harmless because the attempt claims the row first.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    /** a customer endpoint gets 8 s (connect 3 s); the job ends well before a worker would kill it */
    public int $timeout = 30;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(WebhookDispatcher $webhooks): void
    {
        $webhooks->attempt($this->deliveryId);
    }
}
