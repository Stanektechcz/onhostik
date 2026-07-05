<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index(): View
    {
        return view('admin.marketplace.index', [
            'apps' => MarketplaceApp::query()->withCount('installations')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug'        => ['required', 'string', 'max:64', 'unique:marketplace_apps,slug', 'regex:/^[a-z0-9\-]+$/'],
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'in:cms,ecommerce,database,email,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'min_disk_gb' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        MarketplaceApp::create([
            'slug'        => $validated['slug'],
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? 'package',
            'min_disk_gb' => (int) ($validated['min_disk_gb'] ?? 1),
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('status', 'Aplikace přidána do marketplace.');
    }

    public function update(Request $request, MarketplaceApp $app): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'category'    => ['required', 'in:cms,ecommerce,database,email,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'min_disk_gb' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ]);

        $app->update([
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? 'package',
            'min_disk_gb' => (int) ($validated['min_disk_gb'] ?? 1),
            'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            'is_active'   => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('status', 'Aplikace aktualizována.');
    }

    public function destroy(MarketplaceApp $app): RedirectResponse
    {
        $app->delete();

        return back()->with('status', 'Aplikace odstraněna.');
    }

    public function toggle(MarketplaceApp $app): RedirectResponse
    {
        $app->update(['is_active' => ! $app->is_active]);

        return back()->with('status', $app->is_active ? 'Aplikace aktivována.' : 'Aplikace deaktivována.');
    }
}
