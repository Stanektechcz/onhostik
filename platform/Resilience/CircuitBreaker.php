<?php

declare(strict_types=1);

namespace Onhost\Platform\Resilience;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-backed circuit breaker shared by all workers of one provider instance.
 * CLOSED -> (failures >= threshold) OPEN -> (cooldown elapsed) HALF_OPEN -> success CLOSED / failure OPEN.
 */
final class CircuitBreaker
{
    public const CLOSED = 'closed';

    public const OPEN = 'open';

    public const HALF_OPEN = 'half_open';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $key,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60,
        private readonly int $windowSeconds = 120,
    ) {}

    public function state(): string
    {
        $openedAt = $this->cache->get($this->k('opened_at'));
        if ($openedAt === null) {
            return self::CLOSED;
        }
        if (time() - (int) $openedAt >= $this->cooldownSeconds) {
            return self::HALF_OPEN;
        }

        return self::OPEN;
    }

    public function allowsRequest(): bool
    {
        return $this->state() !== self::OPEN;
    }

    public function recordSuccess(): void
    {
        $this->cache->forget($this->k('failures'));
        $this->cache->forget($this->k('opened_at'));
    }

    public function recordFailure(): void
    {
        $state = $this->state();
        if ($state === self::HALF_OPEN) {
            $this->trip();

            return;
        }
        $failures = (int) $this->cache->get($this->k('failures'), 0) + 1;
        $this->cache->put($this->k('failures'), $failures, $this->windowSeconds);
        if ($failures >= $this->failureThreshold) {
            $this->trip();
        }
    }

    /** Force the circuit open (clock skew, repeated auth failure, manual freeze). */
    public function trip(): void
    {
        $this->cache->put($this->k('opened_at'), time(), $this->cooldownSeconds * 10);
    }

    public function reset(): void
    {
        $this->recordSuccess();
    }

    public function failures(): int
    {
        return (int) $this->cache->get($this->k('failures'), 0);
    }

    private function k(string $suffix): string
    {
        return "onhost:cb:{$this->key}:{$suffix}";
    }
}
