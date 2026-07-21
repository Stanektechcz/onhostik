<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoiceBatchExportJob;
use App\Models\ExportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice batch export (audit L120).
 *
 * Used to render up to 50 PDFs synchronously inside the request — minutes of
 * CPU against a request timeout, and a failure gave a blank error page with
 * nothing to retry. Now it queues a tracked job and notifies when the archive
 * is ready, which also lifts the cap from 50 to 500.
 */
class InvoiceBatchExportController extends Controller
{
    public function export(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_ids'   => ['required', 'array', 'max:500'],
            'invoice_ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $export = ExportJob::create([
            'user_id'    => $request->user()->id,
            'type'       => 'invoice_batch',
            'format'     => 'zip',
            'status'     => ExportJob::STATUS_PENDING,
            'parameters' => ['invoice_ids' => $validated['invoice_ids']],
        ]);

        GenerateInvoiceBatchExportJob::dispatch($export->id);

        return back()->with(
            'status',
            'Export byl zařazen ke zpracování. Až bude hotový, dáme vám vědět.',
        );
    }

    public function download(Request $request, ExportJob $export): StreamedResponse
    {
        /*
         | The archive holds complete invoice data for many customers, so it is
         | scoped to the person who asked for it — an admin must not be able to
         | pull another admin's export by guessing an id — and only while it is
         | ready and unexpired.
         */
        abort_unless($export->user_id === $request->user()->id, 403);
        abort_unless($export->isDownloadable(), 404);

        return Storage::disk('local')->download(
            (string) $export->file_path,
            'faktury-export-' . $export->created_at->format('Y-m-d') . '.zip',
        );
    }
}
