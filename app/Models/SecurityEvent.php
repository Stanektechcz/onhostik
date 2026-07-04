<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $event_type
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $email
 * @property array<string, mixed>|null $metadata
 */
class SecurityEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'user_agent',
        'email',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->event_type) {
            'login'         => 'Přihlášení',
            'login_failed'  => 'Neúspěšný pokus o přihlášení',
            'logout'        => 'Odhlášení',
            'new_ip_login'  => 'Přihlášení z nové IP adresy',
            default         => $this->event_type,
        };
    }

    public function severityClass(): string
    {
        return match ($this->event_type) {
            'login_failed' => 'danger',
            'new_ip_login' => 'warning',
            'logout'       => 'secondary',
            default        => 'success',
        };
    }
}
