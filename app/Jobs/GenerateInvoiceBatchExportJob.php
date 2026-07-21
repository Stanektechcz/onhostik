<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Shared\Support\LogContext;
use App\Models\ExportJob;
use App\Notifications\ExportReadyNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Renders an invoice batch export off the request thread (audit L120).
 *
 * Previously 50 dompdf renders ran inside the web request: minutes of CPU
 * against a request timeout, and a failure left the admin with a blank error
 * page and nothing to retry.
 */
class GenerateInvoiceBatchExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;
    public int $tries   = 2;

    public function __construct(public int $exportJobId) {}

    public function handle(): void
    {
        $export = ExportJob::find($this->exportJobId);

        if ($export === null || $export->status !== ExportJob::STATUS_PENDING) {
            return; // already picked up, or cancelled
        }

        LogContext::with(
            ['export_job_id' => $export->id, 'user_id' => $export->user_id],
            fn () => $this->generate($export),
        );
    }

    private function generate(ExportJob $export): void
    {
        $export->update(['status' => ExportJob::STATUS_RUNNING, 'started_at' => now()]);

        try {
            /** @var list<int> $ids */
            $ids = $export->parameters['invoice_ids'] ?? [];

            $invoices = Invoice::with('customer.user', 'items')->whereIn('id', $ids)->get();

            $tmp = tempnam(sys_get_temp_dir(), 'invoices_') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::CREATE);

            foreach ($invoices as $invoice) {
                $zip->addFromString(
                    'faktura-' . $invoice->number . '.pdf',
                    Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'isPdf' => true])->output(),
                );
            }

            $zip->close();

            // Private disk: these hold complete invoice data and must never be
            // reachable by guessing a public URL.
            $path = 'exports/' . $export->id . '-faktury-' . now()->format('Y-m-d') . '.zip';

            /*
             | ZipArchive writes NOTHING to disk for an archive with no entries,
             | so an empty selection left $tmp non-existent and file_get_contents
             | blew up. An empty export is a valid (if pointless) result, not a
             | crash — store an empty archive so the download still works.
             */
            Storage::disk('local')->put(
                $path,
                is_file($tmp) ? (string) file_get_contents($tmp) : $this->emptyZip(),
            );

            if (is_file($tmp)) {
                @unlink($tmp);
            }

            $export->update([
                'status'      => ExportJob::STATUS_READY,
                'file_path'   => $path,
                'file_size'   => Storage::disk('local')->size($path),
                'row_count'   => $invoices->count(),
                'finished_at' => now(),
                'expires_at'  => now()->addDays((int) config('billing.export_retention_days', 7)),
            ]);

            $export->user?->notify(new ExportReadyNotification($export));
        } catch (Throwable $e) {
            /*
             | Record the failure ON the job rather than only throwing: the
             | admin needs to see "this export failed and why" in the UI, not
             | discover it from a blank download.
             */
            $export->update([
                'status'        => ExportJob::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'finished_at'   => now(),
            ]);

            throw $e;
        }
    }

    /** A valid, empty ZIP archive (end-of-central-directory record only). */
    private function emptyZip(): string
    {
        return "PK\x05\x06" . str_repeat("\x00", 18);
    }
}
