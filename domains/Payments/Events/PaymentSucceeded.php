<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Events;

use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Platform\Commands\CommandContext;

/** Dispatched synchronously after the wallet was credited; listeners settle orders/invoices. */
final class PaymentSucceeded
{
    public function __construct(public readonly PaymentIntent $intent, public readonly CommandContext $context) {}
}
