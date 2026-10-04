<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Http\Client\Factory as HttpFactory;
use Onhost\Domain\Integrations\ChatMessage;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\Webhooks\DeliverWebhook;
use Onhost\Domain\Notifications\Webhooks\WebhookEvents;
use Onhost\Domain\Notifications\Webhooks\WebhookPayload;
use Onhost\Domain\Notifications\Webhooks\WebhookSigner;
use Onhost\Domain\Notifications\Webhooks\WebhookView;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Http\EgressGuard;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Customer webhooks (blueprint §72, D4). Every public organization event (WebhookEvents) becomes one delivery row per
 * subscribed endpoint, holding the PUBLIC payload only (WebhookPayload), and one queued attempt (DeliverWebhook) — the
 * outbox relay never waits for a customer's server. Attempts are signed (WebhookSigner), go only to public addresses
 * (EgressGuard: pinned, no redirects), are retried with backoff by `onhost:webhooks:retry`, and an endpoint that keeps
 * failing is suspended and its organization told (`webhook.endpoint.suspended`).
 */
final class WebhookDispatcher
{
    public const BACKOFF_MINUTES = [1, 5, 30, 120, 720];

    /** consecutive failed attempts after which an endpoint is suspended */
    public const SUSPEND_AFTER = 20;

    /** how long a claimed attempt keeps other workers off the row (a worker that died is retried after it) */
    private const CLAIM_MINUTES = 2;

    /** @deprecated read WebhookEvents::FAMILIES; kept for the families list the API answers with */
    public const CUSTOMER_EVENTS = ['order.', 'service.', 'invoice.', 'wallet.', 'domain.', 'dns.', 'subscription.', 'dunning.', 'ticket.', 'incident.', 'maintenance.', 'app.', 'backup.', 'sla.', 'monitoring.', 'deploy.', 'staging.', 'import.', 'certificate.', 'cdn.'];

    public function __construct(private readonly HttpFactory $http, private readonly EgressGuard $egress, private readonly OutboxPublisher $outbox) {}

    public function handle(OutboxEventDispatched $event): void
    {
        $message = $event->message;
        if ($message->organization_id === null) {
            return;
        }
        $data = WebhookPayload::of($message);
        if ($data === null) {
            return; // not an event a webhook carries
        }
        $endpoints = WebhookEndpoint::query()->where('organization_id', $message->organization_id)->where('state', WebhookEndpoint::ACTIVE)->get()->filter(fn (WebhookEndpoint $e) => $e->subscribedTo($message->name));
        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->firstOrCreate(['endpoint_id' => $endpoint->id, 'outbox_message_id' => $message->id], [
                'event' => $message->name, 'payload' => $data, 'state' => WebhookDelivery::PENDING, 'attempts' => 0, 'next_attempt_at' => now(),
            ]);
            if ($delivery->wasRecentlyCreated) {
                $this->queue($delivery);
            }
        }
    }

    /** A test event for one endpoint (`webhook.ping`), delivered like any other. */
    public function ping(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create([
            'endpoint_id' => $endpoint->id, 'outbox_message_id' => null, 'event' => WebhookEvents::PING, 'state' => WebhookDelivery::PENDING, 'attempts' => 0, 'next_attempt_at' => now(),
            'payload' => ['aggregate' => ['type' => 'webhook_endpoint', 'id' => $endpoint->id], 'organization_id' => $endpoint->organization_id, 'payload' => ['endpoint_id' => $endpoint->id]],
        ]);
        $this->queue($delivery);

        return $delivery;
    }

    /**
     * Sends a delivery again — the same id and the same body, so a receiver that already has it recognises it. A delivery
     * that is already waiting for its attempt is left alone: asking twice queues it once.
     */
    public function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        $requeued = WebhookDelivery::query()->whereKey($delivery->id)->whereIn('state', [WebhookDelivery::DELIVERED, WebhookDelivery::DEAD, WebhookDelivery::FAILED])
            ->update(['state' => WebhookDelivery::PENDING, 'attempts' => 0, 'last_error' => null, 'next_attempt_at' => now()]);
        if ($requeued === 1) {
            $this->queue($delivery);
        }

        return $delivery->fresh() ?? $delivery;
    }

    /** Queue the due attempts (scheduler). @return array{queued:int, dead:int} */
    public function retryDue(int $limit = 200): array
    {
        $stats = ['queued' => 0, 'dead' => 0];
        $due = WebhookDelivery::query()->whereIn('state', [WebhookDelivery::PENDING, WebhookDelivery::FAILED])->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit($limit)->get();
        foreach ($due as $delivery) {
            $endpoint = WebhookEndpoint::query()->find($delivery->endpoint_id);
            if ($endpoint === null || $endpoint->state !== WebhookEndpoint::ACTIVE) {
                $delivery->forceFill(['state' => WebhookDelivery::DEAD, 'last_error' => 'endpoint unavailable', 'next_attempt_at' => null])->save();
                $stats['dead']++;

                continue;
            }
            $this->queue($delivery);
            $stats['queued']++;
        }

        return $stats;
    }

    /**
     * One attempt (DeliverWebhook). The row is claimed first — its next attempt is pushed out by a compare-and-set — so two
     * workers holding the same job, or the retry pass racing the first attempt, send it once.
     *
     * @return 'delivered'|'failed'|'dead'|null null when there was nothing to do
     */
    public function attempt(string $deliveryId): ?string
    {
        $claimed = WebhookDelivery::query()->whereKey($deliveryId)->whereIn('state', [WebhookDelivery::PENDING, WebhookDelivery::FAILED])
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['next_attempt_at' => now()->addMinutes(self::CLAIM_MINUTES)]);
        if ($claimed !== 1) {
            return null;
        }
        $delivery = WebhookDelivery::query()->findOrFail($deliveryId);
        $endpoint = WebhookEndpoint::query()->find($delivery->endpoint_id);
        if ($endpoint === null || $endpoint->state !== WebhookEndpoint::ACTIVE) {
            $delivery->forceFill(['state' => WebhookDelivery::DEAD, 'last_error' => 'endpoint unavailable', 'next_attempt_at' => null])->save();

            return WebhookDelivery::DEAD;
        }

        return $this->deliver($delivery, $endpoint);
    }

    /** @return 'delivered'|'failed'|'dead' */
    public function deliver(WebhookDelivery $delivery, WebhookEndpoint $endpoint): string
    {
        $body = (string) json_encode($this->body($delivery, $endpoint), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attempt = $delivery->attempts + 1;
        try {
            $response = $this->http->withOptions($this->egress->options((string) $endpoint->url)) // public destinations only, pinned, no redirects
                ->withHeaders(WebhookSigner::headers((string) $endpoint->secret, $delivery->event, $delivery->id, $body))
                ->timeout(8)->connectTimeout(3)->withBody($body, 'application/json')->post((string) $endpoint->url);
            $status = $response->status();
            if ($status >= 200 && $status < 300) {
                $delivery->forceFill(['state' => WebhookDelivery::DELIVERED, 'attempts' => $attempt, 'response_status' => $status, 'delivered_at' => now(), 'last_error' => null, 'next_attempt_at' => null])->save();
                $endpoint->forceFill(['failures' => 0, 'last_delivered_at' => now()])->save();

                return WebhookDelivery::DELIVERED;
            }
            $error = "HTTP {$status}";
        } catch (Throwable $e) {
            $status = null;
            $error = mb_substr(WebhookView::scrubError($e->getMessage()), 0, 250);
        }
        $dead = $attempt >= count(self::BACKOFF_MINUTES);
        $delivery->forceFill(['state' => $dead ? WebhookDelivery::DEAD : WebhookDelivery::FAILED, 'attempts' => $attempt, 'response_status' => $status, 'last_error' => $error, 'next_attempt_at' => $dead ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempt - 1] ?? 720)])->save();
        $this->countFailure($endpoint, $status);

        return $dead ? WebhookDelivery::DEAD : WebhookDelivery::FAILED;
    }

    /** a Discord, Slack or Teams incoming webhook gets that tool's message format; any other endpoint the signed platform envelope */
    private function body(WebhookDelivery $delivery, WebhookEndpoint $endpoint): array
    {
        $data = (array) $delivery->payload;
        $created = $delivery->created_at?->toIso8601String();
        $envelope = ['id' => $delivery->id, 'event' => $delivery->event, 'created_at' => $created, 'data' => $data];

        return ChatMessage::build((string) $endpoint->url, $delivery->event, $data, $created, rtrim((string) config('onhost.portal_url', config('app.url')), '/'), $envelope);
    }

    private function countFailure(WebhookEndpoint $endpoint, ?int $status): void
    {
        $failures = $endpoint->failures + 1;
        $suspend = $failures >= self::SUSPEND_AFTER && $endpoint->state === WebhookEndpoint::ACTIVE;
        $endpoint->forceFill(['failures' => $failures] + ($suspend ? ['state' => WebhookEndpoint::SUSPENDED] : []))->save();
        if (! $suspend) {
            return;
        }
        // the customer hears it (NotificationRouter): events keep happening and nothing reaches their server until they act
        $this->outbox->publish(GenericEvent::of('webhook.endpoint.suspended', 'webhook_endpoint', $endpoint->id, [
            'host' => (string) parse_url((string) $endpoint->url, PHP_URL_HOST), 'failures' => $failures, 'last_status' => $status,
        ], $endpoint->organization_id));
    }

    private function queue(WebhookDelivery $delivery): void
    {
        DeliverWebhook::dispatch($delivery->id)->afterCommit();
    }
}
