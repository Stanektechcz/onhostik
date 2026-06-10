<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\DTOs;

use Spatie\LaravelData\Data;

/**
 * Normalized result of a provisioning operation across all drivers.
 */
final class ProvisioningResult extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly array $credentials = [],   // sanitized before logging!
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
        public readonly ?string $externalRequestId = null,
    ) {}

    public static function ok(string $externalId, array $credentials = [], array $metadata = []): self
    {
        return new self(
            success: true,
            externalId: $externalId,
            credentials: $credentials,
            metadata: $metadata,
        );
    }

    public static function failure(string $errorMessage, ?string $externalRequestId = null): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            externalRequestId: $externalRequestId,
        );
    }
}
