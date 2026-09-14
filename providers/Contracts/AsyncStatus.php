<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

final class AsyncStatus
{
    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const UNKNOWN = 'unknown';

    /** @param array<string,mixed> $detail */
    public function __construct(
        public readonly string $state,
        public readonly ?string $message = null,
        public readonly array $detail = [],
    ) {}

    public static function running(?string $message = null, array $detail = []): self
    {
        return new self(self::RUNNING, $message, $detail);
    }

    public static function succeeded(array $detail = []): self
    {
        return new self(self::SUCCEEDED, null, $detail);
    }

    public static function failed(string $message, array $detail = []): self
    {
        return new self(self::FAILED, $message, $detail);
    }

    public static function unknown(string $message): self
    {
        return new self(self::UNKNOWN, $message);
    }

    public function isTerminal(): bool
    {
        return $this->state === self::SUCCEEDED || $this->state === self::FAILED;
    }
}
