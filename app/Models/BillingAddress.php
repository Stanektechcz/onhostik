<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'label',
        'company_name',
        'street',
        'city',
        'postal_code',
        'country_code',
        'vat_number',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<BillingAddress>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BillingAddress>
     */
    public function scopeDefault(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_default', true);
    }
}
