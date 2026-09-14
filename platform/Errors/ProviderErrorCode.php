<?php

declare(strict_types=1);

namespace Onhost\Platform\Errors;

/**
 * Normalised provider error taxonomy (blueprint §6.1). Vendor codes never leak to
 * customers; adapters map every failure into exactly one of these classes and
 * the operation runner decides retry/compensation from the class, not the vendor.
 */
enum ProviderErrorCode: string
{
    case AUTH = 'AUTH';
    case CAPACITY = 'CAPACITY';
    case VALIDATION = 'VALIDATION';
    case RATE_LIMIT = 'RATE_LIMIT';
    case TRANSIENT = 'TRANSIENT';
    case PROVIDER_BUG = 'PROVIDER_BUG';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
    case CIRCUIT_OPEN = 'CIRCUIT_OPEN';
    case UNKNOWN = 'UNKNOWN';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::TRANSIENT, self::RATE_LIMIT, self::CIRCUIT_OPEN, self::UNKNOWN => true,
            default => false,
        };
    }

    /** Authentication failures are never retried: they open an incident instead (§docs-provider-apis §0.7). */
    public function opensIncident(): bool
    {
        return $this === self::AUTH;
    }
}
