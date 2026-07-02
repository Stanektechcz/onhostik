<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Shared\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'currency',
        'unit_price',
        'vat_rate',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class . ':currency',
            'total'      => MoneyCast::class . ':currency',
            'vat_rate'   => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
