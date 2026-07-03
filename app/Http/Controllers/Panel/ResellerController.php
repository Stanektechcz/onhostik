<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Reseller\Models\ResellerProfile;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerController extends Controller
{
    public function index(Request $request): View
    {
        $user    = $request->user();
        $profile = $user ? ResellerProfile::where('user_id', $user->id)->first() : null;

        return view('panel.reseller-program', compact('profile'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && ResellerProfile::where('user_id', $user->id)->exists()) {
            return redirect()->route('panel.reseller-program')->with('status', 'Žádost již existuje.');
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-\.]{0,251}[a-zA-Z0-9]$/'],
        ]);

        ResellerProfile::create([
            'user_id'       => $user->id,
            'business_name' => $validated['business_name'],
            'custom_domain' => $validated['custom_domain'] ?? null,
            'markup_percent' => 0,
            'status'        => 'pending',
        ]);

        activity('reseller')
            ->causedBy($user)
            ->log('reseller.applied');

        return redirect()->route('panel.reseller-program')
            ->with('status', 'Žádost o reseller program byla odeslána. Vyčkejte prosím na schválení.');
    }
}
