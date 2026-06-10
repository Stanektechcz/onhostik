<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\PaymentMethod;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Casts\MoneyCast;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Payment record.
 *
 * Idempotency: `gateway_transaction_id` carries a UNIQUE index — a duplicate
 * Comgate webhook can never create a second payment row.
 */
class Payment extends Model
{
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'method',
        'status',
        'currency',
        'amount',
        'gateway_transaction_id',
        'gateway_response',     // sanitized JSON — no card data ever
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'method'           => PaymentMethod::class,
            'status'           => PaymentStatus::class,
            'currency'         => Currency::class,
            'amount'           => MoneyCast::class . ':currency',
            'gateway_response' => 'array',
            'processed_at'     => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'processed_at'])
            ->logOnlyDirty()
            ->useLogName('payment');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }
}
