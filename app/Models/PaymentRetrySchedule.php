<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRetrySchedule extends Model
{
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'retry_at',
        'status',
        'attempt_number',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'retry_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
