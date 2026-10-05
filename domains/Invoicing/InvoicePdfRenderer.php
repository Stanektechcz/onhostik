<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Platform\Money\Money;

/**
 * Renders the immutable invoice snapshot to HTML and PDF (dompdf, DejaVu Sans for diacritics). Everything the document says
 * comes from what it froze when it was issued — the seller (VAT mode included), the buyer, the lines, the recap — so switching
 * the VAT mode later never changes a document (G2). Labels are Czech and English side by side.
 */
final class InvoicePdfRenderer
{
    public function __construct(private readonly ViewFactory $views) {}

    public function render(Invoice $invoice): string
    {
        return Pdf::loadHTML($this->html($invoice))->setPaper('a4')->output();
    }

    /** The document as HTML: what the PDF is made of, and what a browser can show. */
    public function html(Invoice $invoice): string
    {
        $lines = $invoice->lines()->get();
        $sellerVatPayer = (bool) ($invoice->seller['vat_payer'] ?? true); // as the document froze the seller
        $advances = array_values(array_filter((array) ($invoice->meta['advances'] ?? []), 'is_array'));
        $deducted = self::deductedTotal($invoice);

        return $this->views->make('invoices.invoice', [
            'invoice' => $invoice,
            'lines' => $lines,
            'money' => fn (int $minor) => Money::minor($minor, $invoice->currency)->format($invoice->buyer['locale'] ?? 'cs'),
            'title' => self::titleFor((string) $invoice->type, $sellerVatPayer),
            'taxDocument' => CzkTaxStatement::isTaxDocument($invoice),
            'sellerVatPayer' => $sellerVatPayer,
            'advances' => $advances,
            'advanceVat' => self::deductedVat($invoice),
            'deducted' => $deducted,
        ])->render();
    }

    /**
     * What the advances deducted on a final invoice stated, per rate (their tax documents' recaps added up).
     *
     * @param  list<array<string,mixed>>  $advances
     * @return array<string, array{rate:string, category:string, net:int, tax:int}>
     */
    public static function advanceVat(array $advances): array
    {
        $out = [];
        foreach ($advances as $advance) {
            foreach ((array) ($advance['summary'] ?? []) as $row) {
                $key = ($row['rate'] ?? '0').'|'.($row['category'] ?? 'S');
                $out[$key] ??= ['rate' => (string) ($row['rate'] ?? '0'), 'category' => (string) ($row['category'] ?? 'S'), 'net' => 0, 'tax' => 0];
                $out[$key]['net'] += (int) ($row['net'] ?? 0);
                $out[$key]['tax'] += (int) ($row['tax'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * What a final invoice deducts, per rate — never more than the document states at that rate (H2, security review of #106):
     * advances that claim more (a forged or an old row) are cut to the document's recap, so no difference goes below zero.
     *
     * @return array<string, array{rate:string, category:string, net:int, tax:int}>
     */
    public static function deductedVat(Invoice $invoice): array
    {
        $own = [];
        foreach ((array) $invoice->tax_summary as $row) {
            $own[($row['rate'] ?? '0').'|'.($row['category'] ?? 'S')] = ['net' => (int) ($row['net'] ?? 0), 'tax' => (int) ($row['tax'] ?? 0)];
        }
        $out = [];
        foreach (self::advanceVat(array_values(array_filter((array) ($invoice->meta['advances'] ?? []), 'is_array'))) as $key => $row) {
            $out[$key] = ['rate' => $row['rate'], 'category' => $row['category'], 'net' => max(0, min($row['net'], $own[$key]['net'] ?? 0)), 'tax' => max(0, min($row['tax'], $own[$key]['tax'] ?? 0))];
        }

        return $out;
    }

    /** What a final invoice deducts in total: the advances, never more than the document's total. */
    public static function deductedTotal(Invoice $invoice): int
    {
        $advances = array_sum(array_map(fn ($a) => is_array($a) ? (int) ($a['total_minor'] ?? 0) : 0, (array) ($invoice->meta['advances'] ?? [])));

        return max(0, min($advances, (int) $invoice->total_minor));
    }

    /**
     * What the document says it is. Only the types in CzkTaxStatement::TYPES are tax documents (G1): a statement listed what
     * the credit paid for under the heading of a tax invoice, so a payment with its receipt looked like two tax documents. A
     * seller who is no VAT payer issues no tax document at all (§ 29): its invoice is just an invoice.
     */
    public static function titleFor(string $type, bool $sellerVatPayer = true): string
    {
        if (! $sellerVatPayer) {
            return match ($type) {
                'proforma' => 'Zálohová faktura / Proforma invoice',
                'credit_note' => 'Opravná faktura / Credit note',
                'statement' => 'Vyúčtování z kreditu / Credit statement',
                'receipt', InvoiceService::PAYMENT_CONFIRMATION => 'Potvrzení o přijetí platby / Payment confirmation',
                default => 'Faktura / Invoice',
            };
        }

        return match ($type) {
            'proforma' => 'Zálohová faktura (není daňový doklad) / Proforma invoice',
            'credit_note' => 'Opravný daňový doklad / Credit note',
            'receipt' => 'Daňový doklad k přijaté platbě / Tax receipt',
            'statement' => 'Vyúčtování z kreditu (není daňový doklad) / Credit statement',
            InvoiceService::PAYMENT_CONFIRMATION => 'Potvrzení o přijetí platby (není daňový doklad) / Payment confirmation',
            default => 'Faktura – daňový doklad / Invoice',
        };
    }
}
