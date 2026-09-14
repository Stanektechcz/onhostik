<?php

declare(strict_types=1);

namespace Onhost\Platform\Errors;

use RuntimeException;
use Throwable;

final class ProviderException extends RuntimeException
{
    /** @param array<string,mixed> $context redacted vendor context (code, message, request fingerprint) */
    public function __construct(
        public readonly string $provider,
        public readonly ProviderErrorCode $errorCode,
        string $message,
        public readonly ?string $vendorCode = null,
        public readonly array $context = [],
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('[%s:%s] %s', $provider, $errorCode->value, $message), 0, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->errorCode->isRetryable();
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'code' => $this->errorCode->value,
            'vendor_code' => $this->vendorCode,
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfterSeconds,
            'context' => $this->context,
        ];
    }
}
