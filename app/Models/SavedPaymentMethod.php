<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $provider
 * @property string|null $label
 * @property string|null $last4
 * @property string|null $card_brand
 * @property string|null $expires_at  Card expiry as stored (MM/YY or Y-m-d), not a date cast.
 * @property bool $is_default
 */
class SavedPaymentMethod extends Model
{
    protected $fillable = [
        'customer_id',
        'provider',
        'label',
        'token',
        'last4',
        'card_brand',
        'expires_at',
        'is_default',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
