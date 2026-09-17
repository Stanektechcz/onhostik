<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\ProviderHttp\ProviderHttpClient;
use Throwable;

/** Provider health for the Integrations panel and alerting (blueprint §5.7): one probe per instance, state transitions emit events. */
final class IntegrationHealthProbe
{
    public function __construct(private readonly ProviderRegistry $providers, private readonly ProviderHttpClient $http, private readonly OutboxPublisher $outbox) {}

    /** @return array{checked:int, up:int, down:int} */
    public function run(): array
    {
        $stats = ['checked' => 0, 'up' => 0, 'down' => 0];
        foreach (ProviderInstance::query()->where('state', '!=', 'disabled')->get() as $instance) {
            $stats['checked']++;
            $stats[$this->probeInstance($instance)['up'] ? 'up' : 'down']++;
        }

        return $stats;
    }

    /**
     * Probe one instance (scheduler and the admin "test connection" button), persist the health record and
     * emit `integration.down` / `integration.recovered` on transitions.
     *
     * @return array{up:bool, error:?string, latency_ms:?int, version:?string, detail:array, circuit_state:string, checked_at:string}
     */
    public function probeInstance(ProviderInstance $instance): array
    {
        $record = IntegrationHealth::query()->firstOrNew(['provider_instance_id' => $instance->id]);
        $wasUp = $record->exists ? (bool) $record->up : null;
        try {
            $health = $this->providers->forInstance($instance)->health();
            $up = $health->healthy;
            $error = $health->error;
            $latency = $health->latencyMs;
            $version = $health->version;
            $detail = $health->detail;
        } catch (Throwable $e) {
            $up = false;
            $error = $e->getMessage();
            $latency = null;
            $version = null;
            $detail = [];
        }
        $window = now()->subDay();
        $calls = DB::table('provider_calls')->where('instance_key', $instance->key)->where('created_at', '>=', $window);
        $calls24h = (clone $calls)->count();
        $errors24h = (clone $calls)->where('ok', false)->count();
        $hour = DB::table('provider_calls')->where('instance_key', $instance->key)->where('created_at', '>=', now()->subHour());
        $hourCalls = (clone $hour)->count();
        $hourErrors = (clone $hour)->where('ok', false)->count();
        $circuit = $this->http->breaker($instance->key)->state();
        $record->forceFill([
            'up' => $up, 'last_success_at' => $up ? now() : $record->last_success_at, 'last_failure_at' => $up ? $record->last_failure_at : now(),
            'error_rate_1h' => $hourCalls > 0 ? round($hourErrors / $hourCalls * 100, 2) : 0, 'p95_ms' => (int) ($latency ?? $record->p95_ms ?? 0), 'calls_24h' => $calls24h, 'errors_24h' => $errors24h,
            'budget_used_pct' => $this->budgetUsed($instance), 'circuit_state' => $circuit, 'last_error' => $error !== null ? mb_substr($error, 0, 500) : null, 'checked_at' => now(),
        ])->save();
        $overdueBefore = (bool) data_get($instance->health, 'maintenance_overdue', false);
        $instance->forceFill(['health' => array_merge(['up' => $up, 'latency_ms' => $latency, 'error' => $error], $detail), 'health_checked_at' => now(), 'vendor_version' => $version ?? $instance->vendor_version])->save();
        $this->settleMaintenanceLock($instance, $up, $overdueBefore);
        if ($wasUp !== null && $wasUp !== $up) {
            $this->outbox->publish(GenericEvent::of($up ? 'integration.recovered' : 'integration.down', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'error' => $error]));
        } elseif ($wasUp === null && ! $up) {
            $this->outbox->publish(GenericEvent::of('integration.down', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'error' => $error]));
        }

        return ['up' => $up, 'error' => $error, 'latency_ms' => $latency, 'version' => $version, 'detail' => $detail, 'circuit_state' => $circuit, 'checked_at' => now()->toIso8601String()];
    }

    /**
     * A maintenance lock never lifts itself (H322): when its planned end has passed, or when it was set because the
     * panel address moved (H311), only a probe that finds the panel up returns the instance to automation. A lock
     * that outlived its window with the panel still down is reported once, not on every probe.
     */
    private function settleMaintenanceLock(ProviderInstance $instance, bool $up, bool $overdueBefore): void
    {
        if ($instance->state !== 'maintenance') {
            return;
        }
        $awaitingProbe = str_starts_with((string) $instance->state_reason, ProviderInstanceService::ADDRESS_CHANGE_REASON);
        $expired = $instance->maintenanceExpired();
        if (! $awaitingProbe && ! $expired) {
            return; // a lock inside its window stays exactly as staff set it
        }
        if ($up) {
            $reason = (string) $instance->state_reason;
            $instance->forceFill(['state' => 'active', 'maintenance_until' => null, 'state_reason' => null, 'health' => array_merge((array) $instance->health, ['maintenance_overdue' => false])])->save();
            $this->providers->forget($instance);
            $this->outbox->publish(GenericEvent::of('integration.maintenance.lifted', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'reason' => $reason, 'by' => $awaitingProbe ? 'address_probe' : 'expired_probe']));

            return;
        }
        $instance->forceFill(['health' => array_merge((array) $instance->health, ['maintenance_overdue' => true])])->save();
        if (! $overdueBefore) {
            $this->outbox->publish(GenericEvent::of('integration.maintenance.overdue', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'reason' => $instance->state_reason, 'maintenance_until' => $instance->maintenance_until?->toIso8601String(), 'error' => data_get($instance->health, 'error')]));
        }
    }

    private function budgetUsed(ProviderInstance $instance): float
    {
        $bucket = $this->http->bucket(match ($instance->provider) {
            'wedos', 'wedos_zone' => 'wapi:all', 'subreg' => 'subreg:'.$instance->key, default => $instance->key
        });
        if ($bucket === null || $bucket->limit() <= 0) {
            return 0.0;
        }

        return round($bucket->used() / $bucket->limit() * 100, 2);
    }
}
