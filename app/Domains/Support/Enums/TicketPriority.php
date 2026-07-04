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

    /** SLA response time in hours per priority level. */
    public function slaHours(): int
    {
        return match ($this) {
            self::Low    => 48,
            self::Normal => 24,
            self::High   => 8,
            self::Urgent => 4,
        };
    }

    /** Returns the next higher priority (Urgent stays Urgent). */
    public function escalated(): self
    {
        return match ($this) {
            self::Low    => self::Normal,
            self::Normal => self::High,
            self::High   => self::Urgent,
            self::Urgent => self::Urgent,
        };
    }
}
