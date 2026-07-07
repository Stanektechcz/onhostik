<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DunningConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DunningConfigController extends Controller
{
    public function index(): View
    {
        $configs = DunningConfig::orderBy('step')->orderBy('days_after_due')->paginate(30);

        return view('admin.dunning-configs.index', compact('configs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'step'           => ['required', 'integer', 'min:1', 'max:10'],
            'days_after_due' => ['required', 'integer', 'min:0', 'max:365'],
            'action'         => ['required', 'in:email,suspend,cancel'],
            'email_template' => ['nullable', 'string', 'max:100'],
            'is_active'      => ['boolean'],
        ]);

        DunningConfig::create($validated);

        return back()->with('status', 'Dunning krok přidán.');
    }

    public function update(Request $request, DunningConfig $dunningConfig): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'days_after_due' => ['required', 'integer', 'min:0', 'max:365'],
            'action'         => ['required', 'in:email,suspend,cancel'],
            'email_template' => ['nullable', 'string', 'max:100'],
            'is_active'      => ['boolean'],
        ]);

        $dunningConfig->update($validated);

        return back()->with('status', 'Dunning krok aktualizován.');
    }

    public function destroy(DunningConfig $dunningConfig): RedirectResponse
    {
        $dunningConfig->delete();

        return back()->with('status', 'Dunning krok smazán.');
    }
}
