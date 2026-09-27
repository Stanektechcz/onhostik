<?php

declare(strict_types=1);

namespace Onhost\Domain\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Queue\Events\Looping;
use Onhost\Platform\Settings\SettingsStore;
use Throwable;

/**
 * A heartbeat per queue lane (TASK-0045, staging pre-mortem 2026-09-27). The one heartbeat job (QueueHeartbeat, default
 * queue) proves that *a* worker runs; each lane — `default`, `mails`, `provider-*` — is its own systemd unit
 * (`onhost-queue@<lane>`), and a dead `mails` or `provider-proxmox` lane went unnoticed while `default` answered.
 *
 * Why the worker loop and not a job per lane: a job sent to a lane nobody works (the provider lanes on a contained
 * staging are masked on purpose) would pile up there for ever and flood the lane the day it starts. The worker fires
 * `Looping` before it looks for its next job, idle or busy, so the stamp costs nothing that is not already running, and
 * only a worker that really loops on a lane stamps it.
 *
 * Which lanes are expected: every lane that has ever looped on this installation (remembered in `system_settings`, one
 * key per lane, so a cache flush does not forget a dead lane). A lane that never ran — masked, or not installed — is
 * never reported; a lane retired on purpose is forgotten with `php artisan onhost:queue:lanes --forget=<lane>`.
 */
final class QueueLaneHeartbeat
{
    /** A lane whose worker has not looped for this long is down: one job may run for up to 15 minutes (`--timeout=900`). */
    public const STALE_MINUTES = 20;

    /** A worker loops every couple of seconds; the stamp is written at most this often per lane and process. */
    public const STAMP_EVERY_SECONDS = 30;

    /** The doctor row of a lane is `automation|queue worker alive (<lane>)` — deploy-gate.php reports that family as liveness. */
    public const ROW_PREFIX = 'queue worker alive (';

    private const CACHE_KEY = 'onhost:queue:lane:';

    private const SETTING_PREFIX = 'queue.lane.';

    private const LANE = '/^[a-z0-9][a-z0-9._-]{0,62}$/';

    /** @var array<string, int> lane → when this process last stamped it (unix seconds) */
    private array $stamped = [];

    /** @var array<string, true> lanes this process knows are remembered */
    private array $known = [];

    public function __construct(private readonly CacheRepository $cache, private readonly SettingsStore $settings) {}

    /** The worker's loop event: `--queue=a,b` stamps both lanes. */
    public function handle(Looping $event): void
    {
        foreach (explode(',', (string) $event->queue) as $lane) {
            try {
                $this->beat(trim($lane));
            } catch (Throwable $e) {
                // a heartbeat must never stop the worker it reports on (review LOW): Redis or the database away, or two workers
                // remembering the same new lane at once (the settings key is unique) — reported, and tried again next loop
                report($e);
            }
        }
    }

    public function beat(string $lane): void
    {
        if (preg_match(self::LANE, $lane) !== 1) {
            return; // the name comes from a command line; only a lane name becomes a settings key
        }
        $now = now()->getTimestamp();
        if (isset($this->stamped[$lane]) && $now - $this->stamped[$lane] < self::STAMP_EVERY_SECONDS) {
            return;
        }
        $this->stamped[$lane] = $now;
        $this->cache->put(self::CACHE_KEY.$lane, ['at' => now()->toIso8601String(), 'host' => gethostname() ?: null], 7 * 86400);
        if (! isset($this->known[$lane])) {
            if ($this->settings->get(self::SETTING_PREFIX.$lane) === null) {
                $this->settings->set(self::SETTING_PREFIX.$lane, ['first_seen' => now()->toIso8601String()], 'queue-worker');
            }
            $this->known[$lane] = true;
        }
    }

    /** @return list<string> every lane that has ever looped here, sorted */
    public function lanes(): array
    {
        $lanes = [];
        foreach (array_keys($this->settings->all()) as $key) {
            if (str_starts_with((string) $key, self::SETTING_PREFIX)) {
                $lanes[] = substr((string) $key, strlen(self::SETTING_PREFIX));
            }
        }
        sort($lanes);

        return $lanes;
    }

    public function lastSeenAt(string $lane): ?CarbonImmutable
    {
        $row = $this->cache->get(self::CACHE_KEY.$lane);

        return is_array($row) && ! empty($row['at']) ? CarbonImmutable::parse((string) $row['at']) : null;
    }

    /** Stop expecting a lane that was retired on purpose. Returns false for a lane nobody remembers. */
    public function forget(string $lane): bool
    {
        if (! in_array($lane, $this->lanes(), true)) {
            return false;
        }
        $this->settings->forget(self::SETTING_PREFIX.$lane);
        $this->cache->forget(self::CACHE_KEY.$lane);
        unset($this->known[$lane], $this->stamped[$lane]);

        return true;
    }

    /**
     * One doctor row per expected lane. None under the `sync` driver: there is no worker at all (the aggregate row says so).
     *
     * @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool}>
     */
    public function rows(): array
    {
        if ((string) config('queue.default') === 'sync') {
            return [];
        }
        $rows = [];
        foreach ($this->lanes() as $lane) {
            $at = $this->lastSeenAt($lane);
            $alive = $at !== null && $at->diffInMinutes(now(), true) < self::STALE_MINUTES;
            $detail = $at === null ? 'no loop recorded' : 'last loop '.$at->diffForHumans();
            $rows[] = ['area' => 'automation', 'check' => self::ROW_PREFIX.$lane.')', 'ok' => $alive, 'blocking' => true,
                'detail' => $alive ? $detail : $detail." — systemctl status onhost-queue@{$lane}; retired on purpose: php artisan onhost:queue:lanes --forget={$lane}"];
        }

        return $rows;
    }
}
