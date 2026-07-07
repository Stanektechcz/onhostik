<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DunningConfig extends Model
{
    protected $fillable = [
        'name',
        'step',
        'days_after_due',
        'action',
        'email_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<DunningConfig>  $query
     * @return \Illuminate\Database\Eloquent\Builder<DunningConfig>
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
