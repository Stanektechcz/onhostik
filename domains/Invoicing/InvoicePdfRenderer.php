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
        $html = $this->views->make('invoices.invoice', [
            'invoice' => $invoice,
            'lines' => $lines,
            'money' => fn (int $minor) => Money::minor($minor, $invoice->currency)->format($invoice->buyer['locale'] ?? 'cs'),
            'title' => match ($invoice->type) {
                'proforma' => 'Zálohová faktura / Proforma invoice',
                'credit_note' => 'Opravný daňový doklad / Credit note',
                'receipt' => 'Doklad o přijetí platby / Receipt',
                default => 'Faktura – daňový doklad / Invoice',
            },
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}
