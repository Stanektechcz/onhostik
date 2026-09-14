<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing\Models;

use Onhost\Platform\Eloquent\Model;

final class InvoiceLine extends Model
{
    protected static string $idPrefix = 'il';

    protected $table = 'invoice_lines';

    protected function casts(): array
    {
        return [
            'position' => 'integer', 'qty' => 'string', 'unit_net_minor' => 'integer', 'discount_minor' => 'integer', 'net_minor' => 'integer',
            'tax_rate' => 'string', 'tax_minor' => 'integer', 'total_minor' => 'integer', 'period_from' => 'date', 'period_to' => 'date',
        ];
    }
}
