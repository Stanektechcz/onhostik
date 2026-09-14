<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class Order extends Model
{
    protected static string $idPrefix = 'ord';

    protected $table = 'orders';

    protected function casts(): array
    {
        return [
            'consents' => 'array', 'meta' => 'array',
            'subtotal_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'commit_months' => 'integer',
            'placed_at' => 'datetime', 'paid_at' => 'datetime', 'activated_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function total(): Money
    {
        return Money::minor($this->total_minor, $this->currency);
    }

    public function tax(): Money
    {
        return Money::minor($this->tax_minor, $this->currency);
    }

    public function subtotal(): Money
    {
        return Money::minor($this->subtotal_minor, $this->currency);
    }
}
