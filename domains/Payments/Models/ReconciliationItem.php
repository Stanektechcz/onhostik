<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Models;

use Onhost\Platform\Eloquent\Model;

final class ReconciliationItem extends Model
{
    protected static string $idPrefix = 'rci';

    protected $table = 'reconciliation_items';

    protected function casts(): array
    {
        return ['expected_minor' => 'integer', 'actual_minor' => 'integer', 'resolved_at' => 'datetime'];
    }
}
