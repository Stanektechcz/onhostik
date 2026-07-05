<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyReward extends Model
{
    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'loyalty_milestone_id',
        'awarded_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<LoyaltyMilestone, $this> */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(LoyaltyMilestone::class, 'loyalty_milestone_id');
    }
}
