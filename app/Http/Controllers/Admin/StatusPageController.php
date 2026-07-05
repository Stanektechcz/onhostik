<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\StatusPageComponent;
use App\Domains\Monitoring\Models\StatusPageMaintenance;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StatusPageController extends Controller
{
    public function index(): View
    {
        return view('admin.status-page.index', [
            'components'   => StatusPageComponent::query()->with('monitor')->orderBy('sort_order')->get(),
            'maintenances' => StatusPageMaintenance::query()->orderByDesc('scheduled_start_at')->paginate(15),
            'monitors'     => Monitor::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'label']),
        ]);
    }

    // ── Components ────────────────────────────────────────────────────────────

    public function storeComponent(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'description'=> ['nullable', 'string', 'max:500'],
            'monitor_id' => ['nullable', 'integer', 'exists:monitors,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['boolean'],
        ]);

        StatusPageComponent::create([
            'name'        => $validated['name'],
            'group_name'  => $validated['group_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'monitor_id'  => $validated['monitor_id'] ?? null,
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_visible'  => (bool) ($validated['is_visible'] ?? true),
        ]);

        return back()->with('status', 'Komponenta přidána.');
    }

    public function updateComponent(Request $request, StatusPageComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'group_name'  => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'monitor_id'  => ['nullable', 'integer', 'exists:monitors,id'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_visible'  => ['boolean'],
        ]);

        $component->update([
            'name'        => $validated['name'],
            'group_name'  => $validated['group_name'] ?? null,
            'description' => $validated['description'] ?? null,
            'monitor_id'  => $validated['monitor_id'] ?? null,
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_visible'  => (bool) ($validated['is_visible'] ?? true),
        ]);

        return back()->with('status', 'Komponenta upravena.');
    }

    public function destroyComponent(StatusPageComponent $component): RedirectResponse
    {
        $component->delete();

        return back()->with('status', 'Komponenta odstraněna.');
    }

    // ── Maintenances ──────────────────────────────────────────────────────────

    public function storeMaintenance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'scheduled_start_at'  => ['required', 'date'],
            'scheduled_end_at'    => ['required', 'date', 'after:scheduled_start_at'],
            'status'              => ['required', 'in:scheduled,in_progress,completed'],
        ]);

        StatusPageMaintenance::create($validated);

        return back()->with('status', 'Plánovaná údržba přidána.');
    }

    public function updateMaintenance(Request $request, StatusPageMaintenance $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'scheduled_start_at'  => ['required', 'date'],
            'scheduled_end_at'    => ['required', 'date', 'after:scheduled_start_at'],
            'status'              => ['required', 'in:scheduled,in_progress,completed'],
        ]);

        $maintenance->update($validated);

        return back()->with('status', 'Údržba aktualizována.');
    }

    public function destroyMaintenance(StatusPageMaintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return back()->with('status', 'Údržba odstraněna.');
    }
}
