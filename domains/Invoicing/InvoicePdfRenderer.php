<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Platform\Money\Money;

/** Renders the immutable invoice snapshot to PDF (dompdf, DejaVu Sans for diacritics). */
final class InvoicePdfRenderer
{
    public function __construct(private readonly ViewFactory $views) {}

    public function render(Invoice $invoice): string
    {
        $lines = $invoice->lines()->get();
        $sellerVatPayer = (bool) ($invoice->seller['vat_payer'] ?? true); // as the document froze the seller
        $html = $this->views->make('invoices.invoice', [
            'invoice' => $invoice,
            'lines' => $lines,
            'money' => fn (int $minor) => Money::minor($minor, $invoice->currency)->format($invoice->buyer['locale'] ?? 'cs'),
            'title' => self::titleFor((string) $invoice->type, $sellerVatPayer),
            'taxDocument' => $sellerVatPayer && in_array($invoice->type, CzkTaxStatement::TYPES, true),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
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
