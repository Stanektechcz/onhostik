<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $received_at
 * @property Carbon|null $deadline_at
 */
class Chargeback extends Model
{
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'amount',
        'currency',
        'reason',
        'gateway_reference',
        'status',
        'admin_note',
        'handled_by',
        'received_at',
        'deadline_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'deadline_at' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
