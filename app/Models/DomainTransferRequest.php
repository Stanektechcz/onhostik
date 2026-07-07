<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainTransferRequest extends Model
{
    protected $fillable = [
        'customer_id',
        'domain_name',
        'auth_code',
        'status',
        'admin_note',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
