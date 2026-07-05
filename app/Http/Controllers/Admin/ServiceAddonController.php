<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\ServiceAddon;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceAddonController extends Controller
{
    public function index(): View
    {
        return view('admin.service-addons.index', [
            'addons' => ServiceAddon::withCount('subscriptions')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.service-addons.form', ['addon' => new ServiceAddon()]);
    }

    public function store(Request $request): RedirectResponse
    {
        ServiceAddon::create($this->validated($request));

        return redirect()->route('admin.service-addons.index')
            ->with('status', 'Doplněk byl vytvořen.');
    }

    public function edit(ServiceAddon $serviceAddon): View
    {
        return view('admin.service-addons.form', ['addon' => $serviceAddon]);
    }

    public function update(Request $request, ServiceAddon $serviceAddon): RedirectResponse
    {
        $serviceAddon->update($this->validated($request));

        return redirect()->route('admin.service-addons.index')
            ->with('status', 'Doplněk byl uložen.');
    }

    public function destroy(ServiceAddon $serviceAddon): RedirectResponse
    {
        $serviceAddon->delete();

        return redirect()->route('admin.service-addons.index')
            ->with('status', 'Doplněk byl smazán.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'slug'       => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_czk'  => ['required', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
