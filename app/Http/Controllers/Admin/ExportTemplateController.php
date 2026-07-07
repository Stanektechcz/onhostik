<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExportTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = ExportTemplate::with('creator')
            ->where(function ($q) use ($request): void {
                $q->where('created_by', $request->user()->id)
                  ->orWhere('is_shared', true);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.export-templates.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $columnsRaw = $request->input('columns_raw') ?? '';
        $request->merge([
            'columns' => array_values(array_filter(array_map('trim', explode(',', $columnsRaw)))),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'entity_type' => ['required', 'string', 'max:50'],
            'columns'     => ['required', 'array', 'min:1'],
            'columns.*'   => ['required', 'string'],
            'filters'     => ['nullable', 'array'],
            'format'      => ['required', 'in:csv,xlsx,json'],
            'is_shared'   => ['boolean'],
        ]);

        ExportTemplate::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Šablona exportu vytvořena.');
    }

    public function destroy(ExportTemplate $exportTemplate): RedirectResponse
    {
        $exportTemplate->delete();

        return back()->with('status', 'Šablona exportu smazána.');
    }
}
