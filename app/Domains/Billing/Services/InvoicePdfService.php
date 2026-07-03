<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generates a PDF binary string for an invoice using the pdf/invoice.blade.php template.
 */
final class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->load('items');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'faktura-' . str_replace(['/', '\\', ' '], '-', $invoice->number) . '.pdf';
    }
}
