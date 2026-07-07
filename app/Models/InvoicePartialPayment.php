<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePartialPayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'admin_user_id',
        'amount_haler',
        'payment_method',
        'note',
        'paid_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
