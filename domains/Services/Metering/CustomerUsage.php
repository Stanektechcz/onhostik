<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Metering;

use Onhost\Domain\Services\Models\Service;
use Onhost\Providers\Contracts\Usage;

/**
 * What a customer is shown as the usage of their service (blueprint §21.1: their entitlement and usage, never
 * host-wide values). A shared web node answers usage for the whole node — CPU, memory and disk of every site on it —
 * and the API passed those figures off as the customer's own (audit H286). For a web or managed service a node-wide
 * figure (`node_*`) is not measured for the customer: it is kept as a key so the shape does not change, with no number.
 */
final class CustomerUsage
{
    public static function of(Service $service, Usage $usage): Usage
    {
        if (! in_array($service->family, ['web', 'managed'], true)) {
            return $usage;
        }
        $metrics = [];
        foreach ($usage->metrics as $key => $value) {
            $metrics[$key] = str_starts_with((string) $key, 'node_') ? null : $value;
        }

        return new Usage($metrics, $usage->observedAt, $usage->periodStart, $usage->periodEnd);
    }
}
