<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Collection;
use Onhost\Domain\Provisioning\Models\ProviderInstance;

/**
 * The active game panels, read once per request or job (W7-0b, MERENI P6). Bound `scoped`, so it never
 * outlives a request or job; any ProviderInstance save or delete drops it (AppServiceProvider).
 */
final class GameInstanceMemo
{
    /** @var Collection<int, ProviderInstance>|null */
    private ?Collection $instances = null;

    /** @return Collection<int, ProviderInstance> */
    public function get(): Collection
    {
        return $this->instances ??= ProviderInstance::query()->where('provider', 'pterodactyl')->where('state', 'active')->get();
    }

    public function forget(): void
    {
        $this->instances = null;
    }
}
