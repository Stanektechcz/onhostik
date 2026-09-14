<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Country of a public IP address (audit §5g-4) — one signal of the order intake check. Implementations never throw
 * and never block an order: an unknown country is simply no signal.
 */
interface IpGeoProvider
{
    /** ISO 3166-1 alpha-2 country code, or null when unknown (private address, lookup down, not configured). */
    public function country(string $ip): ?string;
}
