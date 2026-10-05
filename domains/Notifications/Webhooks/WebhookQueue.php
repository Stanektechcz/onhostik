<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Onhost\Domain\Platform\QueueLaneHeartbeat;

/**
 * The queue lane of webhook deliveries (G7, TASK-0115). A delivery waits up to 8 s on a customer's endpoint; on the `default`
 * lane a burst of slow endpoints held everything else that lane runs (heartbeats, operations' follow-ups). Deliveries go to a
 * lane of their own — `onhost.webhooks.queue`, `webhooks` unless ONHOST_WEBHOOK_QUEUE says otherwise — but only while a worker
 * loops on it (QueueLaneHeartbeat, the same proof the doctor reads). An installation that runs no `onhost-queue@webhooks`
 * (staging runs `default` and `mails` only), or whose webhooks worker stopped, keeps delivering from the default queue instead
 * of piling deliveries up where nobody works. Empty = always the default queue.
 */
final class WebhookQueue
{
    /** The lane for the next delivery; null = the connection's default queue. */
    public static function name(): ?string
    {
        $lane = trim((string) config('onhost.webhooks.queue', 'webhooks'));
        if ($lane === '' || $lane === 'default') {
            return null;
        }
        $seen = app(QueueLaneHeartbeat::class)->lastSeenAt($lane);

        return $seen !== null && $seen->greaterThan(now()->subMinutes(QueueLaneHeartbeat::STALE_MINUTES)) ? $lane : null;
    }
}
