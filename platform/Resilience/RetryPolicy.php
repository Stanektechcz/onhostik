<?php

declare(strict_types=1);

namespace Onhost\Platform\Resilience;

/**
 * Retry schedule with full jitter. Provisioning: tries=5, backoff [10,30,120,600] s,
 * retryUntil +6 h; sync jobs +30 min (docs-provider-apis §0.7). Authentication
 * failures are never retried (see ProviderErrorCode::isRetryable()).
 */
final class RetryPolicy
{
    /** @param list<int> $backoffSeconds */
    public function __construct(
        public readonly int $maxAttempts = 5,
        public readonly array $backoffSeconds = [10, 30, 120, 600],
        public readonly int $retryUntilSeconds = 6 * 3600,
        public readonly float $jitter = 0.25,
    ) {}

    public static function provisioning(): self
    {
        return new self;
    }

    public static function sync(): self
    {
        return new self(3, [5, 20, 60], 30 * 60);
    }

    public function delayForAttempt(int $attempt, ?int $retryAfterHint = null): int
    {
        if ($retryAfterHint !== null && $retryAfterHint > 0) {
            return $retryAfterHint + random_int(0, 3);
        }
        $index = max(0, min($attempt - 1, count($this->backoffSeconds) - 1));
        $base = $this->backoffSeconds[$index];
        $spread = (int) round($base * $this->jitter);

        return max(1, $base + random_int(-$spread, $spread));
    }

    public function canRetry(int $attempt, int $elapsedSeconds): bool
    {
        return $attempt < $this->maxAttempts && $elapsedSeconds < $this->retryUntilSeconds;
    }
}
