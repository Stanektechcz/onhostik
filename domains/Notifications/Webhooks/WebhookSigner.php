<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

/**
 * The one signature format of customer webhooks (docs/api/README.md "Webhooks"):
 *
 *   X-ONhost-Timestamp: <unix seconds>
 *   X-ONhost-Signature: v1=<hex HMAC-SHA256(secret, "<timestamp>.<raw body>")>
 *
 * The secret is the whole `whsec_…` string shown once at creation or rotation. A receiver recomputes the HMAC over the
 * bytes it received, compares in constant time and refuses a timestamp older than its tolerance (five minutes is the
 * documented default) — the timestamp is inside the signed bytes, so an old delivery cannot be replayed with a new one.
 * The docs used to describe a single `t=…,v1=…` header that the platform never sent.
 */
final class WebhookSigner
{
    public const VERSION = 'v1';

    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return self::VERSION.'='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /** What a receiver does; used by the tests and kept here so that the documented check and the signer cannot drift apart. */
    public static function verify(string $secret, string $timestamp, string $body, string $signature, ?int $now = null): bool
    {
        if (! ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(self::sign($secret, $timestamp, $body), $signature);
    }

    /** @return array<string, string> */
    public static function headers(string $secret, string $event, string $deliveryId, string $body, ?int $now = null): array
    {
        $timestamp = (string) ($now ?? time());

        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ONhost-Webhooks/1.0',
            'X-ONhost-Event' => $event,
            'X-ONhost-Delivery' => $deliveryId,
            'X-ONhost-Timestamp' => $timestamp,
            'X-ONhost-Signature' => self::sign($secret, $timestamp, $body),
        ];
    }
}
