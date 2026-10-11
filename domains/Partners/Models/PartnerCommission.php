<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners\Models;

use Illuminate\Support\Carbon;
use Onhost\Platform\Eloquent\Model;

/**
 * One commission line per paid client invoice (or a reversal for a credit note).
 *
 * States (owner decision R7, TASK-0097): `pending` until 30 days after the client paid (`payable_at`), then `payable`; a payout
 * takes it `allocated`, its payment `paid`. A credit note inside the window makes it `cancelled` (given back in full) or adds a
 * pending reversal that matures with it; after the window the reversal is `payable` at once — a minus on the next payouts.
 *
 * @property Carbon|null $invoice_paid_at
 * @property Carbon|null $payable_at
 * @property Carbon|null $cancelled_at
 * @property bool $fragment
 */
final class PartnerCommission extends Model
{
    public const PENDING = 'pending';

    public const PAYABLE = 'payable';

    public const ALLOCATED = 'allocated';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    protected static string $idPrefix = 'pcm';

    protected $table = 'partner_commissions';

    protected function casts(): array
    {
        return ['base_minor' => 'integer', 'rate_pct' => 'integer', 'amount_minor' => 'integer', 'invoice_paid_at' => 'datetime', 'payable_at' => 'datetime', 'cancelled_at' => 'datetime', 'fragment' => 'boolean'];
    }

    /**
     * A payout splits the last commission it takes so it matches the requested amount exactly (PartnerPayouts::allocate copies
     * the row with `replicate()`): the copy is a fragment of the same commission — the same invoice and kind — and the unique
     * index on (invoice_id, kind) leaves fragments out (R7).
     */
    protected static function booted(): void
    {
        self::replicating(function (self $copy): void {
            $copy->setAttribute('fragment', true);
        });
    }
}
