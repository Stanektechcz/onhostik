<?php

declare(strict_types=1);

namespace App\Domains\Billing\Exceptions;

use Brick\Money\Money;
use RuntimeException;

class InsufficientCreditException extends RuntimeException
{
    public function __construct(
        public readonly Money $available,
        public readonly Money $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient credit: available %s, requested %s.',
            $available, $requested,
        ));
    }
}
