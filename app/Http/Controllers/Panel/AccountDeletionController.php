<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function create(Request $request): View
    {
        $existing = AccountDeletionRequest::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        return view('panel.account.delete', compact('existing'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $existing = AccountDeletionRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return back()->withErrors(['status' => 'Žádost o smazání účtu již existuje.']);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        AccountDeletionRequest::create([
            'user_id' => $user->id,
            'reason'  => $validated['reason'] ?? null,
            'status'  => 'pending',
        ]);

        return redirect()->route('panel.dashboard')->with('status', 'Žádost o smazání účtu odeslána. Bude zpracována do 30 dní.');
    }
}
