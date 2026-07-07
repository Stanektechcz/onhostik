<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ZipArchive;

class InvoiceBulkDownloadController extends Controller
{
    public function download(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'invoice_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'invoice_ids.*' => ['integer'],
        ]);

        /** @var \App\Models\User $user */
        $user     = $request->user();
        $customer = $user->customer;

        if ($customer === null) {
            return back()->withErrors(['error' => 'Zákaznický profil nenalezen.']);
        }

        $invoices = Invoice::query()
            ->with('items')
            ->where('customer_id', $customer->id)
            ->whereIn('id', $validated['invoice_ids'])
            ->get();

        if ($invoices->isEmpty()) {
            return back()->withErrors(['error' => 'Žádné faktury nenalezeny.']);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'invoices_') . '.zip';

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($invoices as $invoice) {
            $pdf      = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
            $filename = $invoice->number . '.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        $zipContent = (string) file_get_contents($zipPath);
        @unlink($zipPath);

        return response($zipContent, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="faktury.zip"',
        ]);
    }
}
