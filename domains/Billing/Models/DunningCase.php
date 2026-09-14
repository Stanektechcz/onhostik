<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Onhost\Platform\Eloquent\Model;
use Onhost\Platform\StateMachine\StateMachine;

/** Payment delinquency separated from data destruction (blueprint §65.1). */
final class DunningCase extends Model
{
    protected static string $idPrefix = 'dun';

    protected $table = 'dunning_cases';

    public const DUE = 'DUE';

    public const OVERDUE_NOTICE = 'OVERDUE_NOTICE';

    public const GRACE = 'GRACE';

    public const SUSPENDED = 'SUSPENDED';

    public const TERMINATION_SCHEDULED = 'TERMINATION_SCHEDULED';

    public const TERMINATED = 'TERMINATED';

    public const RESOLVED = 'RESOLVED';

    protected function casts(): array
    {
        return ['notices_sent' => 'array', 'due_at' => 'datetime', 'next_action_at' => 'datetime', 'suspended_at' => 'datetime', 'termination_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public static function machine(): StateMachine
    {
        return new StateMachine('dunning', [
            self::DUE => ['label' => 'Po splatnosti', 'next' => [self::OVERDUE_NOTICE, self::RESOLVED], 'tone' => 'warn', 'ui' => 'po_splatnosti'],
            self::OVERDUE_NOTICE => ['label' => 'Upomínka', 'next' => [self::GRACE, self::RESOLVED], 'tone' => 'warn', 'ui' => 'po_splatnosti'],
            self::GRACE => ['label' => 'Ochranná lhůta', 'next' => [self::SUSPENDED, self::RESOLVED], 'tone' => 'hot', 'ui' => 'po_splatnosti'],
            self::SUSPENDED => ['label' => 'Pozastaveno', 'next' => [self::TERMINATION_SCHEDULED, self::RESOLVED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::TERMINATION_SCHEDULED => ['label' => 'Naplánováno zrušení', 'next' => [self::TERMINATED, self::RESOLVED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::TERMINATED => ['label' => 'Zrušeno', 'next' => [], 'tone' => 'off', 'ui' => 'zruseno'],
            self::RESOLVED => ['label' => 'Uhrazeno', 'next' => [], 'tone' => 'ok', 'ui' => 'zaplaceno'],
        ]);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(DunningAction::class, 'case_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->state, [self::TERMINATED, self::RESOLVED], true);
    }
}
