<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Billing\BillingPeriod;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\InvoiceLine;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\GreenService;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\WalletLedger\LedgerService;
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
            'period_from' => now()->toDateString(), 'period_to' => $this->periodEnd($item->period, (int) ($item->config['periods_billed'] ?? 1) * ($item->product_key === 'domain' ? (int) ($item->config['period_years'] ?? 1) : 1)),
            'service_id' => $item->service_id, 'order_item_id' => $item->id,
        ])->all();
        $draft = $this->draft($organization, $type, $order->currency, $lines, $context, $order->id, ['payment_method' => $paymentMethod, 'postpaid' => $postpaid, 'order_number' => $order->number]);
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
            ['country' => $organization->country, 'customer_class' => $organization->customer_class, 'vat_status' => $organization->vat_status],
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
            'buyer' => $this->buyerSnapshot($organization),
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
                'supply_date' => ($supplyDate ?? $issuedAt)->format('Y-m-d'),
                'due_at' => $issuedAt->copy()->addDays($dueDays),
                'payment_reference' => InvoiceNumberAllocator::variableSymbol($allocated['number']),
            ])->save();
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
            $state = $paid >= $invoice->total_minor ? Invoice::PAID : $invoice->state;
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

    /** Credit note (opravný daňový doklad) for the whole invoice or a subset of lines; original becomes CREDITED. */
    public function creditNote(Invoice $original, string $reason, CommandContext $context, ?array $lineIds = null, ?string $incidentRef = null): Invoice
    {
        if (! $original->isIssued() || $original->type === 'proforma') {
            throw new DomainError('invoice_not_creditable', 'Only issued tax documents can be credited.', 409);
        }

        return DB::transaction(function () use ($original, $reason, $context, $lineIds, $incidentRef) {
            $organization = Organization::query()->findOrFail($original->organization_id);
            $lines = $original->lines()->get()->filter(fn ($l) => $lineIds === null || in_array($l->id, $lineIds, true))->map(fn ($l) => [
                'sku' => $l->sku, 'description' => 'Dobropis: '.$l->description, 'qty' => $l->qty, 'unit' => $l->unit,
                'unit_net' => -$l->unit_net_minor, 'discount' => -$l->discount_minor, 'net' => -$l->net_minor, 'tax_rate' => $l->tax_rate, 'tax_category' => $l->tax_category,
                'tax' => -$l->tax_minor, 'total' => -$l->total_minor, 'period_from' => $l->period_from?->toDateString(), 'period_to' => $l->period_to?->toDateString(),
                'service_id' => $l->service_id, 'order_item_id' => $l->order_item_id,
            ])->values()->all();
            $draft = $this->draft($organization, 'credit_note', $original->currency, $lines, $context, $original->order_id, ['reason' => $reason, 'incident' => $incidentRef, 'original_number' => $original->number, 'postpaid' => $original->meta['postpaid'] ?? false], $original->id);
            $credit = $this->issue($draft, $context, dueDays: 0);
            if ($lineIds === null) {
                $original->forceFill(['state' => Invoice::CREDITED])->save();
            }
            if (($original->meta['postpaid'] ?? false) && $original->type === 'invoice') {
                $amount = abs($credit->total_minor);
                $this->ledger->post('credit_note', $original->currency, [
                    ['account' => LedgerService::revenueAccount('credit_note', $original->currency), 'debit' => $amount],
                    ['account' => LedgerService::receivableAccount($original->organization_id, $original->currency), 'credit' => $amount],
                ], "credit-note:{$credit->id}", $original->organization_id, 'invoice', $credit->id, "Credit note {$credit->number} for {$original->number}");
            }
            $this->audit->record($context->withScope($original->organization_id), 'invoice.credit_note', 'succeeded', ['original' => $original->number, 'credit_note' => $credit->number, 'reason' => $reason], 'invoice', $credit->id);

            return $credit;
        }, 3);
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
            'name' => $organization->name, 'ico' => $organization->ico, 'dic' => $organization->dic, 'vat_id' => $organization->vat_id, 'vat_status' => $organization->vat_status,
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

    private function periodEnd(string $period, int $units): string
    {
        return match ($period) {
            'year' => BillingPeriod::end(now(), 'year', $units)->subDay()->toDateString(),
            'day' => now()->addDays(max(1, $units))->subDay()->toDateString(),
            'hour' => now()->addHours(max(1, $units))->toDateString(),
            default => BillingPeriod::end(now(), 'month', $units)->subDay()->toDateString(),
        };
    }
}
