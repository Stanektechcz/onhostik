<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Enums;

enum TaskStatus: string
{
    case Pending      = 'pending';
    case Running      = 'running';
    case Success      = 'success';
    case Failed       = 'failed';
    case Retrying     = 'retrying';
    case Cancelled    = 'cancelled';
    case ManualReview = 'manual_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending      => 'Čeká',
            self::Running      => 'Běží',
            self::Success      => 'Úspěch',
            self::Failed       => 'Selhalo',
            self::Retrying     => 'Opakuje se',
            self::Cancelled    => 'Zrušeno',
            self::ManualReview => 'Manuální kontrola',
        };
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Failed, self::ManualReview], true);
    }
}
