<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskWebhook extends Model
{
    public const EVENTS = [
        'ticket.created'        => 'Nový tiket',
        'ticket.replied'        => 'Odpověď na tiket',
        'ticket.closed'         => 'Tiket uzavřen',
        'ticket.status_changed' => 'Změna stavu tiketu',
    ];

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'timeout_seconds',
        'last_fired_at',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'events'        => 'array',
            'is_active'     => 'boolean',
            'last_fired_at' => 'datetime',
        ];
    }

    /** @return HasMany<HelpdeskWebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(HelpdeskWebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, (array) $this->events, true);
    }
}
