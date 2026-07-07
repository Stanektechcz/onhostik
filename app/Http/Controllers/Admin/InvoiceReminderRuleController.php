<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceReminderRuleController extends Controller
{
    public function index(): View
    {
        $rules = InvoiceReminderRule::orderBy('days_after_due')->get();

        return view('admin.invoice-reminder-rules.index', compact('rules'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days_after_due' => ['required', 'integer', 'min:1', 'max:90'],
            'channel'        => ['required', 'in:mail,database'],
            'template_key'   => ['required', 'string', 'max:100'],
        ]);

        InvoiceReminderRule::updateOrCreate(
            ['days_after_due' => $validated['days_after_due'], 'channel' => $validated['channel']],
            ['template_key' => $validated['template_key'], 'is_active' => true]
        );

        return back()->with('status', 'Pravidlo upomínky uloženo.');
    }

    public function update(Request $request, InvoiceReminderRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'is_active'    => ['required', 'boolean'],
            'template_key' => ['required', 'string', 'max:100'],
        ]);

        $rule->update($validated);

        return back()->with('status', 'Pravidlo aktualizováno.');
    }

    public function destroy(InvoiceReminderRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', 'Pravidlo smazáno.');
    }
}
