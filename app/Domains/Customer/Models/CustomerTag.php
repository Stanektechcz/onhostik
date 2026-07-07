<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'description',
        'customer_count',
    ];

    /** @return BelongsToMany<Customer, $this> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_customer_tag')
            ->withPivot(['assigned_by'])
            ->withTimestamps();
    }

    public function colorBadgeStyle(): string
    {
        $color = $this->color ?: '#6c757d';
        return "background-color: {$color}; color: #fff;";
    }
}
