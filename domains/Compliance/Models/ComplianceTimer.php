<?php

declare(strict_types=1);

namespace Onhost\Domain\Compliance\Models;

use Onhost\Platform\Eloquent\Model;

/** Regulatory deadline clock: running → met | missed | waived. Warned once at 75 % of the window. */
final class ComplianceTimer extends Model
{
    protected static string $idPrefix = 'tmr';

    protected $table = 'compliance_timers';

    public const LABELS = [
        'NIS2_EARLY_WARNING' => 'NIS2 včasné varování (24 h)', 'NIS2_NOTIFICATION' => 'NIS2 oznámení incidentu (72 h)', 'NIS2_FINAL_REPORT' => 'NIS2 závěrečná zpráva (1 měsíc)',
        'GDPR_72H' => 'GDPR oznámení ÚOOÚ (72 h)', 'DSA_ART18_PROMPT' => 'DSA čl. 18 oznámení orgánům', 'DATA_ACT_SWITCHING' => 'Data Act přechod k jinému poskytovateli (30 dní)',
    ];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'starts_at' => 'datetime', 'deadline_at' => 'datetime', 'warned_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function label(): string
    {
        return self::LABELS[$this->timer] ?? $this->timer;
    }

    public function remainingSeconds(): int
    {
        return (int) now()->diffInSeconds($this->deadline_at, false);
    }
}
