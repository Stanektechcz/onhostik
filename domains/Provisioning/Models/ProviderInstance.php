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
 * @property array<string,mixed>|null $version_gate
 */
final class ProviderInstance extends Model
{
    protected static string $idPrefix = 'pvi';

    // ── TASK-0045: a contained panel stays contained ──
    /** Every state an instance can be in (the staff state action and the console list them). */
    public const STATES = ['active', 'draining', 'maintenance', 'disabled', self::CONTAINED];

    /**
     * The owner's stop for a panel (staging pre-mortem 2026-09-27): nothing — no customer, no staff, not the system — is
     * given an adapter for it, so no request and no stored credential goes to its host until staff lift it.
     */
    public const CONTAINED = 'contained';

    /** States in which the registry refuses every caller (`disabled` used to be a label only). */
    public const REFUSED_STATES = ['disabled', self::CONTAINED];
    // ── end TASK-0045 ──

    protected $table = 'provider_instances';

    protected function casts(): array
    {
        return [
            'capabilities' => 'array', 'options' => 'array', 'quotas' => 'array', 'rate_limits' => 'array', 'health' => 'array',
            'health_checked_at' => 'datetime', 'maintenance_until' => 'datetime', 'version_gate' => 'array',
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

    /** Instances the platform may call at all: not disabled, not contained (TASK-0045). */
    public function scopeAllowedToCall(Builder $query): Builder
    {
        return $query->whereNotIn('state', self::REFUSED_STATES);
    }

    /** Whether every caller is refused this instance (TASK-0045): `disabled` or `contained`. */
    public function isRefused(): bool
    {
        return in_array($this->state, self::REFUSED_STATES, true);
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
     * A panel whose version is held (PanelVersionGate) takes nothing new either; what already runs there is still managed.
     */
    public function isUsable(): bool
    {
        return $this->state === 'active' && ! $this->versionHeld();
    }

    /** The panel runs a version nobody verified or accepted yet (PanelVersionGate, H530). */
    public function versionHeld(): bool
    {
        return data_get($this->version_gate, 'state') === 'held';
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
