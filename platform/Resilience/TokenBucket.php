<?php

declare(strict_types=1);

namespace Onhost\Platform\Resilience;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Fixed-window quota bucket shared across workers (e.g. WAPI 1000 requests/hour,
 * domain-check 100/hour with a 15 % critical reserve, aaPanel 600/min per node).
 * `reserve` is the share of the window kept for critical operations only.
 */
final class TokenBucket
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $key,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly float $reserve = 0.0,
    ) {}

    /** Try to consume one token. Critical calls may dip into the reserve. */
    public function tryConsume(bool $critical = false, int $tokens = 1): bool
    {
        $window = intdiv(time(), $this->windowSeconds);
        $key = "onhost:bucket:{$this->key}:{$window}";
        $ttl = $this->windowSeconds + 5;
        $this->cache->add($key, 0, $ttl);
        $used = (int) $this->cache->get($key, 0);
        $allowed = $critical ? $this->limit : (int) floor($this->limit * (1 - $this->reserve));
        if ($used + $tokens > $allowed) {
            return false;
        }
        $this->cache->increment($key, $tokens);

        return true;
    }

    public function used(): int
    {
        $window = intdiv(time(), $this->windowSeconds);

        return (int) $this->cache->get("onhost:bucket:{$this->key}:{$window}", 0);
    }

    public function remaining(bool $critical = false): int
    {
        $allowed = $critical ? $this->limit : (int) floor($this->limit * (1 - $this->reserve));

        return max(0, $allowed - $this->used());
    }

    public function secondsUntilReset(): int
    {
        return $this->windowSeconds - (time() % $this->windowSeconds);
    }

    public function limit(): int
    {
        return $this->limit;
    }
}
