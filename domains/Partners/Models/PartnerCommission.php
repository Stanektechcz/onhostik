<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Onhost\Platform\Eloquent\Model;

/** One commission line per paid client invoice (or a reversal for a credit note). */
final class PartnerCommission extends Model
{
    protected static string $idPrefix = 'pcm';

    protected $table = 'partner_commissions';

    protected function casts(): array
    {
        return ['base_minor' => 'integer', 'rate_pct' => 'integer', 'amount_minor' => 'integer', 'invoice_paid_at' => 'datetime'];
    }
}
