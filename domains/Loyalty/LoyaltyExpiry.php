<?php

declare(strict_types=1);

namespace Onhost\Domain\Loyalty;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Loyalty\Models\LoyaltyExpiryNotice;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Owner decision G-R2: points expire 24 months after they were credited, and the customer hears about it beforehand.
 *
 * Points are spent oldest first: whatever left the balance — redeemed, taken back, expired before — is counted against the oldest
 * credited points, and what is reserved by an unpaid order counts as spent too (its points must still be there when it is paid).
 * So the points due are the ones credited on or before the cutoff that nothing has used up: earned up to the cutoff, less every
 * debit, less the reservations. Writing the expiry row makes it a debit itself, so a second run on the same day finds nothing.
 * Points credited before the rule existed count as credited on `loyalty.expiry.counted_from` (they had been promised "never").
 * Points given back by a credit note are credited again on the day they come back.
 */
final class LoyaltyExpiry
{
    public const RULE = 'expiry';

    public function __construct(private readonly LoyaltyService $loyalty, private readonly LoyaltyRedemptions $redemptions, private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public static function months(): int
    {
        return max(1, (int) config('loyalty.expiry.months', 24));
    }

    public static function warnDays(): int
    {
        return max(1, (int) config('loyalty.expiry.warn_days', 30));
    }

    private static function countedFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) config('loyalty.expiry.counted_from', '2026-10-05'), 'UTC')->startOfDay();
    }

    /** Unspent points credited on or before `$cutoff`. */
    public function dueBy(string $organizationId, CarbonInterface $cutoff): int
    {
        if (self::countedFrom()->greaterThan($cutoff)) {
            return 0;
        }
        $earned = (int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('points', '>', 0)->where('created_at', '<=', $cutoff)->sum('points');
        $debits = -(int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('points', '<', 0)->sum('points');

        return max(0, $earned - $debits - $this->redemptions->reserved($organizationId));
    }

    /**
     * The daily run: points past their 24 months leave the balance (a row of their own, `expiry`).
     *
     * @return array{organizations:int, points:int}
     */
    public function expire(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $cutoff = $now->subMonthsNoOverflow(self::months());
        $out = ['organizations' => 0, 'points' => 0];
        if (self::countedFrom()->greaterThan($cutoff)) {
            return $out;
        }
        $context = CommandContext::system('loyalty expiry');
        foreach ($this->holders($cutoff) as $organizationId) {
            $taken = DB::transaction(function () use ($organizationId, $cutoff, $context) {
                Organization::query()->whereKey($organizationId)->lockForUpdate()->first();
                $due = $this->dueBy($organizationId, $cutoff);
                if ($due <= 0) {
                    return 0;
                }
                try {
                    DB::transaction(fn () => LoyaltyPoint::query()->create(['organization_id' => $organizationId, 'rule' => self::RULE, 'reference' => $cutoff->format('Y-m-d'), 'points' => -$due, 'note' => 'Propadlé body připsané do '.$cutoff->format('j. n. Y')]));
                } catch (QueryException) {
                    return 0; // this day's expiry is written already
                }
                $total = $this->loyalty->points($organizationId);
                $this->audit->record($context->withScope($organizationId), 'loyalty.expire', 'succeeded', ['points' => -$due, 'credited_until' => $cutoff->format('Y-m-d'), 'total' => $total], 'organization', $organizationId);
                $this->outbox->publish(GenericEvent::of('loyalty.expired', 'organization', $organizationId, ['points' => $due, 'total' => $total, 'credited_until' => $cutoff->format('Y-m-d')], $organizationId));

                return $due;
            });
            if ($taken > 0) {
                $out['organizations']++;
                $out['points'] += $taken;
            }
        }

        return $out;
    }

    /**
     * The warning before: points that expire within `warn_days`, once per organization and month of the first of them.
     *
     * @return int organizations warned
     */
    public function warn(?CarbonInterface $now = null): int
    {
        $now = CarbonImmutable::instance($now ?? now());
        $cutoff = $now->addDays(self::warnDays())->subMonthsNoOverflow(self::months());
        $warned = 0;
        foreach ($this->holders($cutoff) as $organizationId) {
            $soon = $this->dueBy($organizationId, $cutoff);
            $first = $soon > 0 ? $this->nextExpiry($organizationId) : null;
            if ($first === null) {
                continue;
            }
            try {
                DB::transaction(fn () => LoyaltyExpiryNotice::query()->create(['organization_id' => $organizationId, 'window' => $first->format('Y-m'), 'points' => $soon, 'notified_at' => now()]));
            } catch (QueryException) {
                continue; // warned already for this month
            }
            $this->outbox->publish(GenericEvent::of('loyalty.expiring', 'organization', $organizationId, ['points' => $soon, 'expires_on' => $first->format('Y-m-d'), 'total' => $this->loyalty->points($organizationId)], $organizationId));
            $warned++;
        }

        return $warned;
    }

    /** @return array{points:int, by:string, next_on:?string} what the panel shows: what expires within the warning window and when the next points do */
    public function upcoming(string $organizationId): array
    {
        $by = now()->addDays(self::warnDays());
        $next = $this->nextExpiry($organizationId);

        return ['points' => $this->dueBy($organizationId, CarbonImmutable::instance($by)->subMonthsNoOverflow(self::months())), 'by' => $by->format('Y-m-d'), 'next_on' => $next?->format('Y-m-d')];
    }

    /** The day the oldest unspent point expires, or null when nothing is left to expire. */
    public function nextExpiry(string $organizationId): ?CarbonImmutable
    {
        $skip = -(int) LoyaltyPoint::query()->where('organization_id', $organizationId)->where('points', '<', 0)->sum('points') + $this->redemptions->reserved($organizationId);
        $sum = 0;
        foreach (LoyaltyPoint::query()->where('organization_id', $organizationId)->where('points', '>', 0)->orderBy('created_at')->orderBy('id')->cursor() as $row) {
            $sum += (int) $row->points;
            if ($sum > $skip) {
                $credited = CarbonImmutable::instance($row->created_at);

                return ($credited->lessThan(self::countedFrom()) ? self::countedFrom() : $credited)->addMonthsNoOverflow(self::months());
            }
        }

        return null;
    }

    /** @return list<string> organizations with points credited on or before the cutoff */
    private function holders(CarbonInterface $cutoff): array
    {
        return LoyaltyPoint::query()->where('points', '>', 0)->where('created_at', '<=', $cutoff)->distinct()->orderBy('organization_id')->pluck('organization_id')->map(fn ($id) => (string) $id)->all();
    }
}
