<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutoSuspendRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoSuspendRuleController extends Controller
{
    public function index(): View
    {
        $rules = AutoSuspendRule::orderBy('name')->paginate(20);

        return view('admin.auto-suspend-rules.index', compact('rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:100',
            'trigger'         => 'required|in:overdue_days,usage_percent,failed_payments',
            'threshold_value' => 'required|integer|min:1',
            'description'     => 'nullable|string|max:500',
            'is_active'       => 'boolean',
        ]);

        AutoSuspendRule::create($validated);

        return back()->with('status', 'Pravidlo vytvořeno.');
    }

    public function update(Request $request, AutoSuspendRule $autoSuspendRule): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $autoSuspendRule->update(['is_active' => $validated['is_active']]);

        return back()->with('status', 'Pravidlo aktualizováno.');
    }

    public function destroy(AutoSuspendRule $autoSuspendRule): RedirectResponse
    {
        $autoSuspendRule->delete();

        return back()->with('status', 'Pravidlo smazáno.');
    }
}
