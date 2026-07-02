<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Carbon|null $last_reply_at
 * @property Carbon|null $closed_at
 */
class SupportTicket extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'assigned_to',
        'subject',
        'status',
        'priority',
        'department',
        'related_type',   // optional link: service / domain / order / invoice
        'related_id',
        'last_reply_at',
        'closed_at',
        'sla_deadline',
    ];

    protected function casts(): array
    {
        return [
            'status'        => TicketStatus::class,
            'priority'      => TicketPriority::class,
            'last_reply_at' => 'datetime',
            'closed_at'     => 'datetime',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<SupportTicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /** @return HasMany<SupportTicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class);
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
