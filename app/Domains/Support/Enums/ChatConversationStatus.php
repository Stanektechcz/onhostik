<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

enum ChatConversationStatus: string
{
    case Bot          = 'bot';           // AI is handling it
    case WaitingAgent = 'waiting_agent'; // escalated, no agent has replied yet
    case AgentActive  = 'agent_active';  // a live agent is handling it
    case Closed       = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Bot          => 'AI asistent',
            self::WaitingAgent => 'Čeká na operátora',
            self::AgentActive  => 'Živá podpora',
            self::Closed       => 'Uzavřeno',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bot          => 'primary',
            self::WaitingAgent => 'warning',
            self::AgentActive  => 'success',
            self::Closed       => 'secondary',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
