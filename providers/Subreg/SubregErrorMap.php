<?php

declare(strict_types=1);

namespace Onhost\Providers\Subreg;

use Onhost\Platform\Errors\ProviderErrorCode;

/**
 * Subreg error codes (major/minor, see https://subreg.cz/manual/?cmd=Error_Codes) → ONhost taxonomy.
 * Vendor codes never reach customers as a contract (`Presenters::customerError`).
 */
final class SubregErrorMap
{
    public const NOT_LOGGED = 'NOT_LOGGED';

    public const PROVIDER_AUTH_ERROR = 'PROVIDER_AUTH_ERROR';

    public const IP_NOT_ALLOWED = 'IP_NOT_ALLOWED';

    public const INVALID_REQUEST = 'INVALID_REQUEST';

    public const OBJECT_NOT_FOUND = 'OBJECT_NOT_FOUND';

    public const ALREADY_EXISTS = 'ALREADY_EXISTS';

    public const DOMAIN_NOT_AVAILABLE = 'DOMAIN_NOT_AVAILABLE';

    public const ORDER_ALREADY_PENDING = 'ORDER_ALREADY_PENDING';

    public const EXPIRY_MISMATCH = 'EXPIRY_MISMATCH';

    public const INSUFFICIENT_REGISTRAR_CREDIT = 'INSUFFICIENT_REGISTRAR_CREDIT';

    public const REGISTRY_TEMPORARILY_UNAVAILABLE = 'REGISTRY_TEMPORARILY_UNAVAILABLE';

    public const UNKNOWN = 'UNKNOWN';

    /** @return array{code:ProviderErrorCode, normalized:string, retry_after:?int} */
    public static function map(int $major, int $minor, string $message = ''): array
    {
        $text = strtolower($message);
        if (str_contains($text, 'credit') || str_contains($text, 'billing failure') || $major === 602) {
            return ['code' => ProviderErrorCode::CAPACITY, 'normalized' => self::INSUFFICIENT_REGISTRAR_CREDIT, 'retry_after' => 3600];
        }

        return match (true) {
            $major === 500 && $minor === 101 => ['code' => ProviderErrorCode::AUTH, 'normalized' => self::NOT_LOGGED, 'retry_after' => null],
            $major === 500 && $minor === 105 => ['code' => ProviderErrorCode::AUTH, 'normalized' => self::IP_NOT_ALLOWED, 'retry_after' => null],
            $major === 500 => ['code' => ProviderErrorCode::AUTH, 'normalized' => self::PROVIDER_AUTH_ERROR, 'retry_after' => null],
            $major === 501 && in_array($minor, [1004, 1007], true) => ['code' => ProviderErrorCode::NOT_FOUND, 'normalized' => self::OBJECT_NOT_FOUND, 'retry_after' => null],
            $major === 501 => ['code' => ProviderErrorCode::VALIDATION, 'normalized' => self::INVALID_REQUEST, 'retry_after' => null],
            $major === 502 => ['code' => ProviderErrorCode::TRANSIENT, 'normalized' => self::REGISTRY_TEMPORARILY_UNAVAILABLE, 'retry_after' => 300],
            $major === 503 && $minor === 1004, $major === 507 && $minor === 1009, $major === 506 && $minor === 1007 => ['code' => ProviderErrorCode::NOT_FOUND, 'normalized' => self::OBJECT_NOT_FOUND, 'retry_after' => null],
            $major === 507 && $minor === 1010 => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::ALREADY_EXISTS, 'retry_after' => null],
            $major === 506 && $minor === 1008 => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::EXPIRY_MISMATCH, 'retry_after' => null],
            $major === 506 && $minor === 1006 => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::INVALID_REQUEST, 'retry_after' => null],
            $major === 600 => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::ORDER_ALREADY_PENDING, 'retry_after' => null],
            $major >= 503 && $major <= 524, $major === 601, $major >= 603 && $major <= 606 => ['code' => ProviderErrorCode::VALIDATION, 'normalized' => self::INVALID_REQUEST, 'retry_after' => null],
            str_contains($text, 'not available') || str_contains($text, 'already registered') || str_contains($text, 'already exists') => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::DOMAIN_NOT_AVAILABLE, 'retry_after' => null],
            default => ['code' => ProviderErrorCode::UNKNOWN, 'normalized' => self::UNKNOWN, 'retry_after' => 120],
        };
    }

    /** Order (Info_Order / POLL) error codes that mean the registry refused the order for good. */
    public static function orderFailed(string $status): bool
    {
        return in_array(strtolower($status), ['failed', 'error', 'cancelled', 'canceled', 'rejected', 'denied', 'expired'], true);
    }

    public static function orderCompleted(string $status): bool
    {
        return in_array(strtolower($status), ['completed', 'done', 'finished', 'ok'], true);
    }
}
