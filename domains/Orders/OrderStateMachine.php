<?php

declare(strict_types=1);

namespace Onhost\Domain\Orders;

use Onhost\Platform\StateMachine\StateMachine;

final class OrderStateMachine
{
    public const NEW = 'NEW';

    public const PENDING_PAYMENT = 'PENDING_PAYMENT';

    public const PAID = 'PAID';

    public const PROVISIONING = 'PROVISIONING';

    public const PARTIALLY_ACTIVE = 'PARTIALLY_ACTIVE';

    public const ACTIVE = 'ACTIVE';

    public const SUSPENDED = 'SUSPENDED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public static function machine(): StateMachine
    {
        return new StateMachine('order', [
            self::NEW => ['label' => 'Nová', 'next' => [self::PENDING_PAYMENT, self::PAID, self::CANCELLED], 'tone' => 'warn', 'ui' => 'nova'],
            self::PENDING_PAYMENT => ['label' => 'Čeká na platbu', 'next' => [self::PAID, self::CANCELLED], 'tone' => 'warn', 'ui' => 'nova'],
            self::PAID => ['label' => 'Zaplaceno', 'next' => [self::PROVISIONING, self::CANCELLED], 'tone' => 'ok', 'ui' => 'zaplaceno'],
            self::PROVISIONING => ['label' => 'Provisioning', 'next' => [self::ACTIVE, self::PARTIALLY_ACTIVE, self::FAILED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::PARTIALLY_ACTIVE => ['label' => 'Částečně aktivní', 'next' => [self::ACTIVE, self::FAILED, self::CANCELLED], 'tone' => 'warn', 'ui' => 'provisioning'],
            self::ACTIVE => ['label' => 'Aktivní', 'next' => [self::SUSPENDED, self::CANCELLED], 'tone' => 'ok', 'ui' => 'aktivni'],
            self::SUSPENDED => ['label' => 'Pozastaveno', 'next' => [self::ACTIVE, self::CANCELLED], 'tone' => 'hot', 'ui' => 'pozastaveno'],
            self::FAILED => ['label' => 'Selhalo', 'next' => [self::PROVISIONING, self::CANCELLED], 'tone' => 'hot', 'ui' => 'provisioning'],
            self::CANCELLED => ['label' => 'Zrušeno', 'next' => [], 'tone' => 'off', 'ui' => 'zruseno'],
        ]);
    }

    /** Prototype slug for the API-backed store (`onhost-store.api.js`). */
    public static function uiState(string $state): string
    {
        return self::machine()->definition[$state]['ui'] ?? 'nova';
    }
}
