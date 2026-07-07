<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ZipArchive;

class InvoiceBatchExportController extends Controller
{
    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'invoice_ids'   => ['required', 'array', 'max:50'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $invoices = Invoice::with('customer.user')
            ->whereIn('id', $validated['invoice_ids'])
            ->get();

        $zipPath = tempnam(sys_get_temp_dir(), 'invoices_') . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($invoices as $invoice) {
            $pdf      = Pdf::loadView('pdf.invoice', compact('invoice'));
            $filename = 'faktura-' . $invoice->number . '.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        $content = file_get_contents($zipPath);
        @unlink($zipPath);

        return response((string) $content)
            ->header('Content-Type', 'application/zip')
            ->header('Content-Disposition', 'attachment; filename="faktury-export-' . now()->format('Y-m-d') . '.zip"');
    }
}
