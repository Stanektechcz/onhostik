<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRateApplication extends Model
{
    protected $fillable = [
        'tax_rate_id',
        'invoice_id',
        'customer_id',
        'rate_applied',
        'tax_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'rate_applied' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<TaxRate, $this> */
    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
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
