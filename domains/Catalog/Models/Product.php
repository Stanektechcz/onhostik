<?php

declare(strict_types=1);

namespace Onhost\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;

final class Product extends Model
{
    protected static string $idPrefix = 'prod';

    protected $table = 'products';

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'meta' => 'array', 'sort' => 'integer'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'product_id')->orderBy('sort');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_id')->orderBy('sort');
    }

    public function isSellable(): bool
    {
        return $this->state === 'active' && ($this->executor !== null || $this->family === 'addon'); // add-ons attach to the parent service, no executor of their own
    }

    public function localizedName(string $locale = 'cs'): string
    {
        return (string) ($this->name[$locale] ?? $this->name['cs'] ?? $this->key);
    }
}
