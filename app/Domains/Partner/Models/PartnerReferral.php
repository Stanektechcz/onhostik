<?php

declare(strict_types=1);

namespace App\Domains\Partner\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Partner\Enums\ReferralStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ReferralStatus $status
 */
class PartnerReferral extends Model
{
    protected $fillable = [
        'partner_profile_id',
        'referred_user_id',
        'referred_customer_id',
        'referral_code',
        'source_url',
        'landing_url',
        'ip_hash',
        'user_agent_hash',
        'first_seen_at',
        'registered_at',
        'converted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'          => ReferralStatus::class,
            'first_seen_at'   => 'datetime',
            'registered_at'   => 'datetime',
            'converted_at'    => 'datetime',
        ];
    }

    // ────────────────────────────────── relationships

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function referredCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }
}
