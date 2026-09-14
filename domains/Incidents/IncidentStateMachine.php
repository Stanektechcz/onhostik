<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents;

use Onhost\Platform\StateMachine\StateMachine;

/** Blueprint §73.2: DETECTED → INVESTIGATING → IDENTIFIED → MITIGATING → MONITORING → RESOLVED → POSTMORTEM (reopen allowed from MONITORING/RESOLVED). */
final class IncidentStateMachine
{
    public const DETECTED = 'DETECTED';

    public const INVESTIGATING = 'INVESTIGATING';

    public const IDENTIFIED = 'IDENTIFIED';

    public const MITIGATING = 'MITIGATING';

    public const MONITORING = 'MONITORING';

    public const RESOLVED = 'RESOLVED';

    public const POSTMORTEM = 'POSTMORTEM';

    public static function machine(): StateMachine
    {
        return new StateMachine('incident', [
            self::DETECTED => ['label' => 'Detekováno', 'next' => [self::INVESTIGATING, self::IDENTIFIED, self::MONITORING, self::RESOLVED], 'tone' => 'hot', 'ui' => 'vysetrovani'],
            self::INVESTIGATING => ['label' => 'Vyšetřování', 'next' => [self::IDENTIFIED, self::MITIGATING, self::MONITORING, self::RESOLVED], 'tone' => 'hot', 'ui' => 'vysetrovani'],
            self::IDENTIFIED => ['label' => 'Příčina identifikována', 'next' => [self::MITIGATING, self::MONITORING, self::RESOLVED], 'tone' => 'warn', 'ui' => 'identifikovano'],
            self::MITIGATING => ['label' => 'Mitigace', 'next' => [self::MONITORING, self::RESOLVED, self::INVESTIGATING], 'tone' => 'warn', 'ui' => 'identifikovano'],
            self::MONITORING => ['label' => 'Monitoring', 'next' => [self::RESOLVED, self::INVESTIGATING, self::MITIGATING], 'tone' => 'warn', 'ui' => 'monitoring'],
            self::RESOLVED => ['label' => 'Vyřešeno', 'next' => [self::POSTMORTEM, self::INVESTIGATING], 'tone' => 'ok', 'ui' => 'vyreseno'],
            self::POSTMORTEM => ['label' => 'Post-mortem', 'next' => [], 'tone' => 'off', 'ui' => 'vyreseno'],
        ]);
    }
}
