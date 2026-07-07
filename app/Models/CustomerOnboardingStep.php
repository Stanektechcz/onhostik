<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class CustomerOnboardingStep extends Model
{
    protected $table = 'customer_onboarding_steps';

    protected $fillable = [
        'customer_id',
        'step',
        'description',
        'is_required',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_required'  => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
