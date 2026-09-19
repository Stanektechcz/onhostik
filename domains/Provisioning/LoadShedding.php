<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Overviews go first (Brain card H139). When the platform is overloaded — operations pile up behind their due time —
 * the work that can wait is refused outright so that the work that cannot keeps its share of the database and the PHP
 * workers: restores, access management, payments and every service action are never touched by this switch.
 *
 * What is shed is the read-heavy reporting staff and customers can live without for an hour: revenue and churn
 * reports, analytics, cross-service overviews. They answer 503 `load_shedding` with `Retry-After` instead of serving a
 * cached copy — an overview that is refused cannot be mistaken for a current one.
 *
 *  • `auto` (default): follows the verdict the scheduled health pass stores (`observe()`); a request never measures
 *    anything itself, it reads one cache key;
 *  • `on` / `off`: staff override during an incident, with a reason, under the same permission as the freeze switch.
 */
final class LoadShedding
{
    public const MODES = ['auto', 'on', 'off'];

    private const KEY = 'onhost:load-shedding';

    /** A verdict older than this is not trusted: a scheduler that stopped must not keep reports dark for ever. */
    private const VERDICT_TTL = 900;

    public function __construct(private readonly CacheRepository $cache) {}

    public function active(): bool
    {
        return match ($this->mode()) {
            'on' => true,
            'off' => false,
            default => (bool) data_get($this->cache->get(self::KEY.':verdict'), 'overloaded', false),
        };
    }

    public function mode(): string
    {
        $mode = (string) data_get($this->cache->get(self::KEY.':mode'), 'mode', 'auto');

        return in_array($mode, self::MODES, true) ? $mode : 'auto';
    }

    public function set(string $mode, ?string $reason, string $actor): void
    {
        $mode = in_array($mode, self::MODES, true) ? $mode : 'auto';
        if ($mode === 'auto') {
            $this->cache->forget(self::KEY.':mode');

            return;
        }
        $this->cache->forever(self::KEY.':mode', ['mode' => $mode, 'reason' => $reason, 'actor' => $actor, 'at' => now()->toIso8601String()]);
    }

    /**
     * The scheduled health pass reports what it measured; returns the transition (`started`, `ended`) or null.
     *
     * @param  array{alert:bool, stale:int, threshold:int, age_minutes:int}  $backlog
     */
    public function observe(array $backlog): ?string
    {
        $before = (bool) data_get($this->cache->get(self::KEY.':verdict'), 'overloaded', false);
        $now = (bool) $backlog['alert'];
        $since = $now && $before ? data_get($this->cache->get(self::KEY.':verdict'), 'since') : ($now ? now()->toIso8601String() : null);
        $this->cache->put(self::KEY.':verdict', ['overloaded' => $now, 'since' => $since, 'stale' => (int) $backlog['stale'], 'threshold' => (int) $backlog['threshold'], 'age_minutes' => (int) $backlog['age_minutes'], 'measured_at' => now()->toIso8601String()], self::VERDICT_TTL);

        return $now === $before ? null : ($now ? 'started' : 'ended');
    }

    /** @return array{active:bool, mode:string, reason:?string, actor:?string, since:?string, signal:?array<string,mixed>} */
    public function state(): array
    {
        $override = $this->cache->get(self::KEY.':mode');
        $verdict = $this->cache->get(self::KEY.':verdict');

        return [
            'active' => $this->active(), 'mode' => $this->mode(),
            'reason' => is_array($override) ? ($override['reason'] ?? null) : null, 'actor' => is_array($override) ? ($override['actor'] ?? null) : null,
            'since' => is_array($override) ? ($override['at'] ?? null) : (is_array($verdict) ? ($verdict['since'] ?? null) : null),
            'signal' => is_array($verdict) ? $verdict : null,
        ];
    }
}
