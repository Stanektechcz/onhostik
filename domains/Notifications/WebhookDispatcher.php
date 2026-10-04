<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\RateLimiter;
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
use Onhost\Platform\Redaction\Redactor;
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
    /** the wait before each retry: the first attempt, then one retry per step; dead only when the last retry fails */
    public const BACKOFF_MINUTES = [1, 5, 30, 120, 720];

    /** consecutive failed attempts after which an endpoint is suspended */
    public const SUSPEND_AFTER = 20;

    /** attempts of one delivery in all, redeliveries included; a redelivery is one more attempt, never a fresh start */
    public const MAX_ATTEMPTS = 10;

    /** attempts per endpoint and minute; what is over waits (not a failure) — a burst of events or redeliveries is spread out */
    public const OUTBOUND_PER_MINUTE = 60;

    /** where a webhook may be sent: https, on these ports (checked at creation and again at every attempt) */
    public const ALLOWED_PORTS = [443, 8443];

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
        $data = (new Redactor)->redact($data); // a secret pasted into an allowed field is masked like in every other output
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
     * Sends a delivery again — the same id and the same body, so a receiver that already has it recognises it. It is one more
     * attempt on top of the earlier ones (MAX_ATTEMPTS in all). A delivery that is waiting for its attempt, or that an attempt
     * holds right now (its next attempt is in the future), is left alone: asking twice queues it once.
     */
    public function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        $requeued = WebhookDelivery::query()->whereKey($delivery->id)->whereIn('state', [WebhookDelivery::DELIVERED, WebhookDelivery::DEAD, WebhookDelivery::FAILED])
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update(['state' => WebhookDelivery::PENDING, 'last_error' => null, 'next_attempt_at' => now()]);
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
        $key = self::outboundKey($endpoint->id);
        if (RateLimiter::tooManyAttempts($key, self::OUTBOUND_PER_MINUTE)) {
            $delivery->forceFill(['next_attempt_at' => now()->addSeconds(max(1, RateLimiter::availableIn($key)))])->save();

            return null; // over the ceiling: it waits, the retry pass queues it when it is due
        }
        RateLimiter::hit($key, 60);

        return $this->deliver($delivery, $endpoint);
    }

    /** attempts the schedule makes on its own: the first one and one retry per backoff step (redeliveries come on top) */
    public static function scheduledAttempts(): int
    {
        return count(self::BACKOFF_MINUTES) + 1;
    }

    /**
     * When the retries happen, in minutes after the first attempt (cumulative): [1, 6, 36, 156, 876] — the last retry about
     * 14.6 hours after the event. The API docs are computed from this (D3).
     *
     * @return list<int>
     */
    public static function retryScheduleMinutes(): array
    {
        $offsets = [];
        $total = 0;
        foreach (self::BACKOFF_MINUTES as $minutes) {
            $offsets[] = $total += $minutes;
        }

        return $offsets;
    }

    public static function outboundKey(string $endpointId): string
    {
        return 'webhook-outbound:'.$endpointId;
    }

    /** Why a URL is not a webhook destination (scheme, port), or null. EgressGuard decides the address. */
    public static function destinationProblem(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return 'a webhook endpoint must use https';
        }
        $port = (int) ($parts['port'] ?? 443);

        return in_array($port, self::ALLOWED_PORTS, true) ? null : "port {$port} is not allowed for webhooks (".implode(', ', self::ALLOWED_PORTS).')';
    }

    /** @return 'delivered'|'failed'|'dead' */
    public function deliver(WebhookDelivery $delivery, WebhookEndpoint $endpoint): string
    {
        $body = (string) json_encode($this->body($delivery, $endpoint), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // counted in the row, not from the copy in hand: two workers with stale copies still count two attempts
        WebhookDelivery::query()->whereKey($delivery->id)->increment('attempts');
        $attempt = (int) WebhookDelivery::query()->whereKey($delivery->id)->value('attempts');
        $delivery->setAttribute('attempts', $attempt)->syncOriginalAttribute('attempts'); // never written back from here
        try {
            $problem = self::destinationProblem((string) $endpoint->url); // an endpoint saved before the https/port rule
            if ($problem !== null) {
                throw new \RuntimeException($problem);
            }
            $response = $this->http->withOptions($this->egress->options((string) $endpoint->url)) // public destinations only, pinned, no redirects
                ->withHeaders(WebhookSigner::headers((string) $endpoint->secret, $delivery->event, $delivery->id, $body))
                ->timeout(8)->connectTimeout(3)->withBody($body, 'application/json')->post((string) $endpoint->url);
            $status = $response->status();
            if ($status >= 200 && $status < 300) {
                $delivery->forceFill(['state' => WebhookDelivery::DELIVERED, 'response_status' => $status, 'delivered_at' => now(), 'last_error' => null, 'next_attempt_at' => null])->save();
                $endpoint->forceFill(['failures' => 0, 'last_delivered_at' => now()])->save();

                return WebhookDelivery::DELIVERED;
            }
            $error = "HTTP {$status}";
        } catch (Throwable $e) {
            $status = null;
            $error = mb_substr(WebhookView::scrubError($e->getMessage()), 0, 250);
        }
        // attempt n waits BACKOFF_MINUTES[n-1] for the next one; after the last retry (or a redelivery past it) the delivery is dead
        $dead = $attempt >= self::scheduledAttempts();
        $delivery->forceFill(['state' => $dead ? WebhookDelivery::DEAD : WebhookDelivery::FAILED, 'response_status' => $status, 'last_error' => $error, 'next_attempt_at' => $dead ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempt - 1])])->save();
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
