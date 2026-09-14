<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflow;

use Onhost\Providers\Contracts\AsyncHandle;

final class StepResult
{
    public const DONE = 'done';

    public const WAIT = 'wait';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    /** @param array<string,mixed> $context @param array<string,mixed> $detail */
    private function __construct(
        public readonly string $outcome,
        public readonly array $context = [],
        public readonly ?AsyncHandle $handle = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = false,
        public readonly array $detail = [],
        public readonly ?int $retryAfterSeconds = null,
    ) {}

    /** @param array<string,mixed> $context */
    public static function done(array $context = []): self
    {
        return new self(self::DONE, $context);
    }

    /** @param array<string,mixed> $context */
    public static function wait(AsyncHandle $handle, array $context = []): self
    {
        return new self(self::WAIT, $context, $handle);
    }

    /** @param array<string,mixed> $detail */
    public static function fail(string $error, bool $retryable = false, array $detail = [], ?int $retryAfterSeconds = null): self
    {
        return new self(self::FAIL, [], null, $error, $retryable, $detail, $retryAfterSeconds);
    }

    public static function skip(): self
    {
        return new self(self::SKIP);
    }
}
