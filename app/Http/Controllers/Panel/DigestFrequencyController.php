<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DigestFrequencyController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        return view('panel.account.digest-frequency', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'digest_frequency' => ['required', 'in:daily,weekly,never'],
        ]);

        $request->user()->update(['digest_frequency' => $validated['digest_frequency']]);

        return back()->with('status', 'Frekvence digestu uložena.');
    }
}
