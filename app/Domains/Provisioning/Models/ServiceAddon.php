<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAddon extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_czk',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'price_czk'  => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ServiceAddonSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ServiceAddonSubscription::class);
    }

    public function priceFormatted(): string
    {
        return number_format($this->price_czk / 100, 2) . ' Kč/měs.';
    }
}
