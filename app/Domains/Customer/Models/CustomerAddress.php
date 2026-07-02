<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'type',          // billing | technical
        'street',
        'city',
        'zip',
        'country_code',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function oneLine(): string
    {
        return "{$this->street}, {$this->zip} {$this->city}, {$this->country_code}";
    }
}
