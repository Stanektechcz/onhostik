<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceWindow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    public function index(): View
    {
        $windows = MaintenanceWindow::orderByDesc('starts_at')->get();

        return view('admin.maintenance.index', compact('windows'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:200'],
            'message'          => ['required', 'string', 'max:2000'],
            'starts_at'        => ['required', 'date'],
            'ends_at'          => ['required', 'date', 'after:starts_at'],
            'show_on_frontend' => ['boolean'],
            'show_on_admin'    => ['boolean'],
            'color'            => ['required', Rule::in(MaintenanceWindow::COLORS)],
            'is_active'        => ['boolean'],
        ]);

        MaintenanceWindow::create([
            'title'            => $data['title'],
            'message'          => $data['message'],
            'starts_at'        => $data['starts_at'],
            'ends_at'          => $data['ends_at'],
            'show_on_frontend' => !empty($data['show_on_frontend']),
            'show_on_admin'    => !empty($data['show_on_admin']),
            'color'            => $data['color'],
            'is_active'        => $data['is_active'] ?? true,
        ]);

        return redirect()->route('admin.maintenance-banners.index')
            ->with('success', 'Okno údržby bylo vytvořeno.');
    }

    public function update(Request $request, MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        $maintenance = $maintenanceWindow;
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:200'],
            'message'          => ['required', 'string', 'max:2000'],
            'starts_at'        => ['required', 'date'],
            'ends_at'          => ['required', 'date', 'after:starts_at'],
            'show_on_frontend' => ['boolean'],
            'show_on_admin'    => ['boolean'],
            'color'            => ['required', Rule::in(MaintenanceWindow::COLORS)],
            'is_active'        => ['boolean'],
        ]);

        $maintenance->update([
            'title'            => $data['title'],
            'message'          => $data['message'],
            'starts_at'        => $data['starts_at'],
            'ends_at'          => $data['ends_at'],
            'show_on_frontend' => !empty($data['show_on_frontend']),
            'show_on_admin'    => !empty($data['show_on_admin']),
            'color'            => $data['color'],
            'is_active'        => !empty($data['is_active']),
        ]);

        return redirect()->route('admin.maintenance-banners.index')
            ->with('success', 'Okno údržby bylo aktualizováno.');
    }

    public function destroy(MaintenanceWindow $maintenanceWindow): RedirectResponse
    {
        $maintenanceWindow->delete();

        return redirect()->route('admin.maintenance-banners.index')
            ->with('success', 'Okno údržby bylo smazáno.');
    }
}
