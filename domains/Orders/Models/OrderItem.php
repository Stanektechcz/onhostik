<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\Money\Money;

final class OrderItem extends Model
{
    protected static string $idPrefix = 'oi';

    protected $table = 'order_items';

    protected function casts(): array
    {
        return ['config' => 'array', 'qty' => 'integer', 'unit_net_minor' => 'integer', 'discount_minor' => 'integer', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'tax_rate' => 'string'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function total(): Money
    {
        return Money::minor($this->total_minor, $this->order?->currency ?? (string) $this->config['currency']);
    }

    public function isDomain(): bool
    {
        return $this->product_key === 'domain';
    }
}
