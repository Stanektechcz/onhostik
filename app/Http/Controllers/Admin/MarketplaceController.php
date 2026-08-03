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
            'price_czk'   => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ] + self::recipeRules());

        MarketplaceApp::create([
            'slug'         => $validated['slug'],
            'name'         => $validated['name'],
            'category'     => $validated['category'],
            'description'  => $validated['description'] ?? null,
            'icon'         => $validated['icon'] ?? 'package',
            'min_disk_gb'  => (int) ($validated['min_disk_gb'] ?? 1),
            'price_halere' => (int) round(((float) ($validated['price_czk'] ?? 0)) * 100),
            'sort_order'   => (int) ($validated['sort_order'] ?? 0),
            'is_active'    => (bool) ($validated['is_active'] ?? true),
        ] + $this->recipeAttributes($request, $validated));

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
            'price_czk'   => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['boolean'],
        ] + self::recipeRules());

        $app->update([
            'name'         => $validated['name'],
            'category'     => $validated['category'],
            'description'  => $validated['description'] ?? null,
            'icon'         => $validated['icon'] ?? 'package',
            'min_disk_gb'  => (int) ($validated['min_disk_gb'] ?? 1),
            'price_halere' => (int) round(((float) ($validated['price_czk'] ?? 0)) * 100),
            'sort_order'   => (int) ($validated['sort_order'] ?? 0),
            'is_active'    => (bool) ($validated['is_active'] ?? true),
        ] + $this->recipeAttributes($request, $validated));

        return back()->with('status', 'Aplikace aktualizována.');
    }

    /**
     * The one-click install recipe. These values end up in a shell command on a
     * hosting node, so they are constrained here as well as in AppInstaller —
     * the installer refuses a bad recipe at deploy time, but an operator should
     * find out at save time, not when a customer clicks Install.
     *
     * @return array<string, list<string>>
     */
    private static function recipeRules(): array
    {
        return [
            'install_url'           => ['nullable', 'string', 'max:500', 'url', 'starts_with:https://'],
            'archive_subdir'        => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'install_path'          => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-\/]+$/'],
            'requires_database'     => ['boolean'],
            'post_install_commands' => ['nullable', 'string', 'max:1000'],
            'docs_url'              => ['nullable', 'string', 'max:300', 'url'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function recipeAttributes(Request $request, array $validated): array
    {
        return [
            'install_source'        => 'archive',
            'install_url'           => $validated['install_url'] ?? null,
            'archive_subdir'        => $validated['archive_subdir'] ?? null,
            'install_path'          => $validated['install_path'] ?? null,
            'requires_database'     => $request->boolean('requires_database'),
            'post_install_commands' => $validated['post_install_commands'] ?? null,
            'docs_url'              => $validated['docs_url'] ?? null,
        ];
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
