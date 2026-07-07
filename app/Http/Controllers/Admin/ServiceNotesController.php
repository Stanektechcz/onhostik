<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceNotesController extends Controller
{
    /** Admin-only notes & label editor for a service. */
    public function edit(Service $service): View
    {
        return view('admin.service-notes.edit', [
            'service'      => $service->load('labels', 'customer.user'),
            'allLabels'    => ServiceLabel::orderBy('name')->get(),
            'serviceLabels'=> $service->labels->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:5000'],
            'labels'     => ['nullable', 'array'],
            'labels.*'   => ['integer', 'exists:service_labels,id'],
        ]);

        $service->update(['admin_note' => $validated['admin_note'] ?? null]);
        $service->labels()->sync($validated['labels'] ?? []);

        return redirect()
            ->route('admin.services.show', $service)
            ->with('status', 'Poznámka a štítky byly uloženy.');
    }

    // ── Label CRUD ────────────────────────────────────────────────────────────

    public function labelsIndex(): View
    {
        return view('admin.service-notes.labels', [
            'labels' => ServiceLabel::withCount('services')->orderBy('name')->paginate(50),
            'colors' => ServiceLabel::COLORS,
        ]);
    }

    public function labelsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:60', 'unique:service_labels,name'],
            'color' => ['required', 'in:' . implode(',', ServiceLabel::COLORS)],
        ]);

        ServiceLabel::create($validated);

        return redirect()
            ->route('admin.service-labels.index')
            ->with('status', "Štítek \"{$validated['name']}\" byl vytvořen.");
    }

    public function labelsDestroy(ServiceLabel $serviceLabel): RedirectResponse
    {
        $serviceLabel->delete();

        return redirect()
            ->route('admin.service-labels.index')
            ->with('status', 'Štítek byl smazán.');
    }
}
