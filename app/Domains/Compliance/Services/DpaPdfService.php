<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Services;

use App\Domains\Customer\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a Data Processing Agreement PDF for a customer (audit 178).
 *
 * The processor's identity and the standing art. 28(3) particulars come from
 * config; the controller's identity is the customer. Nothing here is a live
 * secret — a DPA is a document the customer is entitled to hold — but it does
 * name real parties, so it is generated per-customer and never cached across
 * them.
 */
final class DpaPdfService
{
    /**
     * @return array{pdf: string, filename: string, version: string}
     */
    public function generate(Customer $customer): array
    {
        $version = (string) config('legal.document_versions.dpa', 'n/a');

        $data = [
            'customer'   => $customer,
            'processor'  => config('billing.supplier'),
            'dpa'        => config('legal.dpa'),
            'version'    => $version,
            'generatedAt' => now(),
            'isPdf'      => true,
        ];

        $pdf = Pdf::loadView('pdf.dpa', $data);
        $pdf->setPaper('A4', 'portrait');

        return [
            'pdf'      => $pdf->output(),
            'filename' => $this->filename($customer, $version),
            'version'  => $version,
        ];
    }

    private function filename(Customer $customer, string $version): string
    {
        $slug = str_replace(['/', '\\', ' '], '-', (string) ($customer->company_name ?? ('zakaznik-' . $customer->id)));

        return "DPA-{$slug}-{$version}.pdf";
    }
}
