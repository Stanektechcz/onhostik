<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Billing\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudReview extends Model
{
    /** @use HasFactory<\Database\Factories\FraudReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'score',
        'signals',
        'status',
        'reviewed_by',
        'reviewer_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'signals'     => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
