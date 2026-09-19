<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Models;

use Onhost\Platform\StateMachine\StateMachine;

/** Blueprint §5.2 service state machine. */
final class ServiceStateMachine
{
    public const PENDING_PAYMENT = 'PENDING_PAYMENT';

    public const PAID = 'PAID';

    public const PROVISIONING = 'PROVISIONING';

    public const VERIFYING = 'VERIFYING';

    public const ACTIVE = 'ACTIVE';

    public const DEGRADED = 'DEGRADED';

    public const RESIZING = 'RESIZING';

    public const SUSPENDING = 'SUSPENDING';

    public const SUSPENDED = 'SUSPENDED';

    public const RESUMING = 'RESUMING';

    public const TERMINATING = 'TERMINATING';

    public const TERMINATED = 'TERMINATED';

    public const FAILED = 'FAILED';

    public static function machine(): StateMachine
    {
        return new StateMachine('service', [
            self::PENDING_PAYMENT => ['label' => 'Čeká na platbu', 'next' => [self::PAID, self::TERMINATED, self::FAILED], 'tone' => 'warn', 'ui' => 'nova'],
            self::PAID => ['label' => 'Zaplaceno', 'next' => [self::PROVISIONING, self::FAILED, self::TERMINATED], 'tone' => 'warn', 'ui' => 'zaplaceno'],
            self::PROVISIONING => ['label' => 'Provisioning', 'next' => [self::VERIFYING, self::ACTIVE, self::DEGRADED, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::VERIFYING => ['label' => 'Ověřování', 'next' => [self::ACTIVE, self::DEGRADED, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::ACTIVE => ['label' => 'Aktivní', 'next' => [self::DEGRADED, self::RESIZING, self::SUSPENDING, self::TERMINATING, self::FAILED], 'tone' => 'ok', 'ui' => 'aktivni'],
            self::DEGRADED => ['label' => 'Degradováno', 'next' => [self::ACTIVE, self::SUSPENDING, self::TERMINATING, self::FAILED], 'tone' => 'hot', 'ui' => 'aktivni'],
            self::RESIZING => ['label' => 'Změna zdrojů', 'next' => [self::ACTIVE, self::DEGRADED, self::FAILED], 'tone' => 'warn', 'ui' => 'aktivni'],
            self::SUSPENDING => ['label' => 'Pozastavuje se', 'next' => [self::SUSPENDED, self::ACTIVE, self::FAILED], 'tone' => 'warn', 'ui' => 'pozastaveno'], // ACTIVE: the panel refused the suspend, the service never stopped
            self::SUSPENDED => ['label' => 'Pozastaveno', 'next' => [self::RESUMING, self::TERMINATING], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::RESUMING => ['label' => 'Obnovuje se', 'next' => [self::ACTIVE, self::SUSPENDED, self::FAILED], 'tone' => 'warn', 'ui' => 'pozastaveno'], // SUSPENDED: the panel refused the resume, the service is still down
            self::TERMINATING => ['label' => 'Ukončuje se', 'next' => [self::TERMINATED, self::FAILED], 'tone' => 'warn', 'ui' => 'zruseno'],
            self::TERMINATED => ['label' => 'Ukončeno', 'next' => [], 'tone' => 'off', 'ui' => 'zruseno'],
            self::FAILED => ['label' => 'Selhalo (zásah operátora)', 'next' => [self::PROVISIONING, self::ACTIVE, self::SUSPENDED, self::TERMINATING, self::TERMINATED], 'tone' => 'hot', 'ui' => 'provisioning'],
        ]);
    }

    public static function uiState(string $state): string
    {
        return self::machine()->definition[$state]['ui'] ?? 'nova';
    }
}
