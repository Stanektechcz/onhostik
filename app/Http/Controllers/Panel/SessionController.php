<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $userId   = $request->user()->getAuthIdentifier();
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get();

        $currentId = $request->session()->getId();

        return view('panel.account.sessions', [
            'sessions'  => $sessions,
            'currentId' => $currentId,
        ]);
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->delete();

        return back()->with('status', 'Relace byla ukončena.');
    }

    /**
     * End every OTHER session (audit H117).
     *
     * Deleting the session rows alone was not enough: a "remember me" cookie
     * on another device re-authenticates from the user's remember_token, not
     * from a session row, so that device would simply walk back in. The token
     * is therefore cycled explicitly.
     *
     * (Laravel's Auth::logoutOtherDevices() leans on the AuthenticateSession
     * middleware, which this application does not register — so relying on it
     * alone would have been a no-op here.)
     *
     * The current password is required so someone holding a stolen session
     * cannot use this against the rightful owner. This matters more than
     * usual because two-factor authentication is optional here — this is the
     * customer's own lever when they suspect a compromise.
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.required'         => 'Pro ukončení ostatních relací zadejte své heslo.',
            'password.current_password' => 'Zadané heslo není správné.',
        ]);

        $user      = $request->user();
        $userId    = $user->getAuthIdentifier();
        $currentId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentId)
            ->delete();

        // Invalidates every outstanding "remember me" cookie. The current
        // browser stays signed in via its own (untouched) session row.
        $user->setRememberToken(Str::random(60));
        $user->save();

        activity('security')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('user.logged_out_other_devices');

        return back()->with('status', 'Všechny ostatní relace byly ukončeny.');
    }
}
