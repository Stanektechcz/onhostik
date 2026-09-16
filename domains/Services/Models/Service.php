<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Platform\Eloquent\Model;

/**
 * Canonical desired state of a sold resource (blueprint §5.1). Public id `srv_…`.
 * Provider ids live only in provider_bindings.
 *
 * @property ?Carbon $activated_at
 * @property ?Carbon $suspended_at
 * @property ?Carbon $terminate_at
 * @property ?Carbon $terminated_at
 * @property ?Carbon $retention_until
 * @property ?Carbon $last_reconciled_at
 * @property array<string,mixed>|null $tags
 * @property array<string,mixed>|null $health
 * @property array<string,mixed>|null $desired_spec
 * @property array<string,mixed>|null $actual_spec
 * @property array<string,mixed>|null $entitlements
 */
final class Service extends Model
{
    use SoftDeletes;

    protected static string $idPrefix = 'srv';

    protected $table = 'services';

    protected function casts(): array
    {
        return [
            'desired_spec' => 'array', 'actual_spec' => 'array', 'entitlements' => 'array', 'health' => 'array', 'tags' => 'array', 'legal_hold' => 'boolean',
            'activated_at' => 'datetime', 'suspended_at' => 'datetime', 'terminate_at' => 'datetime', 'terminated_at' => 'datetime', 'retention_until' => 'datetime', 'last_reconciled_at' => 'datetime',
        ];
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(ProviderBinding::class, 'service_id');
    }

    public function primaryBinding(): ?ProviderBinding
    {
        return $this->bindings()->orderBy('created_at')->first();
    }

    public function spec(string $key, mixed $default = null): mixed
    {
        return data_get($this->desired_spec, $key, $default);
    }

    public function entitlement(string $key, mixed $default = null): mixed
    {
        return data_get($this->entitlements, $key, $default);
    }

    public function isActive(): bool
    {
        return $this->state === ServiceStateMachine::ACTIVE || $this->state === ServiceStateMachine::DEGRADED;
    }
}
