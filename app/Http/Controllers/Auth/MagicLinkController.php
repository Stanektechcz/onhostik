<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.magic-link-request');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if ($user !== null) {
            $token = Str::random(64);
            LoginToken::create([
                'user_id'    => $user->id,
                'token'      => $token,
                'expires_at' => now()->addMinutes(15),
            ]);
            $user->notify(new \App\Notifications\MagicLinkNotification($token));
        }

        return back()->with('status', 'Pokud zadaný e-mail existuje, byl na něj zaslán přihlašovací odkaz.');
    }

    public function login(string $token): RedirectResponse
    {
        $record = LoginToken::where('token', $token)->first();

        if ($record === null || ! $record->isValid()) {
            return redirect('/login')->withErrors(['token' => 'Odkaz je neplatný nebo vypršel.']);
        }

        $record->update(['used_at' => now()]);
        Auth::loginUsingId($record->user_id);

        return redirect('/panel');
    }
}
