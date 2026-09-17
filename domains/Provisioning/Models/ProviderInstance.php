<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Secrets\SecretRef;

/**
 * Provider Capability Registry row (blueprint §60.3). Credentials are a secret reference, never a value.
 *
 * @property ?Carbon $maintenance_until
 * @property ?Carbon $health_checked_at
 * @property ?string $state_reason
 * @property array<string,mixed>|null $health
 * @property array<string,mixed>|null $options
 */
final class ProviderInstance extends Model
{
    protected static string $idPrefix = 'pvi';

    protected $table = 'provider_instances';

    protected function casts(): array
    {
        return [
            'capabilities' => 'array', 'options' => 'array', 'quotas' => 'array', 'rate_limits' => 'array', 'health' => 'array',
            'health_checked_at' => 'datetime', 'maintenance_until' => 'datetime',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'provider_instance_id');
    }

    /** Platform-owned instances only: customer-connected registrar accounts carry an organization_id and never serve platform work. */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->whereNull('organization_id');
    }

    public function isCustomerOwned(): bool
    {
        return $this->organization_id !== null;
    }

    public function secretRef(): SecretRef
    {
        return SecretRef::parse($this->secret_ref);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /**
     * Whether automation may place on, reconcile against and repair through this instance. Only `active` counts;
     * a maintenance lock never lifts itself when its time runs out — an expired lock stays a lock until a health
     * probe proves the panel is back (IntegrationHealthProbe) or staff set the state by hand (audit §5ab, card H322).
     */
    public function isUsable(): bool
    {
        return $this->state === 'active';
    }

    /** A maintenance lock whose planned end has passed: still locked, waiting for a fresh check before anything moves. */
    public function maintenanceExpired(): bool
    {
        return $this->state === 'maintenance' && $this->maintenance_until !== null && $this->maintenance_until->isPast();
    }

    public function supports(string $capability): bool
    {
        $value = $this->capabilities[$capability] ?? false;

        return $value === true || $value === 'conditional' || $value === 'delegated';
    }
}
