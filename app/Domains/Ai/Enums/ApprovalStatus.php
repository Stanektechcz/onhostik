<?php

declare(strict_types=1);

namespace App\Domains\Ai\Enums;

enum ApprovalStatus: string
{
    case Pending  = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending  => 'Čeká na schválení',
            self::Approved => 'Schváleno',
            self::Rejected => 'Zamítnuto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending  => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
