<?php

declare(strict_types=1);

namespace Onhost\Platform\Clock;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;

    /** Seconds of measured offset against a trusted reference (NTP / provider Date header); null = unknown. */
    public function offsetSeconds(): ?float;
}
