<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject
 * @property string $body_html
 * @property string|null $body_text
 * @property string $status  draft|sending|sent|failed
 * @property int $recipients_count
 * @property int $sent_count
 * @property int|null $created_by
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NewsletterCampaign extends Model
{
    protected $fillable = [
        'subject', 'body_html', 'body_text', 'status',
        'recipients_count', 'sent_count', 'created_by', 'sent_at',
    ];

    protected $casts = [
        'sent_at'           => 'datetime',
        'recipients_count'  => 'integer',
        'sent_count'        => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /** @param Builder<self> $query */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    public function isDraft(): bool   { return $this->status === 'draft'; }
    public function isSending(): bool { return $this->status === 'sending'; }
    public function isSent(): bool    { return $this->status === 'sent'; }
    public function isFailed(): bool  { return $this->status === 'failed'; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'sending' => 'Odesílání…',
            'sent'    => 'Odesláno',
            'failed'  => 'Chyba',
            default   => 'Koncept',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'sending' => 'warning',
            'sent'    => 'success',
            'failed'  => 'danger',
            default   => 'secondary',
        };
    }

    public function progressPercent(): int
    {
        if ($this->recipients_count === 0) {
            return 0;
        }
        return (int) round($this->sent_count / $this->recipients_count * 100);
    }
}
