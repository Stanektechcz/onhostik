<?php

declare(strict_types=1);

namespace App\Domains\Partner\Models;

use App\Domains\Partner\Enums\PartnerStatus;
use App\Domains\Shared\Traits\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property PartnerStatus $status
 * @property float $commission_rate_percent
 */
class PartnerProfile extends Model
{
    use HasFactory;
    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'referral_code',
        'status',
        'commission_rate_percent',
        'payout_method',
        'payout_details_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'status'                 => PartnerStatus::class,
            'commission_rate_percent' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'commission_rate_percent', 'payout_method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('partner');
    }

    // ────────────────────────────────── relationships

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PartnerReferral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(PartnerReferral::class);
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }

    /** @return HasMany<PartnerPayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(PartnerPayout::class);
    }

    // ────────────────────────────────── helpers

    public function getPayoutDetails(): ?array
    {
        if ($this->payout_details_encrypted === null) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($this->payout_details_encrypted), true);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setPayoutDetails(?array $details): void
    {
        $this->payout_details_encrypted = $details !== null
            ? Crypt::encryptString(json_encode($details, JSON_THROW_ON_ERROR))
            : null;
    }

    public function isActive(): bool
    {
        return $this->status === PartnerStatus::Active;
    }
}
