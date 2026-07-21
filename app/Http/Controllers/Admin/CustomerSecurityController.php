<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

/**
 * Account-security actions an admin performs on a customer (audit G97):
 * trigger a password reset, and clear a 2FA setup the customer has lost
 * access to.
 *
 * Deliberately NOT "set the customer's password": an admin should never
 * know, type or transmit a customer's password. Sending the standard reset
 * link means the credential only ever exists between the customer and the
 * system — and there is nothing to leak into a note, log or support chat.
 */
class CustomerSecurityController extends Controller
{
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        activity('security')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['result' => $status])
            ->log('user.password_reset_sent');

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['security' => 'Odkaz pro obnovu hesla se nepodařilo odeslat.']);
        }

        return back()->with('status', "Odkaz pro obnovu hesla byl odeslán na {$user->email}.");
    }

    /**
     * Clear a customer's two-factor setup.
     *
     * For when they lost their authenticator and their recovery codes — the
     * only alternative is locking them out of their own account forever.
     * Heavily audited because it lowers the account's protection.
     */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            // Forces the operator to record why protection was lowered.
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        if ($user->two_factor_secret === null && $user->two_factor_confirmed_at === null) {
            return back()->withErrors(['security' => 'Uživatel nemá nastavené dvoufázové ověření.']);
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        activity('security')
            ->performedOn($user)
            ->causedBy($request->user())
            ->withProperties(['reason' => $validated['reason']])
            ->log('user.two_factor_reset_by_admin');

        return back()->with('status', 'Dvoufázové ověření bylo zrušeno — uživatel si jej může nastavit znovu.');
    }

    /** Force every other session of this user to sign out. */
    public function logoutEverywhere(Request $request, User $user): RedirectResponse
    {
        // Rotating the remember-token invalidates persistent logins.
        $user->forceFill(['remember_token' => \Illuminate\Support\Str::random(60)])->save();

        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();

        activity('security')
            ->performedOn($user)
            ->causedBy($request->user())
            ->log('user.sessions_revoked');

        return back()->with('status', 'Všechna přihlášení uživatele byla ukončena.');
    }
}
