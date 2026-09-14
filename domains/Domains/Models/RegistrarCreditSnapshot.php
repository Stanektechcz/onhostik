<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Models;

use Onhost\Platform\Eloquent\Model;

/** Registrar credit runway sample (blueprint §46.6). */
final class RegistrarCreditSnapshot extends Model
{
    protected static string $idPrefix = 'rcs';

    protected $table = 'registrar_credit_snapshots';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['balance_minor' => 'integer', 'renewals_30d_minor' => 'integer', 'renewals_30d_count' => 'integer', 'runway_days' => 'integer', 'below_minimum' => 'boolean', 'taken_at' => 'datetime'];
    }
}
