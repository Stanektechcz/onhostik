<?php

declare(strict_types=1);

namespace App\Domains\Partner\Models;

use App\Domains\Partner\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property PayoutStatus $status
 * @property int $amount  minor units
 */
class PartnerPayout extends Model
{
    use LogsActivity;

    protected $fillable = [
        'partner_profile_id',
        'amount',
        'currency',
        'status',
        'method',
        'requested_at',
        'processed_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'status'       => PayoutStatus::class,
            'amount'       => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'processed_at', 'admin_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('partner');
    }

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partnerProfile(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class);
    }

    /** @return HasMany<PartnerCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }

    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 0, ',', ' ') . ' ' . $this->currency;
    }
}
