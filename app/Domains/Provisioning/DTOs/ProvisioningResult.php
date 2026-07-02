<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\DTOs;

use Spatie\LaravelData\Data;

/**
 * Normalized result of a provisioning operation across all drivers.
 */
final class ProvisioningResult extends Data
{
    /**
     * @param array<string, mixed> $credentials  sanitized before logging!
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly array $credentials = [],
        public readonly array $metadata = [],
        public readonly ?string $errorMessage = null,
        public readonly ?string $externalRequestId = null,
    ) {}

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $metadata
     */
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

    /** Alias for failure() — used by drivers that prefer the shorter name. */
    public static function fail(string $errorMessage, bool $retryable = true, ?string $externalRequestId = null): self
    {
        return self::failure($errorMessage, $externalRequestId);
    }

    /**
     * Pending result — task has been submitted but not yet completed.
     * The externalId is null; the polling job will set it once the task succeeds.
     */
    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $metadata
     */
    public static function pending(?string $externalId = null, array $credentials = [], array $metadata = []): self
    {
        return new self(
            success: true,
            externalId: $externalId,
            credentials: $credentials,
            metadata: array_merge($metadata, ['pending_task' => true]),
        );
    }

    public function isPendingTask(): bool
    {
        return (bool) ($this->metadata['pending_task'] ?? false);
    }
}
