<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\FinancialExportJob;
use App\Domains\Billing\Services\FinancialExportService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FinancialExportController extends Controller
{
    public function __construct(private readonly FinancialExportService $exportService) {}

    public function index(): View
    {
        $jobs = FinancialExportJob::with('creator')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.exports.index', compact('jobs'));
    }

    public function create(): View
    {
        return view('admin.exports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'format'    => ['required', 'in:pohoda_xml,csv_invoices,csv_payments,pdf_summary'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'status'    => ['nullable', 'string'],
        ]);

        $job = FinancialExportJob::create([
            'uuid'       => Str::uuid()->toString(),
            'created_by' => $request->user()?->id,
            'format'     => $validated['format'],
            'date_from'  => $validated['date_from'] ?? null,
            'date_to'    => $validated['date_to'] ?? null,
            'status'     => 'pending',
            'filters'    => array_filter(['status' => $validated['status'] ?? null]),
        ]);

        // Process synchronously (can be queued in future)
        $this->exportService->process($job);

        return redirect()->route('admin.exports.index')
            ->with('status', 'Export byl vygenerován.');
    }

    public function download(FinancialExportJob $export): Response|RedirectResponse
    {
        if (!$export->isDownloadable()) {
            return redirect()->route('admin.exports.index')
                ->with('error', 'Export není dostupný ke stažení.');
        }

        $path    = $export->file_path;
        $content = Storage::disk('local')->get($path);

        if ($content === null) {
            return redirect()->route('admin.exports.index')
                ->with('error', 'Soubor exportu nebyl nalezen.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeTypes = [
            'xml' => 'application/xml',
            'csv' => 'text/csv; charset=Windows-1250',
            'pdf' => 'application/pdf',
        ];

        $mime     = $mimeTypes[$extension] ?? 'application/octet-stream';
        $filename = sprintf('%s_%s.%s', $export->format, $export->created_at->format('Y-m-d'), $extension);

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function destroy(FinancialExportJob $export): RedirectResponse
    {
        if ($export->file_path) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->delete();

        return redirect()->route('admin.exports.index')
            ->with('status', 'Export byl odstraněn.');
    }
}
