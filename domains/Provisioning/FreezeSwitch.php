<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Incident switch (S47, §26.8): freezes every new provider mutation while the
 * data plane keeps running. Reads/reconcile continue; suspend/resume for
 * non-payment are held too. Only `provisioning.freeze` may toggle it.
 */
final class FreezeSwitch
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function isFrozen(): bool
    {
        return (bool) $this->cache->get((string) config('onhost.provisioning.freeze_cache_key'), false);
    }

    public function freeze(string $reason, string $actor): void
    {
        $this->cache->forever((string) config('onhost.provisioning.freeze_cache_key'), true);
        $this->cache->forever((string) config('onhost.provisioning.freeze_cache_key').':meta', ['reason' => $reason, 'actor' => $actor, 'at' => now()->toISOString()]);
    }

    public function thaw(): void
    {
        $this->cache->forget((string) config('onhost.provisioning.freeze_cache_key'));
        $this->cache->forget((string) config('onhost.provisioning.freeze_cache_key').':meta');
    }

    /** @return array<string,mixed>|null */
    public function meta(): ?array
    {
        $meta = $this->cache->get((string) config('onhost.provisioning.freeze_cache_key').':meta');

        return is_array($meta) ? $meta : null;
    }
}
