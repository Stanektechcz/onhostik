<?php

declare(strict_types=1);

namespace Onhost\Platform\Errors;

use RuntimeException;

/**
 * Business rule violation rendered as RFC 9457 problem details. `error` is the
 * machine slug documented in the public API (e.g. `insufficient_funds`,
 * `confirmation_required`, `would_change_invoice`); `help` links to docs.
 */
class DomainError extends RuntimeException
{
    /** @param array<string,mixed> $extra */
    public function __construct(
        public readonly string $error,
        string $message,
        public readonly int $status = 422,
        public readonly array $extra = [],
        public readonly ?string $help = null,
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $what): self
    {
        return new self('not_found', "{$what} was not found", 404);
    }

    public static function forbidden(string $message = 'This action is not allowed for the current principal'): self
    {
        return new self('access_not_approved', $message, 403);
    }

    public static function conflict(string $error, string $message, array $extra = []): self
    {
        return new self($error, $message, 409, $extra);
    }

    /** @return array<string,mixed> */
    public function toProblem(): array
    {
        return array_merge([
            'error' => $this->error,
            'message' => $this->getMessage(),
            'status' => $this->status,
            'help' => $this->help ?? '/dokumentace/api#'.str_replace('_', '-', $this->error),
        ], $this->extra);
    }
}
