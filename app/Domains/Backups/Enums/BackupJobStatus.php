<?php

declare(strict_types=1);

namespace App\Domains\Backups\Enums;

enum BackupJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed  = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeká',
            self::Running => 'Běží',
            self::Success => 'Hotovo',
            self::Failed  => 'Selhalo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Running => 'info',
            self::Success => 'success',
            self::Failed  => 'danger',
        };
    }
}
