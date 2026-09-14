<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Models;

use Onhost\Platform\Eloquent\Model;

final class TaxCalculation extends Model
{
    protected static string $idPrefix = 'txc';

    protected $table = 'tax_calculations';

    protected function casts(): array
    {
        return ['inputs' => 'array', 'result' => 'array', 'total_tax_minor' => 'integer'];
    }
}
