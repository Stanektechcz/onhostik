<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

/**
 * Deterministic blind index for encrypted columns (audit 31).
 *
 * Encrypting a column at rest makes it unsearchable — two encryptions of the
 * same value differ, so `WHERE col = ?` can never match. A blind index is a
 * keyed HMAC of the *normalised* value stored in a sidecar column: the same
 * input always yields the same hash, so EXACT-match lookups still work without
 * ever storing the plaintext.
 *
 * It only supports exact match (not LIKE/substring) — that is the deliberate
 * trade for PII-at-rest.
 */
final class BlindIndex
{
    /** Keyed hash of a value, or null for an empty value. */
    public static function of(?string $value): ?string
    {
        $normalized = self::normalize((string) ($value ?? ''));

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, self::key());
    }

    /**
     * Normalise for stable matching: trim, lowercase, and for phone-like input
     * collapse to digits so "+420 777 123 456" and "0777123456" index alike.
     */
    private static function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        // Mostly digits/formatting → treat as a phone number.
        if (preg_match('/^[\d\s()+.-]+$/', $value) === 1) {
            return preg_replace('/\D/', '', $value) ?? '';
        }

        return $value;
    }

    private static function key(): string
    {
        $key = (string) config('security.pii_index_key', '');

        // Fall back to a derivation of the app key so it works out of the box
        // while staying distinct from the encryption key itself.
        if ($key === '') {
            $key = hash('sha256', 'pii-blind-index|' . (string) config('app.key'));
        }

        return $key;
    }
}
