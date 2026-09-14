<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Str;
use Onhost\Domain\Integrations\ChatMessage;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Platform\Outbox\OutboxEventDispatched;
use Onhost\Platform\Outbox\OutboxMessage;
use Throwable;

/**
 * Customer webhooks (blueprint §72): every organization-scoped event fans out to
 * subscribed endpoints, signed with HMAC-SHA256 over the raw body, retried with
 * backoff and paused after repeated failures. Internal-only events never leave.
 */
final class WebhookDispatcher
{
    public const BACKOFF_MINUTES = [1, 5, 30, 120, 720];

    public const CUSTOMER_EVENTS = ['order.', 'service.', 'invoice.', 'wallet.', 'domain.', 'dns.', 'subscription.', 'dunning.', 'ticket.', 'incident.', 'maintenance.', 'app.', 'backup.', 'sla.', 'monitoring.', 'deploy.', 'staging.', 'import.', 'certificate.', 'cdn.'];

    public function __construct(private readonly HttpFactory $http) {}

    public function handle(OutboxEventDispatched $event): void
    {
        $message = $event->message;
        if ($message->organization_id === null || ! $this->customerVisible($message->name)) {
            return;
        }
        $endpoints = WebhookEndpoint::query()->where('organization_id', $message->organization_id)->where('state', 'active')->get()->filter(fn (WebhookEndpoint $e) => $e->subscribedTo($message->name));
        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->firstOrCreate(['endpoint_id' => $endpoint->id, 'outbox_message_id' => $message->id], [
                'event' => $message->name, 'payload' => $this->payload($message), 'state' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(),
            ]);
            if ($delivery->wasRecentlyCreated) {
                $this->deliver($delivery, $endpoint);
            }
        }
    }

    /** Retry due deliveries (scheduler). @return array{delivered:int, failed:int, dead:int} */
    public function retryDue(int $limit = 200): array
    {
        $stats = ['delivered' => 0, 'failed' => 0, 'dead' => 0];
        $due = WebhookDelivery::query()->whereIn('state', ['pending', 'failed'])->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit($limit)->get();
        foreach ($due as $delivery) {
            $endpoint = WebhookEndpoint::query()->find($delivery->endpoint_id);
            if ($endpoint === null || $endpoint->state !== 'active') {
                $delivery->forceFill(['state' => 'dead', 'last_error' => 'endpoint unavailable'])->save();
                $stats['dead']++;

                continue;
            }
            $result = $this->deliver($delivery, $endpoint);
            $stats[$result]++;
        }

        return $stats;
    }

    /** @return 'delivered'|'failed'|'dead' */
    public function deliver(WebhookDelivery $delivery, WebhookEndpoint $endpoint): string
    {
        // a Discord, Slack or Teams incoming webhook gets that tool's message format; any other endpoint the signed platform envelope
        $body = json_encode(ChatMessage::build((string) $endpoint->url, $delivery->event, (array) $delivery->payload, $delivery->created_at?->toIso8601String(), rtrim((string) config('onhost.portal_url', config('app.url')), '/'),
            ['id' => $delivery->id, 'event' => $delivery->event, 'created_at' => $delivery->created_at?->toIso8601String(), 'data' => $delivery->payload]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $endpoint->secret);
        $attempt = $delivery->attempts + 1;
        try {
            $response = $this->http->withHeaders([
                'Content-Type' => 'application/json', 'User-Agent' => 'ONhost-Webhooks/1.0', 'X-ONhost-Event' => $delivery->event, 'X-ONhost-Delivery' => $delivery->id,
                'X-ONhost-Timestamp' => $timestamp, 'X-ONhost-Signature' => "v1={$signature}",
            ])->timeout(8)->connectTimeout(3)->withBody((string) $body, 'application/json')->post($endpoint->url);
            $status = $response->status();
            if ($status >= 200 && $status < 300) {
                $delivery->forceFill(['state' => 'delivered', 'attempts' => $attempt, 'response_status' => $status, 'delivered_at' => now(), 'last_error' => null, 'next_attempt_at' => null])->save();
                $endpoint->forceFill(['failures' => 0, 'last_delivered_at' => now()])->save();

                return 'delivered';
            }
            $error = "HTTP {$status}";
        } catch (Throwable $e) {
            $status = null;
            $error = mb_substr($e->getMessage(), 0, 250);
        }
        $dead = $attempt >= count(self::BACKOFF_MINUTES);
        $delivery->forceFill(['state' => $dead ? 'dead' : 'failed', 'attempts' => $attempt, 'response_status' => $status, 'last_error' => $error, 'next_attempt_at' => $dead ? null : now()->addMinutes(self::BACKOFF_MINUTES[$attempt - 1] ?? 720)])->save();
        $failures = $endpoint->failures + 1;
        $endpoint->forceFill(['failures' => $failures, 'state' => $failures >= 20 ? 'paused' : $endpoint->state])->save();

        return $dead ? 'dead' : 'failed';
    }

    /** Creates an endpoint; the plaintext secret is returned exactly once. @return array{endpoint:WebhookEndpoint, secret:string} */
    public function createEndpoint(string $organizationId, string $url, array $events, ?string $createdBy): array
    {
        $secret = 'whsec_'.Str::random(40);
        $endpoint = WebhookEndpoint::query()->create(['organization_id' => $organizationId, 'url' => $url, 'secret' => $secret, 'events' => array_values($events) ?: ['*'], 'state' => 'active', 'created_by' => $createdBy]);

        return ['endpoint' => $endpoint, 'secret' => $secret];
    }

    private function customerVisible(string $event): bool
    {
        foreach (self::CUSTOMER_EVENTS as $prefix) {
            if (str_starts_with($event, $prefix)) {
                return ! str_contains($event, 'reconcile') && ! str_contains($event, 'registry_notice');
            }
        }

        return false;
    }

    private function payload(OutboxMessage $message): array
    {
        return ['aggregate' => ['type' => $message->aggregate_type, 'id' => $message->aggregate_id], 'organization_id' => $message->organization_id, 'payload' => (array) $message->payload, 'correlation_id' => $message->correlation_id];
    }
}
