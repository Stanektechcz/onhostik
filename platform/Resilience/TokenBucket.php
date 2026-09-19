<?php

declare(strict_types=1);

namespace Onhost\Platform\Resilience;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Fixed-window quota bucket shared across workers (e.g. WAPI 1000 requests/hour,
 * domain-check 100/hour with a 15 % critical reserve, aaPanel 600/min per node).
 * `reserve` is the share of the window kept for critical operations only. `diagnosticTokens` is the top slice of the
 * same window that only health reads may use (Brain card H323): a flood of our own work can then never blind the probe
 * into calling a working panel down, and the total still never exceeds what the vendor allows.
 */
final class TokenBucket
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly string $key,
        private readonly int $limit,
        private readonly int $windowSeconds,
        private readonly float $reserve = 0.0,
        private readonly int $diagnosticTokens = 0,
    ) {}

    /** Try to consume one token. Critical calls may dip into the reserve; only diagnostic reads reach the last slice. */
    public function tryConsume(bool $critical = false, int $tokens = 1, bool $diagnostic = false): bool
    {
        $window = intdiv(time(), $this->windowSeconds);
        $key = "onhost:bucket:{$this->key}:{$window}";
        $ttl = $this->windowSeconds + 5;
        $this->cache->add($key, 0, $ttl);
        $used = (int) $this->cache->get($key, 0);
        $allowed = $this->ceiling($critical, $diagnostic);
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

    public function remaining(bool $critical = false, bool $diagnostic = false): int
    {
        return max(0, $this->ceiling($critical, $diagnostic) - $this->used());
    }

    public function diagnosticTokens(): int
    {
        return max(0, min($this->diagnosticTokens, $this->limit));
    }

    private function ceiling(bool $critical, bool $diagnostic): int
    {
        if ($diagnostic) {
            return $this->limit;
        }
        $work = $this->limit - $this->diagnosticTokens(); // the critical reserve is a share of the work, so the slice never eats it

        return $critical ? $work : (int) floor($work * (1 - $this->reserve));
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
