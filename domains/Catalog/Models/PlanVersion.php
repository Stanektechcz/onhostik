<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * Immutable entitlement snapshot referenced by quotes, orders, services and subscriptions.
 *
 * @property string $id
 * @property string $plan_id
 * @property int $version
 * @property array<string,mixed> $entitlements
 * @property ?array<string,mixed> $limits
 * @property ?array<string,mixed> $features
 * @property ?Carbon $effective_from
 * @property ?Carbon $effective_to
 * @property ?string $created_by
 * @property-read Collection<int, Price> $prices
 */
final class PlanVersion extends Model
{
    protected static string $idPrefix = 'plv';

    protected $table = 'plan_versions';

    protected function casts(): array
    {
        return ['entitlements' => 'array', 'limits' => 'array', 'features' => 'array', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'version' => 'integer'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /** @return HasMany<Price, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'plan_version_id');
    }

    public function entitlement(string $key, mixed $default = null): mixed
    {
        return data_get($this->entitlements, $key, $default);
    }
}
