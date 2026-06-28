<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Products\Models\PricingPlan;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Shared\Casts\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Money $unit_price
 * @property Money $total
 * @property TaskStatus|null $provisioning_status
 * @property array<string, mixed>|null $config
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'pricing_plan_id',
        'description',
        'quantity',
        'currency',
        'unit_price',
        'vat_rate',
        'total',
        'period_from',
        'period_to',
        'provisioning_status',
        'config',          // domain name, hostname, game type, ...
    ];

    protected function casts(): array
    {
        return [
            'unit_price'          => MoneyCast::class . ':currency',
            'total'               => MoneyCast::class . ':currency',
            'vat_rate'            => 'decimal:2',
            'period_from'         => 'date',
            'period_to'           => 'date',
            'provisioning_status' => TaskStatus::class,
            'config'              => 'array',
        ];
    }

    /**
     * Subtotal = unit price × quantity (before VAT).
     * Alias for unit_price to maintain consistency with invoice terminology.
     *
     * @return Money|null
     */
    public function getSubtotalAttribute(): ?Money
    {
        return $this->unit_price;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<PricingPlan, $this> */
    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }
}
