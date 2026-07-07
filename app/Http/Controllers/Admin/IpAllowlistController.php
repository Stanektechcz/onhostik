<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminIpAllowlist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IpAllowlistController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.ip-allowlist', [
            'entries'    => AdminIpAllowlist::query()->with('createdBy')->orderByDesc('id')->get(),
            'currentIp'  => $request->ip(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cidr'  => ['required', 'string', 'max:50', 'regex:/^[\d.:a-fA-F\/]+$/'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);

        AdminIpAllowlist::create([
            'cidr'       => trim($validated['cidr']),
            'label'      => $validated['label'] ?? null,
            'is_active'  => true,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'IP adresa byla přidána do allowlistu.');
    }

    public function update(Request $request, AdminIpAllowlist $entry): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'label'     => ['nullable', 'string', 'max:120'],
        ]);

        $entry->update([
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'label'     => $validated['label'] ?? $entry->label,
        ]);

        return back()->with('status', 'Záznam byl upraven.');
    }

    public function destroy(AdminIpAllowlist $entry): RedirectResponse
    {
        $entry->delete();

        return back()->with('status', 'IP adresa byla odebrána z allowlistu.');
    }
}
