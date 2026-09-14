<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Proof that a queue worker is alive (audit §5g-6): the scheduler queues one of these every minute, the worker
 * stamps the cache when it runs it. A stale stamp means operations, mails and webhooks pile up silently — the
 * health rule turns that into an internal alert and `onhost:doctor` fails on it in production.
 */
final class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const KEY = 'onhost:queue:heartbeat';

    public int $tries = 1;

    public int $timeout = 10;

    public function handle(CacheRepository $cache): void
    {
        $cache->put(self::KEY, ['at' => now()->toIso8601String(), 'host' => gethostname() ?: null], 86400);
    }

    public static function lastSeenAt(CacheRepository $cache): ?CarbonImmutable
    {
        $row = $cache->get(self::KEY);

        return is_array($row) && ! empty($row['at']) ? CarbonImmutable::parse((string) $row['at']) : null;
    }
}
