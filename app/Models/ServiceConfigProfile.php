<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceConfigProfile extends Model
{
    protected $table = 'service_config_profiles';

    protected $fillable = [
        'name',
        'description',
        'service_type',
        'config_data',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'config_data' => 'array',
            'is_active'   => 'boolean',
        ];
    }

    /**
     * @param  Builder<ServiceConfigProfile>  $query
     * @return Builder<ServiceConfigProfile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
