<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Exceptions;

use RuntimeException;

class ProvisioningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $driver = null,
        public readonly ?string $externalRequestId = null,
        public readonly bool $retryable = true,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function connectionFailed(string $driver, ?\Throwable $previous = null): self
    {
        return new self("Connection to {$driver} backend failed.", $driver, retryable: true, previous: $previous);
    }

    public static function rateLimited(string $driver): self
    {
        return new self("{$driver} rate limit exceeded.", $driver, retryable: true);
    }

    public static function invalidResponse(string $driver, string $detail): self
    {
        return new self("{$driver} returned invalid response: {$detail}", $driver, retryable: false);
    }
}
