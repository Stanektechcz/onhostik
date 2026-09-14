<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\StateMachine\StateMachine;

/** Blueprint §63.3. Redirect to a success page is never a state change; only verified provider status is. */
final class PaymentStateMachineStates
{
    public const CREATED = 'CREATED';

    public const PENDING_CUSTOMER = 'PENDING_CUSTOMER';

    public const AUTHENTICATION_REQUIRED = 'AUTHENTICATION_REQUIRED';

    public const PROCESSING = 'PROCESSING';

    public const SUCCEEDED = 'SUCCEEDED';

    public const FAILED = 'FAILED';

    public const CANCELED = 'CANCELED';

    public const PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';

    public const REFUNDED = 'REFUNDED';

    public const CHARGEBACK = 'CHARGEBACK';

    public static function machine(): StateMachine
    {
        return new StateMachine('payment', [
            self::CREATED => ['label' => 'Vytvořeno', 'next' => [self::PENDING_CUSTOMER, self::AUTHENTICATION_REQUIRED, self::PROCESSING, self::SUCCEEDED, self::FAILED, self::CANCELED]],
            self::PENDING_CUSTOMER => ['label' => 'Čeká na zákazníka', 'next' => [self::AUTHENTICATION_REQUIRED, self::PROCESSING, self::SUCCEEDED, self::FAILED, self::CANCELED]],
            self::AUTHENTICATION_REQUIRED => ['label' => '3-D Secure', 'next' => [self::PROCESSING, self::SUCCEEDED, self::FAILED, self::CANCELED]],
            self::PROCESSING => ['label' => 'Zpracovává se', 'next' => [self::SUCCEEDED, self::FAILED, self::CANCELED]],
            self::SUCCEEDED => ['label' => 'Zaplaceno', 'next' => [self::PARTIALLY_REFUNDED, self::REFUNDED, self::CHARGEBACK]],
            self::FAILED => ['label' => 'Selhalo', 'next' => []],
            self::CANCELED => ['label' => 'Zrušeno', 'next' => []],
            self::PARTIALLY_REFUNDED => ['label' => 'Částečně vráceno', 'next' => [self::REFUNDED, self::CHARGEBACK]],
            self::REFUNDED => ['label' => 'Vráceno', 'next' => [self::CHARGEBACK]],
            self::CHARGEBACK => ['label' => 'Chargeback', 'next' => []],
        ]);
    }
}
