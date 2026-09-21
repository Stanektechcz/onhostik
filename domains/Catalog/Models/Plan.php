<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

final class Plan extends Model
{
    protected static string $idPrefix = 'plan';

    protected $table = 'plans';

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'highlighted' => 'boolean', 'sort' => 'integer', 'current_version' => 'integer'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return HasMany<PlanVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PlanVersion::class, 'plan_id');
    }

    public function currentVersion(): ?PlanVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }

    public function localizedName(string $locale = 'cs'): string
    {
        return (string) ($this->name[$locale] ?? $this->name['cs'] ?? $this->key);
    }
}
