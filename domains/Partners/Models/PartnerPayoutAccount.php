<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * Where a partner's commission is paid (TASK-0040, program IF-14): set by the partner organization's owner alone, with a
 * step-up and a notice, usable from `usable_from` — never a field of a payout request. The latest row that is usable and
 * not cancelled is the account; a newer change cancels the one still cooling off.
 *
 * @property string $partner_id
 * @property string $iban
 * @property Carbon $usable_from cast `datetime`
 * @property Carbon|null $cancelled_at cast `datetime`
 */
final class PartnerPayoutAccount extends Model
{
    protected static string $idPrefix = 'ppa';

    protected $table = 'partner_payout_accounts';

    protected function casts(): array
    {
        return ['usable_from' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
