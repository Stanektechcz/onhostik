<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRating extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'support_ticket_id',
        'score',
        'comment',
        'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'score'    => 'integer',
            'rated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function label(): string
    {
        return match ($this->score) {
            5 => 'Výborný',
            4 => 'Dobrý',
            3 => 'Průměrný',
            2 => 'Slabý',
            default => 'Špatný',
        };
    }
}
