<?php

declare(strict_types=1);

namespace Onhost\Providers\Redfish;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Onhost\Providers\Contracts\HostPowerProvider;
use Throwable;

/**
 * DMTF Redfish power reading (audit §5n-6): the chassis' `EnvironmentMetrics` (Redfish 2020.4+, `PowerWatts.Reading`)
 * first, the older `Power` resource (`PowerControl[].PowerConsumedWatts`) as fallback. Basic auth or a session token from
 * the secret store; self-signed BMC certificates are accepted only when the endpoint says so. Never logs the payload.
 */
final class RedfishPowerProvider implements HostPowerProvider
{
    public function __construct(private readonly int $timeoutSeconds = 4) {}

    public function read(array $endpoint, array $credentials): ?array
    {
        $base = rtrim((string) ($endpoint['url'] ?? ''), '/');
        if ($base === '' || ! str_starts_with($base, 'http')) {
            return null;
        }
        $chassis = trim((string) ($endpoint['chassis'] ?? '1'), '/');
        foreach (["/redfish/v1/Chassis/{$chassis}/EnvironmentMetrics", "/redfish/v1/Chassis/{$chassis}/Power"] as $path) {
            try {
                $response = $this->client($endpoint, $credentials)->get($base.$path);
            } catch (Throwable) {
                return null; // an unreachable controller means no reading, never a failed watch
            }
            if (! $response->successful()) {
                continue;
            }
            $watts = self::extract($response->json());
            if ($watts !== null) {
                return ['watts' => $watts, 'at' => now()->toIso8601String(), 'source' => 'redfish'];
            }
        }

        return null;
    }

    public function inventory(array $endpoint, array $credentials): ?array
    {
        $base = rtrim((string) ($endpoint['url'] ?? ''), '/');
        if ($base === '' || ! str_starts_with($base, 'http')) {
            return null;
        }
        $chassis = trim((string) ($endpoint['chassis'] ?? '1'), '/');
        $out = ['temp_max_c' => null, 'fans_failed' => 0, 'psus' => [], 'psu_failed' => 0];
        $found = false;
        try {
            $thermal = $this->client($endpoint, $credentials)->get($base."/redfish/v1/Chassis/{$chassis}/Thermal");
            if ($thermal->successful()) {
                $found = true;
                foreach ((array) data_get($thermal->json(), 'Temperatures', []) as $sensor) {
                    $reading = data_get($sensor, 'ReadingCelsius');
                    if (is_numeric($reading)) {
                        $out['temp_max_c'] = max((int) ($out['temp_max_c'] ?? PHP_INT_MIN), (int) round((float) $reading));
                    }
                }
                foreach ((array) data_get($thermal->json(), 'Fans', []) as $fan) {
                    if (! in_array(strtoupper((string) data_get($fan, 'Status.Health', 'OK')), ['OK', ''], true) && strtoupper((string) data_get($fan, 'Status.State', 'ENABLED')) !== 'ABSENT') {
                        $out['fans_failed']++;
                    }
                }
            }
            $power = $this->client($endpoint, $credentials)->get($base."/redfish/v1/Chassis/{$chassis}/Power");
            if ($power->successful()) {
                $found = true;
                foreach ((array) data_get($power->json(), 'PowerSupplies', []) as $psu) {
                    $health = strtoupper((string) data_get($psu, 'Status.Health', 'OK'));
                    $out['psus'][] = ['name' => (string) ($psu['Name'] ?? $psu['MemberId'] ?? 'PSU'), 'health' => $health];
                    if (! in_array($health, ['OK', ''], true)) {
                        $out['psu_failed']++;
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return $found ? $out : null;
    }

    /** @param array<string,mixed> $endpoint @param array<string,mixed> $credentials */
    private function client(array $endpoint, array $credentials): PendingRequest
    {
        $client = Http::timeout($this->timeoutSeconds)->connectTimeout($this->timeoutSeconds)->acceptJson()->withOptions(['verify' => ! (bool) ($endpoint['insecure'] ?? false)]);
        if (! empty($credentials['token'])) {
            return $client->withHeaders(['X-Auth-Token' => (string) $credentials['token']]);
        }
        if (! empty($credentials['username'])) {
            return $client->withBasicAuth((string) $credentials['username'], (string) ($credentials['password'] ?? ''));
        }

        return $client;
    }

    public static function extract(mixed $json): ?int
    {
        if (! is_array($json)) {
            return null;
        }
        $reading = data_get($json, 'PowerWatts.Reading');
        if (is_numeric($reading)) {
            return max(0, (int) round((float) $reading));
        }
        $consumed = data_get($json, 'PowerControl.0.PowerConsumedWatts');
        if (is_numeric($consumed)) {
            return max(0, (int) round((float) $consumed));
        }
        $total = 0.0;
        $found = false;
        foreach ((array) data_get($json, 'PowerSupplies', []) as $supply) {
            $value = data_get($supply, 'PowerOutputWatts', data_get($supply, 'LastPowerOutputWatts'));
            if (is_numeric($value)) {
                $total += (float) $value;
                $found = true;
            }
        }

        return $found ? max(0, (int) round($total)) : null;
    }
}
