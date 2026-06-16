<?php

declare(strict_types=1);

namespace App\Domains\Support\Enums;

enum TicketPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Nízká',
            self::Normal => 'Normální',
            self::High   => 'Vysoká',
            self::Urgent => 'Urgentní',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low    => 'gray',
            self::Normal => 'info',
            self::High   => 'warning',
            self::Urgent => 'danger',
        };
    }
}
