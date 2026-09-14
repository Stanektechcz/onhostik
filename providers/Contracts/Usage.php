<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/** Product-aware usage sample (blueprint §21.1: customers see their entitlement/usage, not host-wide values). */
final class Usage
{
    /** @param array<string, int|float|null> $metrics e.g. cpu_pct, mem_bytes, disk_bytes, net_in_bytes, net_out_bytes, uptime_s */
    public function __construct(
        public readonly array $metrics,
        public readonly string $observedAt,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['metrics' => $this->metrics, 'observed_at' => $this->observedAt, 'period_start' => $this->periodStart, 'period_end' => $this->periodEnd];
    }
}
