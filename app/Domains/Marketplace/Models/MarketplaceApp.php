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
 * @property string $install_source
 * @property string|null $install_url
 * @property string|null $archive_subdir
 * @property string|null $install_path
 * @property bool $requires_database
 * @property string|null $post_install_commands
 * @property string|null $docs_url
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
        'install_source',
        'install_url',
        'archive_subdir',
        'install_path',
        'requires_database',
        'post_install_commands',
        'docs_url',
    ];

    protected function casts(): array
    {
        return [
            'is_active'         => 'boolean',
            'min_disk_gb'       => 'integer',
            'price_halere'      => 'integer',
            'sort_order'        => 'integer',
            'requires_database' => 'boolean',
        ];
    }

    /**
     * An app without a download URL cannot be installed for real — the
     * marketplace still lists it, but the install button must say so rather
     * than pretending something happened.
     */
    public function isInstallable(): bool
    {
        return $this->install_url !== null && $this->install_url !== '';
    }

    /** @return list<string> */
    public function postInstallCommandList(): array
    {
        if ($this->post_install_commands === null || trim($this->post_install_commands) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split('/\R/', $this->post_install_commands) ?: [],
        )));
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
