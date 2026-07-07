<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWebhookSubscription extends Model
{
    protected $fillable = [
        'customer_id',
        'url',
        'secret',
        'events',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'events'             => 'array',
            'is_active'          => 'boolean',
            'last_triggered_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
