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

    /**
     * G7 (TASK-0115): what a receiver does while the secret is being rotated — the delivery is genuine when ANY of the signatures it
     * carries (X-ONhost-Signature, and X-ONhost-Signature-Previous during the overlap) verifies with the secret the receiver holds.
     *
     * @param  list<string>  $signatures
     */
    public static function verifyAny(string $secret, string $timestamp, string $body, array $signatures, ?int $now = null): bool
    {
        foreach ($signatures as $signature) {
            if ($signature !== '' && self::verify($secret, $timestamp, $body, $signature, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The headers of one attempt. With a `$previousSecret` (a rotation's overlap window) the same bytes are signed with it too, in a
     * header of its own: X-ONhost-Signature keeps exactly one `v1=` value signed with the CURRENT secret, so a receiver that compares
     * the whole header keeps working the moment it holds the new secret, and one that still holds the old secret checks the previous
     * signature. A list inside X-ONhost-Signature would have broken every receiver doing the documented whole-header compare.
     *
     * @return array<string, string>
     */
    public static function headers(string $secret, string $event, string $deliveryId, string $body, ?int $now = null, ?string $previousSecret = null): array
    {
        $timestamp = (string) ($now ?? time());
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ONhost-Webhooks/1.0',
            'X-ONhost-Event' => $event,
            'X-ONhost-Delivery' => $deliveryId,
            'X-ONhost-Timestamp' => $timestamp,
            'X-ONhost-Signature' => self::sign($secret, $timestamp, $body),
        ];
        if ($previousSecret !== null && $previousSecret !== '' && ! hash_equals($secret, $previousSecret)) {
            $headers['X-ONhost-Signature-Previous'] = self::sign($previousSecret, $timestamp, $body);
        }

        return $headers;
    }
}
