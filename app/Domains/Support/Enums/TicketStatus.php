<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

enum TicketStatus: string
{
    case Open     = 'open';
    case Pending  = 'pending';
    case Answered = 'answered';
    case Closed   = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open     => 'Otevřený',
            self::Pending  => 'Čeká na zákazníka',
            self::Answered => 'Zodpovězený',
            self::Closed   => 'Uzavřený',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open     => 'warning',
            self::Pending  => 'info',
            self::Answered => 'success',
            self::Closed   => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Closed;
    }
}
