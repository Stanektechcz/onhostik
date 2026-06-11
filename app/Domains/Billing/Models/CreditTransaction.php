<?php

declare(strict_types=1);

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Casts\MoneyCast;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Traits\HasUuid;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * APPEND-ONLY credit ledger entry.
 *
 * INVARIANTS (enforced below + by DB triggers in migration):
 *  - No UPDATE. No DELETE. Ever.
 *  - `amount` is signed minor units: deposits positive, deductions negative.
 *  - `balance_after` is the running balance computed inside the same
 *    DB transaction under SELECT ... FOR UPDATE on the customer row.
 *  - Authoritative balance = SUM(amount); `balance_after` of the latest row
 *    must always equal it (verified by audit command).
 *
 * @property CreditTransactionType $type
 * @property Currency $currency
 * @property Money $amount
 * @property Money $balance_after
 */
class CreditTransaction extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'type',
        'currency',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'created_by',     // admin user id for manual adjustments
    ];

    protected function casts(): array
    {
        return [
            'type'          => CreditTransactionType::class,
            'currency'      => Currency::class,
            'amount'        => MoneyCast::class . ':currency',
            'balance_after' => MoneyCast::class . ':currency',
        ];
    }

    // ---------------------------------------------------------------- immutability

    public static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Credit ledger is append-only: UPDATE forbidden.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Credit ledger is append-only: DELETE forbidden.');
        });
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
