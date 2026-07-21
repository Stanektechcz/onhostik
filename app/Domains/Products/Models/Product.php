<?php

declare(strict_types=1);

namespace App\Domains\Products\Models;

use App\Domains\Products\Enums\ProductType;
use App\Domains\Products\Enums\SalesMode;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property ProductType $type
 * @property ProvisioningDriver|null $provisioning_driver
 * @property bool $is_active
 * @property SalesMode $sales_mode
 * @property array<string, mixed>|null $resources
 */
class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
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
        'sales_mode',
        'sort_order',
        'resources',
    ];

    protected function casts(): array
    {
        return [
            'type'                => ProductType::class,
            'provisioning_driver' => ProvisioningDriver::class,
            'is_active'           => 'boolean',
            'sales_mode'          => SalesMode::class,
            'resources'           => 'array',
        ];
    }

    /** @return HasMany<PricingPlan, $this> */
    public function pricingPlans(): HasMany
    {
        return $this->hasMany(PricingPlan::class)->orderBy('sort_order');
    }

    /**
     * Whether ordering this product provisions a distinct instance (web site,
     * VPS, game server, domain) that is keyed 1:1 to its order item. For such
     * products quantity must stay 1 — ordering three sites means three
     * separate order lines, not qty=3 on one line (see audit C37).
     */
    public function provisionsInstance(): bool
    {
        return $this->provisioning_driver !== null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
