<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Customer\Models\Customer;
use App\Domains\Shared\Traits\HasUuid;
use App\Domains\Support\Enums\ChatConversationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single support chat thread. Starts on the AI bot; the customer can
 * escalate to a live agent, after which agent + customer messages are all
 * persisted here (nothing lives only in the browser).
 *
 * @property int $id
 * @property string $uuid
 * @property ChatConversationStatus $status
 * @property Carbon|null $last_message_at
 * @property Carbon|null $closed_at
 */
class SupportChatConversation extends Model
{
    use HasUuid;

    protected $fillable = [
        'customer_id',
        'started_by',
        'assigned_to',
        'status',
        'subject',
        'last_message_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'          => ChatConversationStatus::class,
            'last_message_at' => 'datetime',
            'closed_at'       => 'datetime',
        ];
    }

    /** @return HasMany<SupportChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class, 'conversation_id')->orderBy('id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
