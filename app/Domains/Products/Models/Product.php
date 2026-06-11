<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Domains\Products\Enums\ProductType;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property ProductType $type
 * @property ProvisioningDriver|null $provisioning_driver
 * @property bool $is_active
 */
class Product extends Model
{
    use HasFactory;
    use HasTranslations;

    /** @var array<int, string> Translatable attributes (cs, en). */
    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'slug',
        'type',
        'name',          // JSON: {"cs": "...", "en": "..."}
        'description',   // JSON
        'provisioning_driver',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type'                => ProductType::class,
            'provisioning_driver' => ProvisioningDriver::class,
            'is_active'           => 'boolean',
        ];
    }

    /** @return HasMany<PricingPlan, $this> */
    public function pricingPlans(): HasMany
    {
        return $this->hasMany(PricingPlan::class)->orderBy('sort_order');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }
}
