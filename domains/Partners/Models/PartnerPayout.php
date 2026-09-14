<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Onhost\Platform\Eloquent\Model;

/** Payout request with the self-billed invoice snapshot (`self_billing`). */
final class PartnerPayout extends Model
{
    protected static string $idPrefix = 'pay';

    protected $table = 'partner_payouts';

    public const STATES = ['requested', 'approved', 'paid', 'rejected'];

    protected function casts(): array
    {
        return ['self_billing' => 'array', 'amount_minor' => 'integer', 'requested_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
