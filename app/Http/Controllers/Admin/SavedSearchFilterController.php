<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SavedSearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedSearchFilterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = SavedSearchFilter::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return view('admin.saved-search-filters.index', compact('filters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $filtersRaw = $request->input('filters_raw') ?? '{}';
        $decoded    = json_decode($filtersRaw, true);
        $request->merge(['filters' => is_array($decoded) ? $decoded : []]);

        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'context'    => 'required|string|max:50',
            'filters'    => 'required|array',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            SavedSearchFilter::where('user_id', $request->user()->id)
                ->where('context', $validated['context'])
                ->update(['is_default' => false]);
        }

        SavedSearchFilter::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Filtr uložen.');
    }

    public function update(Request $request, SavedSearchFilter $savedSearchFilter): RedirectResponse
    {
        abort_unless($savedSearchFilter->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'is_default' => 'required|boolean',
        ]);

        if ($validated['is_default']) {
            SavedSearchFilter::where('user_id', $request->user()->id)
                ->where('context', $savedSearchFilter->context)
                ->update(['is_default' => false]);
        }

        $savedSearchFilter->update($validated);

        return back()->with('status', 'Filtr aktualizován.');
    }

    public function destroy(Request $request, SavedSearchFilter $savedSearchFilter): RedirectResponse
    {
        abort_unless($savedSearchFilter->user_id === $request->user()->id, 403);

        $savedSearchFilter->delete();

        return back()->with('status', 'Filtr smazán.');
    }
}
