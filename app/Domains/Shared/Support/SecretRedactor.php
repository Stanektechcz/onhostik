<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

/**
 * Masks credential-bearing keys before a structure is logged, serialised or
 * shipped to an error tracker (audit H121, J141).
 *
 * This lived as a private copy inside AapanelClient and WedosWapiClient. Two
 * copies means two key lists, and the moment they diverge one of them starts
 * leaking — so the list lives here once and both delegate to it.
 */
final class SecretRedactor
{
    public const MASK = '***redacted***';

    /**
     * Substring matches, not exact ones: a key called `client_secret`,
     * `aapanel_api_key` or `smtp_password` must be caught too. Exact matching
     * was the weaker rule and is what let prefixed variants through.
     *
     * @var list<string>
     */
    private const SECRET_FRAGMENTS = [
        'password', 'passwd', 'pwd', 'api_key', 'apikey', 'token', 'secret',
        'private_key', 'auth_code', 'authorization', 'credential', 'signature',
        'client_secret', 'dsn', 'webhook_secret', 'recovery_code',
    ];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (self::isSecretKey((string) $key)) {
                $data[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    public static function isSecretKey(string $key): bool
    {
        $key = mb_strtolower($key);

        foreach (self::SECRET_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
