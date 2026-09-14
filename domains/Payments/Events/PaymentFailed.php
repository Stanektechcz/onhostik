<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Events;

use Onhost\Domain\Payments\Models\PaymentIntent;

final class PaymentFailed
{
    public function __construct(public readonly PaymentIntent $intent, public readonly string $reason) {}
}
