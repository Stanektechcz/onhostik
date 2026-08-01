<?php

declare(strict_types=1);

namespace App\Domains\Loyalty\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed loyalty-points ledger entry: positive when earned (e.g. on a paid
 * invoice), negative when redeemed against the reward catalog. The balance is
 * the sum of a customer's rows.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $points
 * @property string $reason
 * @property Carbon $created_at
 */
class LoyaltyPointTransaction extends Model
{
    protected $fillable = [
        'customer_id',
        'points',
        'reason',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
