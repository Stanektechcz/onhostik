<?php

declare(strict_types=1);

namespace Onhost\Providers\Wedos;

use Onhost\Platform\Errors\ProviderErrorCode;

/**
 * WAPI result codes -> ONhost taxonomy (blueprint §45.7). 1000 = OK, 1001 = async
 * accepted, 1002 = test-mode OK. 2xxx request/auth errors, 3xxx business errors,
 * 4xxx registry/system errors. Vendor codes never reach customers as a contract.
 */
final class WedosErrorMap
{
    public const DOMAIN_NOT_AVAILABLE = 'DOMAIN_NOT_AVAILABLE';

    public const INSUFFICIENT_REGISTRAR_CREDIT = 'INSUFFICIENT_REGISTRAR_CREDIT';

    public const REGISTRY_TEMPORARILY_UNAVAILABLE = 'REGISTRY_TEMPORARILY_UNAVAILABLE';

    public const PROVIDER_AUTH_ERROR = 'PROVIDER_AUTH_ERROR';

    public const INVALID_REQUEST = 'INVALID_REQUEST';

    public const OBJECT_NOT_FOUND = 'OBJECT_NOT_FOUND';

    public const ALREADY_EXISTS = 'ALREADY_EXISTS';

    public const UNKNOWN = 'UNKNOWN';

    public static function isSuccess(int $code): bool
    {
        return in_array($code, [1000, 1001, 1002], true);
    }

    public static function isAsyncAccepted(int $code): bool
    {
        return $code === 1001;
    }

    /** @return array{code:ProviderErrorCode, normalized:string, retry_after:?int} */
    public static function map(int $code): array
    {
        return match (true) {
            in_array($code, [3201, 3204, 3205, 3206], true) => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::DOMAIN_NOT_AVAILABLE, 'retry_after' => null],
            in_array($code, [3002, 2283], true) => ['code' => ProviderErrorCode::CAPACITY, 'normalized' => self::INSUFFICIENT_REGISTRAR_CREDIT, 'retry_after' => 3600],
            in_array($code, [4205, 4207, 4218], true) => ['code' => ProviderErrorCode::TRANSIENT, 'normalized' => self::REGISTRY_TEMPORARILY_UNAVAILABLE, 'retry_after' => 600],
            in_array($code, [2001, 2002, 2003, 2010, 2011, 2012, 2013], true) => ['code' => ProviderErrorCode::AUTH, 'normalized' => self::PROVIDER_AUTH_ERROR, 'retry_after' => null],
            $code === 2302 || $code === 3301 => ['code' => ProviderErrorCode::CONFLICT, 'normalized' => self::ALREADY_EXISTS, 'retry_after' => null],
            in_array($code, [2303, 3302, 3222], true) => ['code' => ProviderErrorCode::NOT_FOUND, 'normalized' => self::OBJECT_NOT_FOUND, 'retry_after' => null],
            $code >= 2000 && $code < 3000 => ['code' => ProviderErrorCode::VALIDATION, 'normalized' => self::INVALID_REQUEST, 'retry_after' => null],
            $code >= 3000 && $code < 4000 => ['code' => ProviderErrorCode::VALIDATION, 'normalized' => self::INVALID_REQUEST, 'retry_after' => null],
            $code >= 4000 && $code < 5000 => ['code' => ProviderErrorCode::TRANSIENT, 'normalized' => self::REGISTRY_TEMPORARILY_UNAVAILABLE, 'retry_after' => 300],
            default => ['code' => ProviderErrorCode::UNKNOWN, 'normalized' => self::UNKNOWN, 'retry_after' => 120],
        };
    }
}
