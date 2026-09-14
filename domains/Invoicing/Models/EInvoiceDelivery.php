<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Onhost\Platform\Eloquent\Model;

final class EInvoiceDelivery extends Model
{
    protected static string $idPrefix = 'eid';

    protected $table = 'einvoice_deliveries';

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'next_attempt_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
