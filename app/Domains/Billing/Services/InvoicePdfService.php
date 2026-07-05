<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generates a PDF binary string for an invoice using the pdf/invoice.blade.php template.
 * When the invoice's customer belongs to a reseller, reseller branding is injected
 * into the PDF via the $brand array (overrides config billing.supplier.* defaults).
 */
final class InvoicePdfService
{
    public function generate(Invoice $invoice): string
    {
        $invoice->load(['items', 'customer.reseller']);

        $brand = $this->resolveBrand($invoice);

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice', 'brand'));

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'faktura-' . str_replace(['/', '\\', ' '], '-', $invoice->number) . '.pdf';
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveBrand(Invoice $invoice): array
    {
        $customer = $invoice->customer;
        $reseller = $customer?->reseller;

        if ($reseller === null) {
            return [];
        }

        $b = $reseller->branding ?? [];

        return [
            'name'         => ($b['invoice_company_name'] ?? null) ?: ($b['company_name'] ?? null) ?: $reseller->business_name,
            'email'        => $b['invoice_email'] ?? null,
            'website'      => $b['invoice_website'] ?? null,
            'street'       => $b['invoice_street'] ?? null,
            'zip'          => $b['invoice_zip'] ?? null,
            'city'         => $b['invoice_city'] ?? null,
            'ic'           => $b['invoice_ic'] ?? null,
            'dic'          => $b['invoice_dic'] ?? null,
            'bank_account' => $b['invoice_bank_account'] ?? null,
            'footer_note'  => $b['invoice_footer_note'] ?? null,
            'primary_color' => $b['primary_color'] ?? null,
        ];
    }
}
