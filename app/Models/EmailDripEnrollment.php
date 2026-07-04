<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $next_step_index
 * @property Carbon|null $next_send_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $unsubscribed_at
 */
class EmailDripEnrollment extends Model
{
    protected $fillable = [
        'drip_sequence_id',
        'customer_id',
        'email',
        'name',
        'next_step_index',
        'next_send_at',
        'completed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at'      => 'datetime',
            'next_send_at'     => 'datetime',
            'completed_at'     => 'datetime',
            'unsubscribed_at'  => 'datetime',
            'next_step_index'  => 'integer',
        ];
    }

    /** @return BelongsTo<EmailDripSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(EmailDripSequence::class, 'drip_sequence_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isActive(): bool
    {
        return $this->completed_at === null && $this->unsubscribed_at === null;
    }
}
