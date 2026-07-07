<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $effective_from
 */
class TaxRate extends Model
{
    protected $fillable = [
        'country_code',
        'name',
        'rate_percent',
        'type',
        'is_active',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'rate_percent'   => 'decimal:2',
            'is_active'      => 'boolean',
            'effective_from' => 'date',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TaxRate>  $query
     * @return \Illuminate\Database\Eloquent\Builder<TaxRate>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
