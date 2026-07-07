<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceMigrationBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceMigrationBatchController extends Controller
{
    public function index(): View
    {
        $batches = ServiceMigrationBatch::orderByDesc('created_at')->paginate(15);
        return view('admin.service-migration-batches.index', compact('batches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $serviceIdsRaw = $request->input('service_ids_raw') ?? '';
        $request->merge([
            'service_ids' => array_values(array_filter(array_map('intval', array_filter(array_map('trim', explode(',', $serviceIdsRaw)))))),
        ]);

        $validated = $request->validate([
            'name'             => ['required', 'max:100'],
            'source_server_id' => ['required', 'integer'],
            'target_server_id' => ['required', 'integer', 'different:source_server_id'],
            'service_ids'      => ['required', 'array'],
            'service_ids.*'    => ['integer'],
        ]);

        ServiceMigrationBatch::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status'     => 'pending',
        ]);

        return back()->with('status', 'Migrační dávka vytvořena.');
    }
}
