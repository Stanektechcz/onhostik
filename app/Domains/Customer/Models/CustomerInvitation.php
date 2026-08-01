<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending sub-account invitation. Single-use and expiring; only the token
 * hash is stored (the raw token lives only in the emailed link).
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $invited_by_user_id
 * @property string $email
 * @property string $role
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class CustomerInvitation extends Model
{
    protected $fillable = [
        'customer_id',
        'invited_by_user_id',
        'email',
        'role',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
