<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

final class BankStatementLine extends Model
{
    protected static string $idPrefix = 'bsl';

    protected $table = 'bank_statement_lines';

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'booked_at' => 'datetime'];
    }
}
