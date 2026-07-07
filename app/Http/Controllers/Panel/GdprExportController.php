<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateGdprExport;
use App\Models\GdprExportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GdprExportController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $exports = GdprExportRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('panel.gdpr.export', compact('exports'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $pending = GdprExportRequest::where('user_id', $user->id)
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if ($pending) {
            return back()->withErrors(['export' => 'Export již probíhá. Vyčkejte na dokončení.']);
        }

        $exportRequest = GdprExportRequest::create([
            'user_id' => $user->id,
            'status'  => 'queued',
        ]);

        GenerateGdprExport::dispatch($exportRequest);

        return back()->with('status', 'Export byl zařazen do fronty. Budete upozorněni e-mailem.');
    }

    public function download(Request $request, string $token): BinaryFileResponse|RedirectResponse
    {
        $export = GdprExportRequest::where('user_id', $request->user()->id)
            ->where('download_token', $token)
            ->where('status', 'ready')
            ->where('expires_at', '>', now())
            ->first();

        if ($export === null) {
            return redirect()->route('panel.gdpr.export.index')
                ->withErrors(['export' => 'Odkaz pro stažení není platný nebo vypršel.']);
        }

        $export->update(['downloaded_at' => now()]);

        $path = storage_path('app/gdpr/' . $export->id . '.zip');

        if (! file_exists($path)) {
            return redirect()->route('panel.gdpr.export.index')
                ->withErrors(['export' => 'Soubor nenalezen. Požádejte o nový export.']);
        }

        return response()->download($path, 'gdpr-export-' . now()->format('Y-m-d') . '.zip');
    }
}
