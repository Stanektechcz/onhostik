<?php

declare(strict_types=1);

namespace App\Domains\Billing\Events;

use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CreditBalanceLow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly Money $balance,
    ) {}
}
