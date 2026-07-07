<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerWhitelabelController extends Controller
{
    public function index(): View
    {
        $resellers = ResellerProfile::with('user')
            ->where('status', 'active')
            ->orderBy('business_name')
            ->get();

        return view('admin.reseller-whitelabel.index', compact('resellers'));
    }

    public function edit(ResellerProfile $reseller): View
    {
        return view('admin.reseller-whitelabel.edit', compact('reseller'));
    }

    public function update(Request $request, ResellerProfile $reseller): RedirectResponse
    {
        $validated = $request->validate([
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'panel_title'   => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'branding'      => ['nullable', 'array'],
            'branding.logo_url'      => ['nullable', 'string', 'max:500'],
            'branding.primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $reseller->update($validated);

        return back()->with('status', 'White-label nastavení uložena.');
    }
}
