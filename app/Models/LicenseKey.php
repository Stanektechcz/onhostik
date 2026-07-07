<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 */
class LicenseKey extends Model
{
    protected $fillable = [
        'product_name',
        'license_key',
        'service_id',
        'customer_id',
        'status',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
