<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;

/**
 * What the API shows of an endpoint and of a delivery. The secret is never part of it (only the create/rotate answer).
 * The full URL is shown in the list only: a chat tool's incoming-webhook URL carries its credential in the path (Slack,
 * Discord, Teams), and a command's answer is kept in the audit trail and the replay store — there it is `https://host/…`.
 */
final class WebhookView
{
    /** @return array<string, mixed> */
    public static function endpoint(WebhookEndpoint $e): array
    {
        return [
            'id' => $e->id, 'url' => $e->url, 'events' => $e->events, 'state' => $e->isSuspended() ? WebhookEndpoint::SUSPENDED : $e->state,
            'failures' => $e->failures, 'last_delivered_at' => $e->last_delivered_at?->toIso8601String(), 'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> the endpoint as a command answers with it (audited, replayable): the URL without its path */
    public static function endpointResult(WebhookEndpoint $e): array
    {
        return ['url' => self::maskUrl((string) $e->url)] + self::endpoint($e);
    }

    public static function maskUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return '…';
        }
        $path = (string) ($parts['path'] ?? '');

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($path !== '' && $path !== '/' || isset($parts['query']) ? '/…' : '/');
    }

    /** a transport error names the URL it failed on (Guzzle adds `for <uri>`): what is kept and shown is the host */
    public static function scrubError(string $message): string
    {
        return (string) preg_replace_callback('#\bhttps?://[^\s"\'<>]+#i', fn (array $m) => self::maskUrl($m[0]), $message);
    }

    /** @return array<string, mixed> */
    public static function delivery(WebhookDelivery $d): array
    {
        return [
            'id' => $d->id, 'event' => $d->event, 'state' => $d->state, 'attempts' => $d->attempts, 'response_status' => $d->response_status, 'last_error' => $d->last_error,
            'next_attempt_at' => $d->next_attempt_at?->toIso8601String(), 'delivered_at' => $d->delivered_at?->toIso8601String(), 'at' => $d->created_at?->toIso8601String(),
        ];
    }
}
