<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Owner decision R7 (TASK-0097): a partner commission becomes payable only 30 days after the client paid the invoice.
 *
 * Before, a commission was payable the moment the invoice was paid: the client could pay, the partner be paid out the same
 * day, and the money go back to the client by a credit note — a refund, a withdrawal, a chargeback refund all write one —
 * so ONhost paid a commission on money it returned. Now:
 *
 *  - a new commission is `pending` until `payable_at` (paid + 30 days); a payout takes only `payable` rows (PartnerPayouts),
 *    so nothing pending is ever in a payout, an automatic one included;
 *  - a credit note while the commission is still pending cancels it (given back in full) or adds a pending reversal in
 *    proportion that matures with it; partial credit notes add up to the whole and then cancel it;
 *  - once matured the commission is locked: a later credit note never reaches back into it — its reversal is payable at once,
 *    a minus that the next payouts carry (the balance may go below zero; no payout is possible until it is earned back);
 *  - the maturing job (`onhost:partners:mature-commissions`, PartnerCommand `commissions.mature` through the bus) flips each
 *    row with a conditional update, so overlapping runs mature every commission once and announce it once.
 *
 * A credit note is only matched to a commission of its own organization's invoice (an invoice's credit note is written for
 * the invoice's organization; a row claiming otherwise reaches nothing).
 */
final class CommissionGrace
{
    /** R7: days between the client's payment and the commission becoming payable. */
    public const DAYS = 30;

    /** At most this many commissions mature in one run; the hourly schedule takes the rest. */
    public const BATCH = 1000;

    public function __construct(
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public static function payableAt(CarbonInterface $paidAt): Carbon
    {
        return Carbon::instance($paidAt)->copy()->addDays(self::DAYS);
    }

    /**
     * A credit note against an invoice that carries a commission (`invoice.issued`, AccruePartnerCommission). Idempotent: one
     * reversal per credit note (unique (invoice_id, kind)); the commission row is locked, so two credit notes of one invoice,
     * or a credit note and the maturing job, wait for each other.
     */
    public function reverseForCreditNote(Invoice $creditNote): ?PartnerCommission
    {
        if ($creditNote->type !== 'credit_note' || $creditNote->corrects_invoice_id === null) {
            return null;
        }

        return DB::transaction(function () use ($creditNote): ?PartnerCommission {
            $commission = PartnerCommission::query()->where('invoice_id', $creditNote->corrects_invoice_id)->where('kind', '!=', 'reversal')->where('fragment', false)->lockForUpdate()->first();
            if ($commission === null || $commission->organization_id !== $creditNote->organization_id) {
                return null;
            }
            if (PartnerCommission::query()->where('invoice_id', $creditNote->id)->where('kind', 'reversal')->exists()) {
                return null;
            }
            $cut = $this->cut($commission, $creditNote);
            if ($cut === null) {
                return null;
            }
            $outcome = $commission->state === PartnerCommission::PENDING ? ($cut['whole'] ? 'cancelled' : 'reduced') : 'adjusted';
            $reversal = $this->writeReversal($commission, $creditNote, $cut, $outcome);
            if ($reversal === null) {
                return null; // the same credit note, delivered twice at once: the other delivery wrote it
            }
            if ($outcome === 'cancelled') {
                $this->cancel($commission, $cut['notes']);
            }
            $this->announce($commission, $creditNote, $reversal, $outcome, $cut['left']);

            return $reversal;
        });
    }

    /**
     * Pending commissions whose day came become payable. Each row is flipped by a conditional update (`state = pending`), so a
     * run that overlaps another — or a credit note cancelling the row meanwhile — flips nothing twice; only what this run
     * flipped is announced, one event per partner and currency.
     *
     * @return array{matured:int, partners:int}
     */
    public function mature(CommandContext $context, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance($now ?? now());
        $due = PartnerCommission::query()->where('state', PartnerCommission::PENDING)->whereNotNull('payable_at')->where('payable_at', '<=', $now)
            ->orderBy('payable_at')->orderBy('id')->limit(self::BATCH)->get();
        $flipped = [];
        foreach ($due as $commission) {
            $updated = PartnerCommission::query()->whereKey($commission->id)->where('state', PartnerCommission::PENDING)->update(['state' => PartnerCommission::PAYABLE, 'updated_at' => now()]);
            if ($updated === 1) {
                $flipped[$commission->partner_id][$commission->currency][] = $commission;
            }
        }
        $count = 0;
        foreach ($flipped as $partnerId => $byCurrency) {
            $organizationId = Partner::query()->whereKey($partnerId)->value('organization_id');
            foreach ($byCurrency as $currency => $rows) {
                $amount = Money::minor(array_sum(array_map(fn (PartnerCommission $c) => (int) $c->amount_minor, $rows)), $currency);
                $count += count($rows);
                $this->audit->record($context->withScope($organizationId), 'partner.commission.mature', 'succeeded', ['commissions' => count($rows), 'amount' => $amount], 'partner', $partnerId);
                if ($amount->isPositive()) { // a commission maturing with its pending reversal to nothing is not news for the partner
                    $this->outbox->publish(GenericEvent::of('partner.commissions.matured', 'partner', $partnerId, ['count' => count($rows), 'amount' => $amount], $organizationId));
                }
            }
        }

        return ['matured' => $count, 'partners' => count($flipped)];
    }

    /**
     * What this credit note takes off the commission, in proportion to the net it gives back: the base left after earlier credit
     * notes of the same invoice caps it, and the one that takes the rest takes exactly what is left of the amount (no haler
     * drifts away in rounding). Fragments a payout split off count with the commission they came from.
     *
     * @return array{base:int, amount:int, whole:bool, left:int, notes:list<string>}|null
     */
    private function cut(PartnerCommission $commission, Invoice $creditNote): ?array
    {
        $earned = (int) PartnerCommission::query()->where('invoice_id', $commission->invoice_id)->where('kind', '!=', 'reversal')->sum('amount_minor');
        $notes = Invoice::query()->where('type', 'credit_note')->where('corrects_invoice_id', $commission->invoice_id)->where('id', '!=', $creditNote->id)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $earlier = PartnerCommission::query()->whereIn('invoice_id', $notes)->where('kind', 'reversal')->get();
        $baseLeft = (int) $commission->base_minor + (int) $earlier->sum('base_minor');
        $amountLeft = $earned + (int) $earlier->sum('amount_minor');
        $credited = abs((int) $creditNote->subtotal_minor - (int) $creditNote->discount_minor);
        $base = min($credited, $baseLeft);
        if ($base <= 0 || $amountLeft <= 0 || (int) $commission->base_minor <= 0) {
            return null;
        }
        $whole = $base >= $baseLeft;
        $amount = $whole ? $amountLeft : min($amountLeft, intdiv(2 * $earned * $base + (int) $commission->base_minor, 2 * (int) $commission->base_minor));

        return ['base' => $base, 'amount' => $amount, 'whole' => $whole, 'left' => $amountLeft - $amount, 'notes' => $notes];
    }

    /** @param array{base:int, amount:int, whole:bool, left:int, notes:list<string>} $cut */
    private function writeReversal(PartnerCommission $commission, Invoice $creditNote, array $cut, string $outcome): ?PartnerCommission
    {
        [$state, $payableAt] = match ($outcome) {
            'cancelled' => [PartnerCommission::CANCELLED, null],
            'reduced' => [PartnerCommission::PENDING, $commission->payable_at], // matures with the commission it reduces
            default => [PartnerCommission::PAYABLE, now()],                      // the commission is locked: a minus on the next payouts
        };
        try {
            return DB::transaction(fn () => PartnerCommission::query()->create([ // a savepoint: PostgreSQL refuses the duplicate, the outer work stays
                'partner_id' => $commission->partner_id, 'organization_id' => $commission->organization_id, 'invoice_id' => $creditNote->id, 'period' => now()->format('Y-m'), 'kind' => 'reversal',
                'base_minor' => -$cut['base'], 'rate_pct' => $commission->rate_pct, 'amount_minor' => -$cut['amount'], 'currency' => $commission->currency, 'state' => $state,
                'invoice_paid_at' => now(), 'payable_at' => $payableAt, 'cancelled_at' => $state === PartnerCommission::CANCELLED ? now() : null,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Given back in full inside the window: the commission and the pending reversals of earlier credit notes are cancelled with
     * it — they net to zero and none of them is ever payable.
     *
     * @param  list<string>  $earlierNotes
     */
    private function cancel(PartnerCommission $commission, array $earlierNotes): void
    {
        $patch = ['state' => PartnerCommission::CANCELLED, 'cancelled_at' => now(), 'updated_at' => now()];
        PartnerCommission::query()->where('invoice_id', $commission->invoice_id)->where('kind', '!=', 'reversal')->where('state', PartnerCommission::PENDING)->update($patch);
        PartnerCommission::query()->whereIn('invoice_id', $earlierNotes)->where('kind', 'reversal')->where('state', PartnerCommission::PENDING)->update($patch);
    }

    private function announce(PartnerCommission $commission, Invoice $creditNote, PartnerCommission $reversal, string $outcome, int $left): void
    {
        $organizationId = Partner::query()->whereKey($commission->partner_id)->value('organization_id');
        $invoiceNumber = Invoice::query()->whereKey($commission->invoice_id)->value('number');
        $amount = Money::minor(-(int) $reversal->amount_minor, $commission->currency);
        $detail = ['invoice' => $invoiceNumber, 'credit_note' => $creditNote->number, 'client' => $commission->organization_id, 'amount' => $amount, 'left' => Money::minor($left, $commission->currency)];
        $action = ['cancelled' => 'partner.commission.cancel', 'reduced' => 'partner.commission.reduce', 'adjusted' => 'partner.commission.adjust'][$outcome];
        $this->audit->record(CommandContext::system('partner.commission')->withScope($organizationId), $action, 'succeeded', $detail, 'partner_commission', $commission->id);
        $this->outbox->publish(GenericEvent::of('partner.commission.'.$outcome, 'partner_commission', $commission->id, [
            'invoice' => $invoiceNumber, 'credit_note' => $creditNote->number, 'amount' => $amount, 'left' => Money::minor($left, $commission->currency), 'payable_at' => $commission->payable_at?->toIso8601String(),
        ], $organizationId));
    }
}
