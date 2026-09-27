<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Domain\Partners\Models\PartnerPayout;
use Onhost\Domain\Partners\Models\PartnerPayoutAccount;
use Onhost\Platform\Money\Money;
use Throwable;

/**
 * The look at the payouts still open when TASK-0040 lands (permission program IF-14, D13): which of them the two holes may
 * have made — read only, no row is changed here.
 *
 *  - `amount_above_allocated` (audit P1): the payout stands for more than the commissions allocated to it — the second of two
 *    racing requests, which passed the balance check the first one then emptied;
 *  - `iban_changed` (audit P2): a bank transfer to an IBAN that was not the partner's confirmed account when it was asked
 *    for — no earlier paid payout went there and no payout account row was usable by then; the request itself had
 *    written it (no step-up, no notice).
 *
 * A partner's first bank transfer has nothing to compare its IBAN with: it is listed as `unconfirmed` for a look, never
 * frozen (freezing it would hold every new partner's first money on a guess). A payout whose commissions are in another
 * currency cannot be compared and is listed as `unknown`. Frozen payouts are skipped: they were looked at already.
 */
final class PayoutAnomalies
{
    /**
     * @return array{anomalies: list<array<string,mixed>>, released: list<array<string,mixed>>, unconfirmed: list<array<string,mixed>>, unknown: list<array<string,mixed>>, checked: int}
     */
    public function scan(): array
    {
        $open = PartnerPayout::query()->whereIn('state', ['requested', 'approved'])->whereNull('frozen_at')->orderBy('requested_at')->orderBy('number')->get();
        $sums = PartnerCommission::query()->whereIn('payout_id', $open->pluck('id')->all())->where('state', 'allocated')
            ->groupBy('payout_id', 'currency')->selectRaw('payout_id, currency, sum(amount_minor) as total')->get()->groupBy('payout_id');
        // a payout finance already released with a reason is not frozen again by the next run; it is listed as released
        $released = DB::table('audit_events')->where('action', 'partner.payout.unfreeze')->whereIn('resource_id', $open->pluck('id')->all())->pluck('resource_id')->all();
        $out = ['anomalies' => [], 'released' => [], 'unconfirmed' => [], 'unknown' => [], 'checked' => $open->count()];
        foreach ($open as $payout) {
            $row = ['payout_id' => $payout->id, 'number' => $payout->number, 'partner_id' => $payout->partner_id, 'state' => $payout->state,
                'amount' => $payout->net(), 'account' => PayoutAccounts::mask(PayoutAccounts::normalIban((string) $payout->iban)), 'requested_at' => $payout->requested_at?->toIso8601String()];
            $excess = self::excess($payout, $sums->get($payout->id, new Collection));
            if ($excess === false) {
                $out['unknown'][] = $row + ['kinds' => ['currency_mix']];

                continue;
            }
            $kinds = [];
            if ($excess !== null) {
                $kinds[] = 'amount_above_allocated';
                $row['allocated'] = $excess['allocated'];
            }
            $confirmed = $this->confirmedBefore($payout);
            $iban = PayoutAccounts::normalIban((string) $payout->iban);
            if ($payout->method === 'bank_transfer' && $iban !== '' && $confirmed !== [] && ! in_array($iban, $confirmed, true)) {
                $kinds[] = 'iban_changed';
                $row['confirmed'] = array_map(fn (string $i) => PayoutAccounts::mask($i), $confirmed);
            }
            if ($kinds !== []) {
                $out[in_array($payout->id, $released, true) ? 'released' : 'anomalies'][] = $row + ['kinds' => $kinds];
            } elseif ($payout->method === 'bank_transfer' && $iban !== '' && $confirmed === []) {
                $out['unconfirmed'][] = $row + ['kinds' => ['first_account']];
            }
        }

        return $out;
    }

    /**
     * The IBANs that were the partner's confirmed account when the payout was asked for: earlier paid payouts (grandfathered)
     * and payout account rows usable by then.
     *
     * @return list<string>
     */
    private function confirmedBefore(PartnerPayout $payout): array
    {
        $at = $payout->requested_at ?? now();
        $paid = PartnerPayout::query()->where('partner_id', $payout->partner_id)->where('id', '!=', $payout->id)->where('state', 'paid')->where('requested_at', '<', $at)->whereNotNull('iban')->pluck('iban');
        $accounts = PartnerPayoutAccount::query()->where('partner_id', $payout->partner_id)->where('usable_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('cancelled_at')->orWhere('cancelled_at', '>', $at))->pluck('iban');

        return $paid->merge($accounts)->map(fn ($i) => PayoutAccounts::normalIban((string) $i))->filter()->unique()->values()->all();
    }

    /**
     * How far the payout exceeds its allocated commissions: null when covered, false when the currencies cannot be compared.
     *
     * @param  Collection<int, mixed>  $rows  the allocated sums per currency
     * @return array{allocated: Money, excess: Money}|false|null
     */
    private static function excess(PartnerPayout $payout, Collection $rows): array|false|null
    {
        try {
            $amount = $payout->net();
            $sum = $rows->reduce(fn (Money $carry, $r) => $carry->add(Money::minor((int) $r->total, (string) $r->currency)), Money::zero($amount->currency));
        } catch (Throwable) {
            return false;
        }

        return $amount->greaterThan($sum) ? ['allocated' => $sum, 'excess' => $amount->subtract($sum)] : null;
    }
}
