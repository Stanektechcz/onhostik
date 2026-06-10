<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\DTOs;

use Spatie\LaravelData\Data;

final class UsageStats extends Data
{
    public function __construct(
        public readonly ?int $diskUsedMb = null,
        public readonly ?int $diskLimitMb = null,
        public readonly ?int $bandwidthUsedMb = null,
        public readonly ?int $bandwidthLimitMb = null,
        public readonly ?float $cpuPercent = null,
        public readonly ?int $memoryUsedMb = null,
        public readonly ?int $memoryLimitMb = null,
        public readonly array $extra = [],
    ) {}

    public function diskUsagePercent(): ?float
    {
        if ($this->diskUsedMb === null || ! $this->diskLimitMb) {
            return null;
        }

        return round($this->diskUsedMb / $this->diskLimitMb * 100, 1);
    }
}
