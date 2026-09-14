<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Models;

use Onhost\Platform\Eloquent\Model;

/** SLA credit candidate → approved → issued (DK credit note + non-refundable wallet credit). */
final class SlaCredit extends Model
{
    protected static string $idPrefix = 'slc';

    protected $table = 'sla_credits';

    public const STATES = ['candidate', 'approved', 'issued', 'rejected'];

    protected function casts(): array
    {
        return ['calculation' => 'array', 'availability_pct' => 'float', 'credit_percent' => 'integer', 'amount_minor' => 'integer', 'issued_at' => 'datetime'];
    }
}
