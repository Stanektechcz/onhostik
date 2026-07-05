<?php

declare(strict_types=1);

namespace App\Domains\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property 'pending'|'qualified'|'rewarded'|'expired' $status
 * @property Carbon|null $qualified_at
 * @property Carbon|null $rewarded_at
 */
class CustomerReferral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'status',
        'referrer_reward_haler',
        'referee_reward_haler',
        'currency',
        'qualified_at',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'qualified_at' => 'datetime',
            'rewarded_at'  => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function referee(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referee_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => 'Čeká na kvalifikaci',
            'qualified' => 'Kvalifikováno',
            'rewarded'  => 'Odměněno',
            'expired'   => 'Vypršelo',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'   => 'bg-secondary',
            'qualified' => 'bg-info',
            'rewarded'  => 'bg-success',
            'expired'   => 'bg-warning',
        };
    }
}
