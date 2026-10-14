<?php

declare(strict_types=1);

namespace Onhost\Domain\Partners;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Partners\Models\Partner;
use Onhost\Domain\Partners\Models\PartnerCommission;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * The commission-only cancellation of owner decision I-R6 (K5, TASK-0158): of a genuine double commission the NEWER row goes, by a
 * new record — never an amount edited by hand.
 *
 * Migration 000949 sets the newer rows aside before 000950 builds its unique index (`duplicate_of_id`, the invoice link moved to
 * `duplicate_invoice_id`, an unpaid row `duplicate` — never payable). Finance then cancels each set-aside row here, through the bus
 * (`partner.commission.cancel_duplicate`: HIGH, a fresh step-up and a second person; the bus keeps the audit with the approval):
 *
 *  · a row nobody was paid for (`duplicate`) becomes `cancelled`, and a `cancelled` reversal nets it to zero — nothing is ever paid;
 *  · a row already paid out (`paid`) stays paid — the money left — and the reversal is `payable` at once: a minus (or, for a doubled
 *    reversal, a plus) on the partner's next payouts, as a credit note after the grace window is;
 *  · anything else (a row that is not a set-aside duplicate, one already cancelled, a state the migration does not set aside) is
 *    refused and nothing changes. A second cancellation of one row finds the first one's reversal (and the unique `cancels_id`
 *    index stops two at once).
 *
 * The partner is told (`partner.commission.duplicate_cancelled`).
 */
final class DuplicateCommissions
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return array{commission: string, state: string, reversal: string, outcome: string, amount: Money} */
    public function cancel(string $commissionId): array
    {
        return DB::transaction(function () use ($commissionId): array {
            $row = PartnerCommission::query()->whereKey($commissionId)->lockForUpdate()->first() ?? throw DomainError::notFound('partner_commission');
            if ($row->duplicate_of_id === null) {
                throw new DomainError('commission_not_duplicate', 'Only the newer row of a double commission, set aside by the deploy, can be cancelled on its own. Any other correction is a credit note on the client invoice.', 422, ['field' => 'commission_id']);
            }
            if (PartnerCommission::query()->where('cancels_id', $row->id)->exists()) {
                throw $this->alreadyCancelled();
            }
            $outcome = match ($row->state) {
                PartnerCommission::DUPLICATE => 'cancelled',
                PartnerCommission::PAID => (int) $row->amount_minor >= 0 ? 'deducted' : 'credited',
                default => throw new DomainError('commission_not_cancellable', "A commission in state {$row->state} cannot be cancelled on its own.", 409, ['state' => $row->state]),
            };
            $reversal = $this->writeReversal($row, $outcome);
            if ($outcome === 'cancelled') {
                $row->forceFill(['state' => PartnerCommission::CANCELLED, 'cancelled_at' => now()])->save();
            }
            $amount = Money::minor(abs((int) $row->amount_minor), $row->currency);
            $this->announce($row, $outcome, $amount);

            return ['commission' => $row->id, 'state' => $row->state, 'reversal' => $reversal->id, 'outcome' => $outcome, 'amount' => $amount];
        });
    }

    private function writeReversal(PartnerCommission $row, string $outcome): PartnerCommission
    {
        $cancelled = $outcome === 'cancelled';
        try {
            return DB::transaction(fn () => PartnerCommission::query()->create([ // a savepoint: PostgreSQL refuses a second reversal of one row, the outer transaction is rolled back by the refusal
                'partner_id' => $row->partner_id, 'organization_id' => $row->organization_id, 'invoice_id' => null, 'period' => now()->format('Y-m'), 'kind' => 'reversal',
                'base_minor' => -(int) $row->base_minor, 'rate_pct' => $row->rate_pct, 'amount_minor' => -(int) $row->amount_minor, 'currency' => $row->currency,
                'state' => $cancelled ? PartnerCommission::CANCELLED : PartnerCommission::PAYABLE, 'invoice_paid_at' => now(), 'payable_at' => $cancelled ? null : now(),
                'cancelled_at' => $cancelled ? now() : null, 'cancels_id' => $row->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw $this->alreadyCancelled();
        }
    }

    /** The partner reads the outcome; finance's reason stays with the command in the audit (the bus records its payload). */
    private function announce(PartnerCommission $row, string $outcome, Money $amount): void
    {
        $organizationId = Partner::query()->whereKey($row->partner_id)->value('organization_id');
        if ($organizationId === null) {
            Log::warning('Double commission cancelled, the partner has no organization to tell', ['commission' => $row->id, 'outcome' => $outcome]);

            return;
        }
        $invoice = Invoice::query()->whereKey($row->duplicate_invoice_id)->value('number');
        $this->outbox->publish(GenericEvent::of('partner.commission.duplicate_cancelled', 'partner_commission', $row->id, [
            'invoice' => $invoice, 'amount' => $amount, 'outcome' => $outcome,
        ], $organizationId));
    }

    private function alreadyCancelled(): DomainError
    {
        return new DomainError('commission_already_cancelled', 'This commission has already been cancelled.', 409);
    }
}
