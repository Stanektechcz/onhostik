<?php

declare(strict_types=1);

namespace App\Domains\Billing\Events;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InvoicePaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Payment $payment,
    ) {}
}
