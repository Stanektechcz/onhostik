<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'phone',
        'relationship',
        'notify_on_suspension',
        'notify_on_expiry',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_suspension' => 'boolean',
            'notify_on_expiry' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
