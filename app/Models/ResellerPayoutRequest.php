<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Reseller\Models\ResellerProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
class ResellerPayoutRequest extends Model
{
    protected $table = 'reseller_payout_requests';

    protected $fillable = [
        'reseller_profile_id',
        'amount',
        'currency',
        'status',
        'requested_by',
        'processed_by',
        'processed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'amount'       => 'integer',
        ];
    }

    /** @return BelongsTo<ResellerProfile, $this> */
    public function resellerProfile(): BelongsTo
    {
        return $this->belongsTo(ResellerProfile::class);
    }

    /**
     * @param  Builder<ResellerPayoutRequest>  $query
     * @return Builder<ResellerPayoutRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
