<?php

declare(strict_types=1);

namespace Onhost\Domain\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\Models\ChargebackRequest;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Billing\Models\Withdrawal;
use Onhost\Domain\Invoicing\AccountingClock;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Early termination of a prepaid (yearly) subscription — owner decision E3 (2026-10-15): when a customer ends a service paid a
 * year in advance, what comes back is the paid price of the WHOLE months that have not started yet; the month running on the
 * day of the request counts as used and a part of a month never comes back. The refund is a credit note of the very lines
 * that were paid (`InvoiceService::giveBack`), so the VAT goes back on the credit note with the revenue it had earned.
 *
 * This is not the consumer's statutory withdrawal (WithdrawalService, fourteen days, prorated by days): that stays exactly as it
 * is and always wins — a service with a withdrawal (or a chargeback being settled) is refused here. Run once per service, after
 * the service has been terminated (HIGH risk: `EarlyTerminationCommand`).
 */
final class EarlyTerminationPolicy
{
    public const TAG = 'early_termination';

    public function __construct(
        private readonly ChargebackService $chargebacks,
        private readonly InvoiceService $invoices,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * Months a paid period `$from`..`$to` (both inclusive) covers, and how many of them start after `$today` (whole months
     * not used yet). A period that has not started yet is unused in full.
     *
     * @return array{months:int, unused:int}
     */
    public static function months(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today): array
    {
        $from = $from->startOfDay();
        $end = $to->startOfDay()->addDay();
        $months = 0;
        while ($months < 1200 && $from->addMonthsNoOverflow($months + 1)->lessThanOrEqualTo($end)) {
            $months++;
        }
        $unused = 0;
        for ($k = 0; $k < $months; $k++) {
            if ($from->addMonthsNoOverflow($k)->greaterThan($today->startOfDay())) {
                $unused++;
            }
        }

        return ['months' => $months, 'unused' => $unused];
    }

    /** The gross amount of a line's whole unused months, never more than what is left of the line after earlier credit notes. */
    public static function refundOf(int $totalMinor, int $leftMinor, int $months, int $unused): int
    {
        if ($months <= 0 || $unused <= 0) {
            return 0;
        }

        return max(0, min($leftMinor, intdiv($totalMinor * $unused, $months)));
    }

    /**
     * What ending `$service` on `$asOf` gives back: the paid lines of the service still running, each by its whole unused months.
     *
     * @return array{currency:string, refund:Money, lines:list<array<string,mixed>>}
     */
    public function estimate(Service $service, ?CarbonImmutable $asOf = null): array
    {
        $today = CarbonImmutable::parse(AccountingClock::date($asOf));
        $base = $this->chargebacks->estimate($service, 100, $asOf); // the paid lines whose period has not run out (never the price list)
        $lines = [];
        foreach ($base['lines'] as $row) {
            ['months' => $months, 'unused' => $unused] = self::months(CarbonImmutable::parse($row['period_from']), CarbonImmutable::parse($row['period_to']), $today);
            $line = InvoiceLine::query()->find($row['line_id']);
            $refund = self::refundOf((int) ($line->total_minor ?? 0), (int) $row['left_minor'], $months, $unused);
            if ($refund > 0) {
                $lines[] = ['line_id' => $row['line_id'], 'invoice_id' => $row['invoice_id'], 'number' => $row['number'], 'period_from' => $row['period_from'], 'period_to' => $row['period_to'],
                    'months' => $months, 'months_unused' => $unused, 'left_minor' => (int) $row['left_minor'], 'refund_minor' => $refund];
            }
        }

        return ['currency' => $base['currency'], 'refund' => Money::minor(array_sum(array_column($lines, 'refund_minor')), $base['currency']), 'lines' => $lines];
    }

    /**
     * Gives back the whole unused months of a terminated service as credit notes, once.
     *
     * @return array{service_id:string, refund:Money, credit_notes:list<string>, to_credit:Money}
     */
    public function settle(Service $service, CommandContext $context): array
    {
        return DB::transaction(function () use ($service, $context) {
            $service = Service::query()->lockForUpdate()->find($service->id) ?? throw DomainError::notFound('service');
            if (! empty(data_get($service->tags, self::TAG))) {
                throw new DomainError('early_termination_settled', 'Za předčasné ukončení této služby už jsme nevyužité měsíce vrátili.', 409);
            }
            if ($service->state !== ServiceStateMachine::TERMINATED) {
                throw new DomainError('service_state_invalid', 'Nevyužité měsíce vracíme až po ukončení služby.', 409, ['state' => $service->state]);
            }
            if (Withdrawal::query()->where('service_id', $service->id)->exists()
                || ChargebackRequest::query()->where('service_id', $service->id)->whereIn('state', [ChargebackRequest::CANCELLING, ChargebackRequest::REFUNDED])->exists()) {
                throw new DomainError('early_termination_not_applicable', 'Služba se už vypořádává odstoupením nebo vrácením kreditu.', 409);
            }
            $estimate = $this->estimate($service);
            $byDocument = [];
            foreach ($estimate['lines'] as $row) {
                $byDocument[$row['invoice_id']][$row['line_id']] = $row['refund_minor'];
            }
            $notes = [];
            $toCredit = 0;
            foreach ($byDocument as $invoiceId => $amounts) {
                $document = Invoice::query()->find($invoiceId);
                if ($document === null) {
                    continue;
                }
                $given = $this->invoices->giveBack($document, $amounts, 'Předčasné ukončení ročního předplatného — vráceny nevyužité celé měsíce', $context->withScope($service->organization_id));
                $notes[] = (string) $given['credit_note']->id;
                $toCredit += (int) $given['to_credit_minor'];
            }
            Subscription::query()->where('service_id', $service->id)->update(['auto_renew' => false]);
            $service->forceFill(['tags' => array_merge((array) $service->tags, [self::TAG => ['at' => now()->toIso8601String(), 'refund_minor' => $estimate['refund']->minor, 'credit_notes' => $notes]])])->save();
            $this->audit->record($context->withScope($service->organization_id), 'billing.early_termination', 'succeeded', ['refund_minor' => $estimate['refund']->minor, 'credit_notes' => $notes], 'service', $service->id);
            $this->outbox->publish(GenericEvent::of('billing.early_termination.refunded', 'service', $service->id, ['service_id' => $service->id, 'label' => $service->label ?: $service->name,
                'refund' => $estimate['refund'], 'to_credit' => Money::minor($toCredit, $estimate['currency']), 'credit_notes' => $notes], $service->organization_id));

            return ['service_id' => $service->id, 'refund' => $estimate['refund'], 'credit_notes' => $notes, 'to_credit' => Money::minor($toCredit, $estimate['currency'])];
        });
    }
}
