<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Enums;

enum GdprRequestStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Rejected   = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Čeká',
            self::Processing => 'Zpracovává se',
            self::Completed  => 'Dokončeno',
            self::Rejected   => 'Zamítnuto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending    => 'warning',
            self::Processing => 'info',
            self::Completed  => 'success',
            self::Rejected   => 'danger',
        };
    }
}
