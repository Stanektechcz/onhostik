<?php

declare(strict_types=1);

namespace Onhost\Domain\Support\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Eloquent\Model;

/** Support ticket (blueprint §68). `number` is the customer-facing id (TK-2026-0001). */
final class Ticket extends Model
{
    protected static string $idPrefix = 'tk';

    protected $table = 'support_tickets';

    protected function casts(): array
    {
        return [
            'required_skills' => 'array', 'tags' => 'array', 'meta' => 'array', 'paused_minutes' => 'integer', 'reopen_count' => 'integer', 'escalation_level' => 'integer', 'csat_score' => 'integer',
            'first_response_due_at' => 'datetime', 'next_response_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'first_responded_at' => 'datetime', 'last_customer_message_at' => 'datetime', 'last_staff_message_at' => 'datetime',
            'waiting_since' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    public function slaEvents(): HasMany
    {
        return $this->hasMany(SlaEvent::class, 'ticket_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->state, [TicketStateMachine::RESOLVED, TicketStateMachine::CLOSED], true);
    }

    /** Prototype keys: otevreny | ceka | vyreseny. */
    public function uiState(): string
    {
        return TicketStateMachine::machine()->toArray()[$this->state]['ui'] ?? 'otevreny';
    }

    /** Prototype priority keys: nizka | stredni | vysoka. */
    public function uiPriority(): string
    {
        return match ($this->priority) {
            'p1', 'p2' => 'vysoka', 'p4' => 'nizka', default => 'stredni'
        };
    }
}
