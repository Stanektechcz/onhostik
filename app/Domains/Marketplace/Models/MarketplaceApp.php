<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Models;

use App\Domains\Shared\Enums\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $category
 * @property string|null $description
 * @property string $icon
 * @property int $min_disk_gb
 * @property int $price_halere
 * @property bool $is_active
 * @property int $sort_order
 */
class MarketplaceApp extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'category',
        'description',
        'icon',
        'min_disk_gb',
        'price_halere',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'min_disk_gb'  => 'integer',
            'price_halere' => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    /** A paid add-on charges the customer's credit on install; 0 = free. */
    public function isPaid(): bool
    {
        return $this->price_halere > 0;
    }

    public function priceMoney(Currency $currency): Money
    {
        return Money::ofMinor($this->price_halere, $currency->value);
    }

    /** @return HasMany<AppInstallation, $this> */
    public function installations(): HasMany
    {
        return $this->hasMany(AppInstallation::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'cms'        => 'CMS',
            'ecommerce'  => 'E-shop',
            'database'   => 'Databáze',
            'email'      => 'E-mail',
            default      => 'Ostatní',
        };
    }
}
