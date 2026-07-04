<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function show(Request $request): View
    {
        $user    = $request->user();
        $profile = $user
            ? ResellerProfile::where('user_id', $user->id)->where('status', 'active')->firstOrFail()
            : abort(403);

        return view('reseller.branding', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user    = $request->user();
        $profile = $user
            ? ResellerProfile::where('user_id', $user->id)->where('status', 'active')->firstOrFail()
            : abort(403);

        $validated = $request->validate([
            'company_name'  => ['nullable', 'string', 'max:150'],
            'tagline'       => ['nullable', 'string', 'max:255'],
            'logo_url'      => ['nullable', 'url', 'max:500'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $existing = $profile->branding ?? [];
        $merged   = array_merge($existing, array_filter($validated, fn ($v) => $v !== null));

        $profile->update(['branding' => $merged]);

        return back()->with('status', 'Branding byl uložen.');
    }
}
