<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Models;

use Onhost\Platform\Eloquent\Model;

final class VatValidation extends Model
{
    protected static string $idPrefix = 'vv';

    protected $table = 'vat_validations';

    protected function casts(): array
    {
        return ['valid' => 'boolean', 'raw' => 'array', 'checked_at' => 'datetime'];
    }
}
