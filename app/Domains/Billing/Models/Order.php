<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Casts\MoneyCast;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'customer_id',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
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
            'total'        => MoneyCast::class . ':currency',
            'paid_at'      => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

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
