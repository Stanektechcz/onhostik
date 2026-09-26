<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\BillingPeriod;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\Models\OrderItem;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Invoice lifecycle (§64): draft -> issue (number, immutable snapshot, PDF + hash,
 * EN16931 structure) -> paid/overdue -> credit note / correction. Postpaid
 * invoices post receivables to the ledger; prepaid (wallet) invoices are tax
 * documents for money already recognised at capture.
 */
final class InvoiceService
{
    public function __construct(
        private readonly InvoiceNumberAllocator $numbers,
        private readonly InvoicePdfRenderer $pdf,
        private readonly UblExporter $ubl,
        private readonly LedgerService $ledger,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly FilesystemFactory $storage,
        private readonly TaxEngine $tax,
        private readonly WalletService $wallets,
        private readonly CzkTaxStatement $czk,
    ) {}

    /**
     * Document for a paid order. Prepaid/wallet orders get a credit statement
     * (vyúčtování) because VAT was settled on the top-up receipt; postpaid orders
     * get a tax invoice with a receivable posting.
     */
    public function issueForOrder(Order $order, CommandContext $context, string $paymentMethod): Invoice
    {
        $postpaid = $order->payment_mode === 'postpaid';
        $type = $postpaid ? 'invoice' : 'statement';
        $existing = Invoice::query()->where('order_id', $order->id)->where('type', $type)->first();
        if ($existing !== null) {
            return $existing;
        }
        $organization = Organization::query()->findOrFail($order->organization_id);
        $lines = $order->items()->get()->map(fn ($item, $i) => [
            'sku' => $item->sku, 'description' => $item->name, 'qty' => $item->qty, 'unit' => 'ks',
            'unit_net' => $item->unit_net_minor, 'discount' => $item->discount_minor, 'net' => $item->unit_net_minor * $item->qty - $item->discount_minor,
            'tax_rate' => $item->tax_rate, 'tax_category' => $item->config['tax_category'] ?? 'S', 'tax' => $item->tax_minor, 'total' => $item->total_minor,
            'period_from' => AccountingClock::date(), 'period_to' => $this->upgradePeriodEnd($item) ?? $this->periodEnd($item->period, (int) ($item->config['periods_billed'] ?? 1) * ($item->product_key === 'domain' ? (int) ($item->config['period_years'] ?? 1) : 1)),
            'service_id' => $item->service_id, 'order_item_id' => $item->id,
        ])->all();
        // the document states the VIES check its lines were decided on at the quote (TASK-0031, D31.4), not a later one
        $draft = $this->draft($organization, $type, $order->currency, $lines, $context, $order->id, array_filter(['payment_method' => $paymentMethod, 'postpaid' => $postpaid, 'order_number' => $order->number, 'vat' => $order->meta['vat'] ?? null], fn ($v) => $v !== null));
        $invoice = $this->issue($draft, $context, dueDays: $postpaid ? (int) config('onhost.billing.invoice_due_days', 14) : 0);
        if (! $postpaid) {
            $this->markPaid($invoice, $invoice->total(), $paymentMethod, $context, postLedger: false);
        }

        return $invoice;
    }

    /**
     * Tax document for a received payment (daňový doklad k přijaté platbě) issued
     * for every verified top-up. VAT is extracted from the gross amount using the
     * tax engine decision for electronically supplied services.
     */
    public function issueReceipt(Organization $organization, Money $gross, string $method, CommandContext $context, ?string $paymentIntentId = null): Invoice
    {
        if ($paymentIntentId !== null) {
            $existing = Invoice::query()->where('type', 'receipt')->where('meta->payment_intent_id', $paymentIntentId)->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        $decision = $this->tax->calculate(
            VatStanding::taxCustomer($organization),
            [['key' => 'topup', 'net' => Money::zero($gross->currency), 'product_class' => 'esd']],
            $gross->currency,
            $organization->id,
        );
        $rate = (string) $decision['lines'][0]['rate'];
        $category = (string) $decision['lines'][0]['category'];
        $net = $rate === '0' ? $gross : Money::minor((int) bcdiv(bcmul((string) $gross->minor, '100', 6), bcadd('100', $rate, 6), 0), $gross->currency);
        $tax = $gross->subtract($net);
        $draft = $this->draft($organization, 'receipt', $gross->currency->value, [[
            'sku' => 'topup', 'description' => 'Přijatá platba — kredit ONhost (záloha na služby)', 'qty' => 1, 'unit' => 'ks',
            'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => $rate, 'tax_category' => $category, 'tax' => $tax->minor, 'total' => $gross->minor,
        ]], $context, null, ['payment_method' => $method, 'payment_intent_id' => $paymentIntentId, 'tax_calculation_id' => $decision['calculation']->id]);
        $receipt = $this->issue($draft, $context, dueDays: 0);

        return $this->markPaid($receipt, $gross, $method, $context, postLedger: false);
    }

    public function issueProforma(Order $order, CommandContext $context): Invoice
    {
        $organization = Organization::query()->findOrFail($order->organization_id);
        $lines = $order->items()->get()->map(fn ($item) => [
            'sku' => $item->sku, 'description' => $item->name, 'qty' => $item->qty, 'unit' => 'ks',
            'unit_net' => $item->unit_net_minor, 'discount' => $item->discount_minor, 'net' => $item->unit_net_minor * $item->qty - $item->discount_minor,
            'tax_rate' => $item->tax_rate, 'tax_category' => $item->config['tax_category'] ?? 'S', 'tax' => $item->tax_minor, 'total' => $item->total_minor,
            'period_from' => null, 'period_to' => null, 'service_id' => null, 'order_item_id' => $item->id,
        ])->all();
        $draft = $this->draft($organization, 'proforma', $order->currency, $lines, $context, $order->id, ['payment_method' => 'bank']);

        return $this->issue($draft, $context, dueDays: (int) config('onhost.billing.invoice_due_days', 14));
    }

    /**
     * @param  list<array<string,mixed>>  $lines  minor units
     * @param  array<string,mixed>  $meta
     */
    public function draft(Organization $organization, string $type, string $currency, array $lines, CommandContext $context, ?string $orderId = null, array $meta = [], ?string $correctsInvoiceId = null): Invoice
    {
        $entity = $this->legalEntity();
        $buyer = $this->buyerSnapshot($organization);
        if (is_array($meta['vat'] ?? null)) {
            $buyer['vat_check'] = $meta['vat']; // the check the order was quoted on
        }
        // finance looks at a document for a business of another EU state that gave a VAT ID and was not reverse-charged
        // (or only by a row from before the check) — the predicate onhost:vat:verify uses for past documents (TASK-0031)
        $meta['vat_review'] = VatStanding::invoiceNeedsReview($buyer, $lines);
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        $total = 0;
        foreach ($lines as $line) {
            $subtotal += (int) $line['net'] + (int) ($line['discount'] ?? 0);
            $discount += (int) ($line['discount'] ?? 0);
            $tax += (int) $line['tax'];
            $total += (int) $line['total'];
        }
        $invoice = Invoice::query()->create([
            'legal_entity' => $entity->key,
            'series' => $entity->seriesFor($type),
            'type' => $type,
            'organization_id' => $organization->id,
            'order_id' => $orderId,
            'corrects_invoice_id' => $correctsInvoiceId,
            'currency' => $currency,
            'state' => Invoice::DRAFT,
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'buyer' => $buyer,
            'seller' => $this->sellerSnapshot($entity),
            'tax_summary' => $this->taxSummary($lines),
            'payment_method' => $meta['payment_method'] ?? null,
            'meta' => $meta,
            'created_by' => $context->actorType.':'.($context->actorId ?? 'system'),
        ]);
        foreach ($lines as $i => $line) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id, 'position' => $i + 1, 'sku' => $line['sku'] ?? null, 'description' => mb_substr((string) $line['description'], 0, 250),
                'qty' => (string) ($line['qty'] ?? 1), 'unit' => $line['unit'] ?? 'ks', 'unit_net_minor' => (int) $line['unit_net'], 'discount_minor' => (int) ($line['discount'] ?? 0),
                'net_minor' => (int) $line['net'], 'tax_rate' => (string) $line['tax_rate'], 'tax_category' => $line['tax_category'] ?? 'S', 'tax_minor' => (int) $line['tax'], 'total_minor' => (int) $line['total'],
                'period_from' => $line['period_from'] ?? null, 'period_to' => $line['period_to'] ?? null, 'service_id' => $line['service_id'] ?? null, 'order_item_id' => $line['order_item_id'] ?? null,
                'corrects_line_id' => $line['corrects_line_id'] ?? null,
            ]);
        }

        return $invoice;
    }

    /** Assign number, freeze, render PDF, store hash and structured form. Postpaid invoices post receivables. */
    public function issue(Invoice $invoice, CommandContext $context, int $dueDays = 14, ?\DateTimeInterface $supplyDate = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $context, $dueDays, $supplyDate) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->state !== Invoice::DRAFT) {
                return $invoice;
            }
            $allocated = $this->numbers->allocate($invoice->legal_entity, $invoice->series);
            $issuedAt = now();
            $meta = (array) $invoice->meta;
            if (in_array($invoice->type, ['invoice', 'receipt'], true) && ! array_key_exists('green', $meta)) { // the month's footprint of the buyer's services (audit §5j-10), stated as an estimate
                $buyer = Organization::query()->find($invoice->organization_id);
                $meta['green'] = $buyer !== null ? app(GreenService::class)->invoiceSnapshot($buyer) : null;
            }
            $invoice->forceFill([
                'meta' => $meta,
                'number' => $allocated['number'],
                'state' => Invoice::ISSUED,
                'issued_at' => $issuedAt,
                'supply_date' => AccountingClock::date($supplyDate ?? $issuedAt), // a DATE on a document is the accounting day, not the day in UTC
                'due_at' => $issuedAt->copy()->addDays($dueDays),
                'payment_reference' => InvoiceNumberAllocator::variableSymbol($allocated['number']),
            ])->save();
            // a tax document in another currency states its VAT in CZK at the national bank's rate of the supply day (it carried
            // neither); a bank that does not answer does not stop the document — `onhost:fx:sync` completes it
            $invoice->forceFill(['meta' => $this->czk->stamp($invoice)])->save();
            $invoice->forceFill(['structured' => $this->ubl->structure($invoice)])->save();
            $this->renderPdf($invoice);
            if (($invoice->meta['postpaid'] ?? false) && $invoice->type === 'invoice') {
                $this->postReceivable($invoice);
            }
            $this->audit->record($context->withScope($invoice->organization_id), 'invoice.issue', 'succeeded', ['number' => $invoice->number, 'type' => $invoice->type, 'total' => $invoice->total()], 'invoice', $invoice->id);
            $this->outbox->publish(GenericEvent::of('invoice.issued', 'invoice', $invoice->id, ['number' => $invoice->number, 'type' => $invoice->type, 'total' => $invoice->total(), 'due_at' => $invoice->due_at?->toISOString()], $invoice->organization_id));

            return $invoice;
        }, 3);
    }

    public function markPaid(Invoice $invoice, Money $amount, string $method, CommandContext $context, bool $postLedger = true, ?string $paymentReference = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $postLedger, $paymentReference) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->state === Invoice::PAID || $invoice->state === Invoice::CANCELLED || $invoice->state === Invoice::CREDITED) {
                return $invoice;
            }
            $paid = $invoice->paid_minor + $amount->minor;
            $state = $paid >= $invoice->total_minor - (int) $invoice->credited_minor ? Invoice::PAID : $invoice->state; // what credit notes took off is not owed
            $invoice->forceFill(['paid_minor' => $paid, 'state' => $state, 'paid_at' => $state === Invoice::PAID ? now() : null, 'payment_method' => $method])->save();
            if ($postLedger && $invoice->type === 'invoice' && ($invoice->meta['postpaid'] ?? false)) {
                $this->ledger->post('invoice_settlement', $invoice->currency, [
                    ['account' => LedgerService::bankAccount($method, $invoice->currency), 'debit' => $amount->minor],
                    ['account' => LedgerService::receivableAccount($invoice->organization_id, $invoice->currency), 'credit' => $amount->minor],
                ], "invoice-paid:{$invoice->id}:".($paymentReference ?? $amount->minor), $invoice->organization_id, 'invoice', $invoice->id, "Payment of {$invoice->number}");
            }
            $this->outbox->publish(GenericEvent::of('invoice.paid', 'invoice', $invoice->id, ['number' => $invoice->number, 'type' => $invoice->type, 'amount' => $amount, 'method' => $method], $invoice->organization_id));

            return $invoice;
        }, 3);
    }

    /**
     * Voids an unpaid proforma whose order was cancelled. A proforma is not a tax document, so it can be voided outright;
     * tax invoices are corrected with a credit note (`creditNote`) and are never touched here.
     */
    public function cancelProforma(Invoice $invoice, CommandContext $context, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $context, $reason) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->type !== 'proforma') {
                throw new DomainError('invoice_not_proforma', 'Only proformas can be voided; tax documents are corrected with a credit note.', 422);
            }
            if ($invoice->state === Invoice::CANCELLED) {
                return $invoice;
            }
            if ((int) $invoice->paid_minor > 0 || ! in_array($invoice->state, [Invoice::DRAFT, Invoice::ISSUED, Invoice::OVERDUE], true)) {
                throw new DomainError('invoice_not_cancellable', 'A paid or settled proforma cannot be voided.', 409);
            }
            $invoice->forceFill(['state' => Invoice::CANCELLED, 'cancelled_at' => now(), 'note' => trim(($invoice->note ? $invoice->note.' · ' : '').$reason)])->save();
            $this->audit->record($context->withScope($invoice->organization_id), 'invoice.cancel', 'succeeded', ['number' => $invoice->number, 'type' => $invoice->type, 'reason' => $reason], 'invoice', $invoice->id);
            $this->outbox->publish(GenericEvent::of('invoice.cancelled', 'invoice', $invoice->id, ['number' => $invoice->number, 'type' => $invoice->type, 'reason' => $reason], $invoice->organization_id));

            return $invoice;
        }, 3);
    }

    /** Daily sweep: ISSUED past due => OVERDUE (dunning listens to the event). */
    public function overdueSweep(): int
    {
        $count = 0;
        foreach (Invoice::query()->where('state', Invoice::ISSUED)->whereIn('type', ['invoice', 'proforma'])->where('due_at', '<', now())->get() as $invoice) {
            $invoice->forceFill(['state' => Invoice::OVERDUE])->save();
            $this->outbox->publish(GenericEvent::of('invoice.overdue', 'invoice', $invoice->id, ['number' => $invoice->number, 'due_at' => $invoice->due_at?->toISOString(), 'outstanding' => $invoice->outstanding()], $invoice->organization_id));
            $count++;
        }

        return $count;
    }

    /**
     * Credit note (opravný daňový doklad) — for the whole document, for some of its lines, or for a part of a line
     * (`$amounts`: line id → the gross to give back, optionally with the period it stands for).
     *
     * What a line has left is what it was issued for minus every credit note already written against it
     * (`invoice_lines.corrects_line_id`). Before, nothing counted: the same document or the same lines could be credited
     * again and again — each time taking the customer's debt down once more — a credit note could itself be credited, and
     * a document with a partial credit note was still asked to be paid in full.
     *
     * A document booked when it was issued (postpaid) gives its revenue AND its VAT back; what the customer had already
     * paid beyond what is still owed returns to their credit.
     *
     * @param  list<string>|null  $lineIds  null: every line that has something left (or the lines named in `$amounts`)
     * @param  array<string, int|array{gross:int, period_from?:?string, period_to?:?string}>|null  $amounts  gross minor units per line id
     */
    public function creditNote(Invoice $original, string $reason, CommandContext $context, ?array $lineIds = null, ?string $incidentRef = null, ?array $amounts = null): Invoice
    {
        if (! $original->isCreditable()) {
            throw new DomainError('invoice_not_creditable', 'Only an issued tax document can be credited; a proforma is voided and a credit note is never credited itself.', 409, ['type' => $original->type, 'state' => $original->state]);
        }

        return DB::transaction(function () use ($original, $reason, $context, $lineIds, $incidentRef, $amounts) {
            $original = Invoice::query()->lockForUpdate()->findOrFail($original->id); // two corrections of one document wait for each other
            $organization = Organization::query()->findOrFail($original->organization_id);
            $named = $lineIds ?? ($amounts !== null ? array_map('strval', array_keys($amounts)) : null);
            $before = $this->creditedByLine($original);
            $lines = [];
            foreach ($original->lines()->get() as $l) {
                if ($named !== null && ! in_array($l->id, $named, true)) {
                    continue;
                }
                $done = $before[$l->id] ?? ['net' => 0, 'tax' => 0, 'total' => 0];
                $left = (int) $l->total_minor - $done['total'];
                $ask = $amounts[$l->id] ?? null;
                $want = $ask === null ? $left : (int) (is_array($ask) ? $ask['gross'] : $ask);
                if ($left === 0 || ($left > 0) !== ((int) $l->total_minor > 0)) {
                    if ($named === null) {
                        continue; // the whole document: a line already credited is simply not credited again
                    }
                    throw new DomainError('invoice_line_already_credited', 'This line was already credited in full.', 409, ['line_id' => $l->id]);
                }
                if ($ask !== null && ((int) $l->total_minor <= 0 || $want <= 0 || $want > $left)) {
                    throw new DomainError('invoice_credit_exceeds_line', 'A line cannot be credited for more than it has left.', 409, ['line_id' => $l->id, 'left' => Money::minor(max(0, $left), $original->currency), 'asked' => Money::minor(max(0, $want), $original->currency)]);
                }
                if ($want === (int) $l->total_minor) { // the whole line, untouched so far: its exact mirror
                    $net = (int) $l->net_minor;
                    $tax = (int) $l->tax_minor;
                    $row = ['qty' => $l->qty, 'unit_net' => -$l->unit_net_minor, 'discount' => -$l->discount_minor];
                } else { // a part, or the rest after a part: the VAT in the line's own proportion, the last part takes the remainder to the haler
                    $tax = $want === $left ? (int) $l->tax_minor - $done['tax'] : (int) round($want * (int) $l->tax_minor / (int) $l->total_minor);
                    $net = $want - $tax;
                    $row = ['qty' => 1, 'unit_net' => -$net, 'discount' => 0];
                }
                $lines[] = $row + [
                    'sku' => $l->sku, 'description' => 'Dobropis: '.$l->description, 'unit' => $l->unit, 'net' => -$net, 'tax_rate' => $l->tax_rate, 'tax_category' => $l->tax_category, 'tax' => -$tax, 'total' => -$want,
                    'period_from' => (is_array($ask) ? ($ask['period_from'] ?? null) : null) ?? $l->period_from?->toDateString(), 'period_to' => (is_array($ask) ? ($ask['period_to'] ?? null) : null) ?? $l->period_to?->toDateString(),
                    'service_id' => $l->service_id, 'order_item_id' => $l->order_item_id, 'corrects_line_id' => $l->id,
                ];
            }
            if ($lines === []) {
                throw new DomainError('invoice_nothing_to_credit', 'Nothing is left to credit on this document.', 409, ['number' => $original->number]);
            }
            $draft = $this->draft($organization, 'credit_note', $original->currency, $lines, $context, $original->order_id, ['reason' => $reason, 'incident' => $incidentRef, 'original_number' => $original->number, 'postpaid' => $original->meta['postpaid'] ?? false], $original->id);
            $credit = $this->issue($draft, $context, dueDays: 0);
            $credited = (int) $original->credited_minor - (int) $credit->total_minor;
            $patch = ['credited_minor' => $credited];
            if ($credited >= (int) $original->total_minor) {
                $patch['state'] = Invoice::CREDITED;
            } elseif (in_array($original->state, [Invoice::ISSUED, Invoice::OVERDUE], true) && (int) $original->paid_minor >= (int) $original->total_minor - $credited) {
                $patch += ['state' => Invoice::PAID, 'paid_at' => now()]; // what was paid already covers what is still owed
            }
            $original->forceFill($patch)->save();
            if ($original->bookedAtIssue()) {
                $this->unbook($original, $credit, $context);
            }
            $this->audit->record($context->withScope($original->organization_id), 'invoice.credit_note', 'succeeded', ['original' => $original->number, 'credit_note' => $credit->number, 'reason' => $reason, 'amount' => Money::minor(-(int) $credit->total_minor, $original->currency), 'whole' => ($patch['state'] ?? null) === Invoice::CREDITED], 'invoice', $credit->id);

            return $credit;
        }, 3);
    }

    /**
     * Gives a part of what a document was paid for back to the customer: the credit note for exactly those amounts, and
     * the money. A document booked at issue returns what was overpaid through its receivable (`unbook`); a document paid from
     * the credit at once (a statement, a wallet-paid invoice) returns it against the revenue and the VAT it had earned. Only
     * what was really paid comes back — a credit note on an unpaid document makes the debt smaller, it does not pay anybody.
     *
     * @param  array<string, int|array{gross:int, period_from?:?string, period_to?:?string}>|null  $amounts  null: everything the document has left
     * @return array{credit_note:Invoice, to_credit_minor:int, off_document_minor:int}
     */
    public function giveBack(Invoice $document, ?array $amounts, string $reason, CommandContext $context): array
    {
        return DB::transaction(function () use ($document, $amounts, $reason, $context) {
            $document = Invoice::query()->lockForUpdate()->findOrFail($document->id);
            $returnedBefore = (int) ($document->meta['overpaid_returned_minor'] ?? 0);
            $credit = $this->creditNote($document, $reason, $context, null, null, $amounts);
            $document = $document->refresh();
            $gross = abs((int) $credit->total_minor);
            if ($document->bookedAtIssue()) {
                $toCredit = (int) ($document->meta['overpaid_returned_minor'] ?? 0) - $returnedBefore;

                return ['credit_note' => $credit, 'to_credit_minor' => $toCredit, 'off_document_minor' => $gross - $toCredit];
            }
            $over = min($gross, (int) $document->paid_minor - max(0, (int) $document->total_minor - (int) $document->credited_minor) - $returnedBefore);
            if ($over > 0) {
                $tax = (int) round(abs((int) $credit->tax_minor) * $over / $gross);
                $this->wallets->returnToCredit($document->organization_id, Money::minor($over, $document->currency), WalletService::revenueReturn(Money::minor($over, $document->currency), Money::minor($tax, $document->currency)),
                    "give-back:{$credit->id}", $context->withScope($document->organization_id), 'invoice', $credit->id, "Vráceno na kredit: dobropis {$credit->number} k dokladu {$document->number}");
                $document->forceFill(['meta' => array_merge((array) $document->meta, ['overpaid_returned_minor' => $returnedBefore + $over])])->save();
            }

            return ['credit_note' => $credit, 'to_credit_minor' => max(0, $over), 'off_document_minor' => $gross - max(0, $over)];
        }, 3);
    }

    /**
     * The credit note of what a document — or the named lines of it — has left; null when nothing is (an order cancelled
     * after the settlement had already given its undelivered lines back).
     *
     * @param  list<string>|null  $lineIds
     */
    public function creditRemaining(Invoice $original, string $reason, CommandContext $context, ?array $lineIds = null): ?Invoice
    {
        $original = $original->refresh();
        if (! $original->isCreditable()) {
            return null;
        }
        $done = $this->creditedByLine($original);
        $open = $original->lines()->get()->filter(fn (InvoiceLine $l) => ($lineIds === null || in_array($l->id, $lineIds, true)) && (int) $l->total_minor !== 0 && (int) $l->total_minor - ($done[$l->id]['total'] ?? 0) !== 0)->pluck('id')->all();
        if ($open === []) {
            return null;
        }

        return $this->creditNote($original, $reason, $context, $open);
    }

    /**
     * What credit notes already took off each line of a document, as positive sums.
     *
     * @return array<string, array{net:int, tax:int, total:int}>
     */
    public function creditedByLine(Invoice $original): array
    {
        $out = [];
        $rows = InvoiceLine::query()->whereNotNull('corrects_line_id')
            ->whereIn('invoice_id', Invoice::query()->where('corrects_invoice_id', $original->id)->where('type', 'credit_note')->where('state', '!=', Invoice::DRAFT)->select('id'))->get();
        foreach ($rows as $row) {
            $key = (string) $row->getAttribute('corrects_line_id');
            $out[$key] ??= ['net' => 0, 'tax' => 0, 'total' => 0];
            $out[$key]['net'] -= (int) $row->net_minor;
            $out[$key]['tax'] -= (int) $row->tax_minor;
            $out[$key]['total'] -= (int) $row->total_minor;
        }

        return $out;
    }

    /**
     * A document booked at issue (DR receivable / CR revenue / CR VAT) is unbooked by its credit note the same way round:
     * the net leaves the revenue and the VAT leaves the VAT account. The whole gross used to be debited to revenue, so every
     * credit note left the VAT liability too high by its tax. What the customer paid beyond what is still owed returns to
     * their credit (DR receivable / CR wallet) — once.
     */
    private function unbook(Invoice $original, Invoice $credit, CommandContext $context): void
    {
        $gross = abs((int) $credit->total_minor);
        $tax = abs((int) $credit->tax_minor);
        $postings = [];
        if ($gross - $tax > 0) {
            $postings[] = ['account' => LedgerService::revenueAccount('credit_note', $original->currency), 'debit' => $gross - $tax];
        }
        if ($tax > 0) {
            $postings[] = ['account' => LedgerService::vatAccount($original->currency), 'debit' => $tax];
        }
        $postings[] = ['account' => LedgerService::receivableAccount($original->organization_id, $original->currency), 'credit' => $gross];
        $this->ledger->post('credit_note', $original->currency, $postings, "credit-note:{$credit->id}", $original->organization_id, 'invoice', $credit->id, "Credit note {$credit->number} for {$original->number}");

        $returned = (int) ($original->meta['overpaid_returned_minor'] ?? 0);
        $over = (int) $original->paid_minor - max(0, (int) $original->total_minor - (int) $original->credited_minor) - $returned;
        if ($over > 0) {
            $this->wallets->returnToCredit($original->organization_id, Money::minor($over, $original->currency), [['account' => LedgerService::receivableAccount($original->organization_id, $original->currency), 'debit' => $over]],
                "credit-note-return:{$credit->id}", $context->withScope($original->organization_id), 'invoice', $credit->id, "Vráceno na kredit: dobropis {$credit->number} k faktuře {$original->number}");
            $original->forceFill(['meta' => array_merge((array) $original->meta, ['overpaid_returned_minor' => $returned + $over])])->save();
        }
    }

    /**
     * Documents in another currency that were issued while the national bank's rate was not known (`meta.czk_pending`): the CZK
     * recap is added, the structure and the PDF are made again. Nothing the document was issued for changes.
     *
     * @return array{completed:int, waiting:int}
     */
    public function completeCzkStatements(int $limit = 200): array
    {
        $stats = ['completed' => 0, 'waiting' => 0];
        foreach (Invoice::query()->where('meta->czk_pending', true)->where('state', '!=', Invoice::DRAFT)->orderBy('issued_at')->limit(max(1, $limit))->get() as $invoice) {
            $meta = $this->czk->stamp($invoice);
            if (! isset($meta['czk'])) {
                $stats['waiting']++;

                continue;
            }
            $invoice->forceFill(['meta' => $meta])->save();
            $invoice->forceFill(['structured' => $this->ubl->structure($invoice)])->save();
            $this->renderPdf($invoice);
            $this->audit->record(CommandContext::system('fx-sync')->withScope($invoice->organization_id), 'invoice.czk_statement.completed', 'succeeded', ['number' => $invoice->number, 'rate' => $meta['czk']['rate'], 'valid_on' => $meta['czk']['valid_on'], 'tax_czk_minor' => $meta['czk']['tax_minor']], 'invoice', $invoice->id);
            $stats['completed']++;
        }

        return $stats;
    }

    public function renderPdf(Invoice $invoice): Invoice
    {
        $binary = $this->pdf->render($invoice->refresh());
        $path = "invoices/{$invoice->organization_id}/{$invoice->id}.pdf";
        $this->storage->disk('local')->put($path, $binary);
        $invoice->forceFill(['pdf_path' => $path, 'pdf_hash' => hash('sha256', $binary)])->save();

        return $invoice;
    }

    public function pdfBinary(Invoice $invoice): string
    {
        if ($invoice->pdf_path !== null && $this->storage->disk('local')->exists($invoice->pdf_path)) {
            $binary = (string) $this->storage->disk('local')->get($invoice->pdf_path);
            if (hash('sha256', $binary) === $invoice->pdf_hash) {
                return $binary;
            }
        }
        // Re-render from the immutable structured snapshot; hash must match the stored one.
        $binary = $this->pdf->render($invoice);
        if ($invoice->pdf_hash !== null && hash('sha256', $binary) !== $invoice->pdf_hash) {
            throw new DomainError('invoice_pdf_integrity', 'Stored PDF hash does not match the re-rendered document; finance review required.', 500);
        }

        return $binary;
    }

    public function legalEntity(?string $key = null): LegalEntity
    {
        $entity = LegalEntity::query()->find($key ?? (string) config('onhost.billing.legal_entity', 'onhost-cz'));
        if ($entity === null) {
            throw new DomainError('legal_entity_missing', 'Legal entity is not configured; run LegalEntitySeeder.', 500);
        }

        return $entity;
    }

    private function postReceivable(Invoice $invoice): void
    {
        $revenue = $invoice->subtotal_minor - $invoice->discount_minor;
        $postings = [['account' => LedgerService::receivableAccount($invoice->organization_id, $invoice->currency), 'debit' => $invoice->total_minor]];
        if ($revenue > 0) {
            $postings[] = ['account' => LedgerService::revenueAccount('postpaid', $invoice->currency), 'credit' => $revenue];
        }
        if ($invoice->tax_minor > 0) {
            $postings[] = ['account' => LedgerService::vatAccount($invoice->currency), 'credit' => $invoice->tax_minor];
        }
        $this->ledger->post('invoice_issue', $invoice->currency, $postings, "invoice-issue:{$invoice->id}", $invoice->organization_id, 'invoice', $invoice->id, "Invoice {$invoice->number}");
    }

    private function buyerSnapshot(Organization $organization): array
    {
        return [
            // vat_id falls back to the DIČ (the number the document and the UBL buyer tax scheme print); vat_status is the standing
            // the tax decision used, and vat_check the evidence behind it (TASK-0031)
            'name' => $organization->name, 'ico' => $organization->ico, 'dic' => $organization->dic, 'vat_id' => $organization->vat_id ?: $organization->dic,
            'vat_status' => VatStanding::effectiveStatus($organization), 'vat_check' => VatStanding::snapshot($organization),
            'street' => $organization->street, 'city' => $organization->city, 'postal_code' => $organization->postal_code, 'country' => $organization->country,
            'email' => $organization->billing_email, 'customer_class' => $organization->customer_class, 'organization_id' => $organization->id,
        ];
    }

    private function sellerSnapshot(LegalEntity $entity): array
    {
        return ['name' => $entity->name, 'ico' => $entity->ico, 'dic' => $entity->dic, 'vat_id' => $entity->vat_id, 'address' => $entity->address, 'country' => $entity->country, 'iban' => $entity->iban, 'bic' => $entity->bic, 'bank_account' => $entity->bank_account, 'vat_payer' => $entity->vat_payer];
    }

    /** @param list<array<string,mixed>> $lines @return list<array{rate:string,category:string,net:int,tax:int}> */
    private function taxSummary(array $lines): array
    {
        $groups = [];
        foreach ($lines as $line) {
            $key = $line['tax_rate'].'|'.($line['tax_category'] ?? 'S');
            $groups[$key] ??= ['rate' => (string) $line['tax_rate'], 'category' => $line['tax_category'] ?? 'S', 'net' => 0, 'tax' => 0];
            $groups[$key]['net'] += (int) $line['net'];
            $groups[$key]['tax'] += (int) $line['tax'];
        }

        return array_values($groups);
    }

    /**
     * A plan change inside a running period pays the difference until that period ends — not for a whole new period from
     * today. The line says so; the return of an unused period is computed from it (ChargebackService).
     */
    private function upgradePeriodEnd(OrderItem $item): ?string
    {
        $serviceId = (string) ($item->config['upgrade_of'] ?? '');
        if ($serviceId === '' || (bool) data_get($item->config, 'plan_change.period_change', false)) {
            return null;
        }
        $end = Subscription::query()->where('service_id', $serviceId)->whereNotIn('state', [Subscription::CANCELLED])->orderByDesc('created_at')->value('current_period_end');

        return $end === null ? null : AccountingClock::date(Carbon::parse($end)->subDay());
    }

    private function periodEnd(string $period, int $units): string
    {
        return match ($period) {
            'year' => BillingPeriod::end(AccountingClock::now(), 'year', $units)->subDay()->toDateString(),
            'day' => AccountingClock::now()->addDays(max(1, $units))->subDay()->toDateString(),
            'hour' => AccountingClock::now()->addHours(max(1, $units))->toDateString(),
            default => BillingPeriod::end(AccountingClock::now(), 'month', $units)->subDay()->toDateString(),
        };
    }
}
