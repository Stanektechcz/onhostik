<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketMacro;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketMacroController extends Controller
{
    public function index(): View
    {
        return view('admin.ticket-macros.index', [
            'macros' => TicketMacro::query()->with('creator')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'body'  => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        TicketMacro::create([
            'title'      => $validated['title'],
            'body'       => $validated['body'],
            'created_by' => $admin->id,
        ]);

        return back()->with('status', 'Makro bylo vytvořeno.');
    }

    public function update(Request $request, TicketMacro $macro): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'body'  => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $macro->update($validated);

        return back()->with('status', 'Makro bylo aktualizováno.');
    }

    public function destroy(TicketMacro $macro): RedirectResponse
    {
        $macro->delete();

        return back()->with('status', 'Makro bylo smazáno.');
    }
}
