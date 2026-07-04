<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\OutgoingWebhook;

/**
 * Fans out a platform event to all active subscribed outgoing webhooks.
 * Never throws — a missing/inactive webhook is a no-op.
 */
final class WebhookDispatcher
{
    /** @param array<string, mixed> $payload */
    public function dispatch(string $event, array $payload): void
    {
        OutgoingWebhook::where('is_active', true)
            ->get()
            ->each(function (OutgoingWebhook $webhook) use ($event, $payload): void {
                if ($webhook->subscribesTo($event)) {
                    DeliverWebhookJob::dispatch($webhook->id, $event, $payload);
                }
            });
    }
}
