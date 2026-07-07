<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceConfigProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceConfigProfileController extends Controller
{
    public function index(Request $request): View
    {
        $serviceType = $request->query('service_type');
        $profiles    = ServiceConfigProfile::when($serviceType, fn($q) => $q->where('service_type', $serviceType))
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('admin.service-config-profiles.index', compact('profiles', 'serviceType'));
    }

    public function store(Request $request): RedirectResponse
    {
        $configRaw  = $request->input('config_data_raw') ?? '{}';
        $configData = json_decode($configRaw, true) ?? [];
        $validated  = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string',
            'service_type' => 'required|string|max:100',
            'is_active'    => 'boolean',
        ]);
        $validated['is_active']   = $request->boolean('is_active', true);
        $validated['config_data'] = $configData;
        $validated['created_by']  = $request->user()->id;
        ServiceConfigProfile::create($validated);
        return back()->with('status', 'Profil konfigurace vytvořen.');
    }

    public function update(Request $request, ServiceConfigProfile $serviceConfigProfile): RedirectResponse
    {
        $serviceConfigProfile->update(['is_active' => !$serviceConfigProfile->is_active]);
        return back()->with('status', 'Stav profilu aktualizován.');
    }

    public function destroy(ServiceConfigProfile $serviceConfigProfile): RedirectResponse
    {
        $serviceConfigProfile->delete();
        return back()->with('status', 'Profil odstraněn.');
    }
}
