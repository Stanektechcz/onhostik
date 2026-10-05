<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Onhost\Domain\Invoicing\CzkTaxStatement;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Payments\Models\PaymentIntent;

/**
 * Read-only list for the accountant (G1, owner decision G-R1): every historical invoice whose own payment — by card or bank
 * transfer — also got a receipt with VAT, so the same sale stands on two tax documents (both in CzkTaxStatement::TYPES, both in
 * the VAT return). Until G1 every gateway or bank payment was credited with a receipt and then spent on the invoice.
 *
 * It writes nothing: no document is marked, cancelled or deleted (documents are append-only; a correction is the accountant's
 * decision — a credit note of the receipt, or an adjustment of the return). The ledger is not affected: it booked the VAT once.
 */
final class BillingDoubleTaxReport extends Command
{
    protected $signature = 'onhost:billing:double-tax-report {--organization= : one organization id} {--since= : receipts issued on or after YYYY-MM-DD} {--json : machine-readable output}';

    protected $description = 'Read-only: invoices whose card/bank payment also got a VAT receipt (two tax documents for one sale)';

    private const CHUNK = 500;

    public function handle(): int
    {
        $pairs = [];
        $receipts = Invoice::query()->where('type', 'receipt')->where('state', '!=', Invoice::DRAFT)->whereNotNull('meta->payment_intent_id');
        if (is_string($this->option('organization')) && $this->option('organization') !== '') {
            $receipts->where('organization_id', (string) $this->option('organization'));
        }
        if (is_string($this->option('since')) && $this->option('since') !== '') {
            $receipts->where('issued_at', '>=', Carbon::parse((string) $this->option('since'))->startOfDay());
        }
        $receipts->chunkById(self::CHUNK, function ($chunk) use (&$pairs) {
            $byIntent = $chunk->keyBy(fn (Invoice $receipt) => (string) ($receipt->meta['payment_intent_id'] ?? ''));
            $intents = PaymentIntent::query()->whereIn('id', $byIntent->keys()->filter()->all())->where('purpose', 'invoice')->where('reference_type', 'invoice')->get();
            $invoices = Invoice::query()->whereIn('id', $intents->pluck('reference_id')->filter()->all())->whereIn('type', CzkTaxStatement::TYPES)->get()->keyBy('id');
            foreach ($intents as $intent) {
                $receipt = $byIntent->get($intent->id);
                $invoice = $invoices->get((string) $intent->reference_id);
                if ($receipt === null || $invoice === null || $invoice->organization_id !== $receipt->organization_id) {
                    continue;
                }
                $pairs[] = [
                    'organization_id' => $invoice->organization_id,
                    'invoice_id' => $invoice->id, 'invoice_number' => $invoice->number, 'invoice_state' => $invoice->state,
                    'invoice_total_minor' => (int) $invoice->total_minor, 'invoice_tax_minor' => (int) $invoice->tax_minor,
                    'receipt_id' => $receipt->id, 'receipt_number' => $receipt->number, 'receipt_issued_at' => $receipt->issued_at?->toIso8601String(),
                    'receipt_total_minor' => (int) $receipt->total_minor, 'receipt_tax_minor' => (int) $receipt->tax_minor,
                    'currency' => (string) $receipt->currency, 'payment_intent_id' => $intent->id, 'provider' => (string) $intent->provider,
                ];
            }
        });
        usort($pairs, fn (array $a, array $b) => [$a['receipt_issued_at'], $a['receipt_number']] <=> [$b['receipt_issued_at'], $b['receipt_number']]);
        $totals = [];
        foreach ($pairs as $pair) {
            $totals[$pair['currency']] ??= ['pairs' => 0, 'receipt_total_minor' => 0, 'receipt_tax_minor' => 0];
            $totals[$pair['currency']]['pairs']++;
            $totals[$pair['currency']]['receipt_total_minor'] += $pair['receipt_total_minor'];
            $totals[$pair['currency']]['receipt_tax_minor'] += $pair['receipt_tax_minor'];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['pairs' => $pairs, 'totals' => $totals], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }
        $this->info('Invoices whose own payment also got a VAT receipt ('.count($pairs).'). Nothing is changed by this report.');
        $this->table(['Organization', 'Invoice', 'Invoice VAT', 'Receipt', 'Receipt issued', 'Receipt total', 'Receipt VAT', 'Currency', 'Payment'], array_map(fn (array $p) => [
            $p['organization_id'], $p['invoice_number'], $p['invoice_tax_minor'], $p['receipt_number'], $p['receipt_issued_at'], $p['receipt_total_minor'], $p['receipt_tax_minor'], $p['currency'], $p['payment_intent_id'],
        ], $pairs));
        foreach ($totals as $currency => $sum) {
            $this->line(sprintf('%s: %d pairs, receipts %d, VAT stated twice %d (minor units)', $currency, $sum['pairs'], $sum['receipt_total_minor'], $sum['receipt_tax_minor']));
        }

        return self::SUCCESS;
    }
}
