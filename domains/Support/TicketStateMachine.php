<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Onhost\Platform\StateMachine\StateMachine;

/** Blueprint §68.3: NEW → TRIAGED → OPEN → WAITING_CUSTOMER/WAITING_INTERNAL → RESOLVED → CLOSED, with ESCALATED as a side state. */
final class TicketStateMachine
{
    public const NEW = 'NEW';

    public const TRIAGED = 'TRIAGED';

    public const OPEN = 'OPEN';

    public const WAITING_CUSTOMER = 'WAITING_CUSTOMER';

    public const WAITING_INTERNAL = 'WAITING_INTERNAL';

    public const ESCALATED = 'ESCALATED';

    public const RESOLVED = 'RESOLVED';

    public const CLOSED = 'CLOSED';

    public static function machine(): StateMachine
    {
        return new StateMachine('ticket', [
            self::NEW => ['label' => 'Nový', 'next' => [self::TRIAGED, self::OPEN, self::ESCALATED, self::RESOLVED], 'tone' => 'warn', 'ui' => 'otevreny'],
            self::TRIAGED => ['label' => 'Roztříděný', 'next' => [self::OPEN, self::WAITING_CUSTOMER, self::WAITING_INTERNAL, self::ESCALATED, self::RESOLVED], 'tone' => 'warn', 'ui' => 'otevreny'],
            self::OPEN => ['label' => 'Otevřený', 'next' => [self::WAITING_CUSTOMER, self::WAITING_INTERNAL, self::ESCALATED, self::RESOLVED], 'tone' => 'warn', 'ui' => 'otevreny'],
            self::WAITING_CUSTOMER => ['label' => 'Čeká na zákazníka', 'next' => [self::OPEN, self::RESOLVED, self::CLOSED, self::ESCALATED], 'tone' => 'off', 'ui' => 'ceka'],
            self::WAITING_INTERNAL => ['label' => 'Čeká interně', 'next' => [self::OPEN, self::WAITING_CUSTOMER, self::ESCALATED, self::RESOLVED], 'tone' => 'warn', 'ui' => 'otevreny'],
            self::ESCALATED => ['label' => 'Eskalováno', 'next' => [self::OPEN, self::WAITING_CUSTOMER, self::WAITING_INTERNAL, self::RESOLVED], 'tone' => 'hot', 'ui' => 'otevreny'],
            self::RESOLVED => ['label' => 'Vyřešeno', 'next' => [self::OPEN, self::CLOSED], 'tone' => 'ok', 'ui' => 'vyreseny'],
            self::CLOSED => ['label' => 'Uzavřeno', 'next' => [self::OPEN], 'tone' => 'off', 'ui' => 'vyreseny'],
        ]);
    }
}
