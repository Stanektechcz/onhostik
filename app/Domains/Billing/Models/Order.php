<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Casts\MoneyCast;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Traits\HasUuid;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property OrderStatus $status
 * @property Currency $currency
 * @property Money $subtotal
 * @property Money $tax_amount
 * @property Money $total
 * @property Money|null $discount_amount
 * @property int|null $discount_code_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 */
class Order extends Model
{
    use \App\Models\Concerns\HasEntityNotes;
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'discount_code_id',
        'discount_amount',
        'vat_scenario',
        'paid_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class,
            'currency'     => Currency::class,
            'subtotal'     => MoneyCast::class . ':currency',
            'tax_amount'   => MoneyCast::class . ':currency',
            'total'           => MoneyCast::class . ':currency',
            'discount_amount' => MoneyCast::class . ':currency',
            'paid_at'         => 'datetime',
            'cancelled_at'    => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'paid_at', 'cancelled_at'])
            ->logOnlyDirty()
            ->useLogName('order');
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ---------------------------------------------------------------- helpers

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
}
