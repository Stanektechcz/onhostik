<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message in a support chat thread.
 *
 * role: user (customer) · bot (AI) · agent (live support) · system (events).
 *
 * @property int $id
 * @property string $role
 * @property string $body
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class SupportChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'author_id',
        'role',
        'body',
        'meta',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SupportChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportChatConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
