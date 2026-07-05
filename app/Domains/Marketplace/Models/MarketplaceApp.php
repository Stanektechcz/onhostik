<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Models;

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
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'min_disk_gb'  => 'integer',
            'sort_order'   => 'integer',
        ];
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
