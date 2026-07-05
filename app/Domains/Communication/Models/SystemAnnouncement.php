<?php

declare(strict_types=1);

namespace App\Domains\Communication\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property 'info'|'warning'|'maintenance'|'feature' $type
 */
class SystemAnnouncement extends Model
{
    protected $fillable = [
        'title', 'body', 'type', 'icon', 'send_email',
        'is_published', 'published_at', 'expires_at', 'created_by', 'sent_count',
    ];

    protected $casts = [
        'send_email'   => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'info'        => 'Informace',
            'warning'     => 'Varování',
            'maintenance' => 'Maintenance',
            'feature'     => 'Novinka',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'info'        => 'badge bg-info text-dark',
            'warning'     => 'badge bg-warning text-dark',
            'maintenance' => 'badge bg-danger',
            'feature'     => 'badge bg-success',
        };
    }

    public function isActive(): bool
    {
        if (! $this->is_published) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }
}
