<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Customer\Models\CustomerInvitation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Accepting a sub-account invitation.
 *
 * Security: the token is the credential (single-use, expiring, stored only as a
 * hash). An already-registered invitee must be signed in AS the invited email to
 * accept — we never sign an existing account in off a link. A brand-new invitee
 * sets their own password here; the invite itself proves control of the email,
 * so the address is marked verified.
 */
final class CustomerInvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = $this->pending($token);

        if ($invitation === null) {
            return view('invitations.accept', ['invalid' => true]);
        }

        $existingUser = User::where('email', $invitation->email)->first();
        $currentUser  = $request->user();

        return view('invitations.accept', [
            'invalid'       => false,
            'token'         => $token,
            'email'         => $invitation->email,
            'accountName'   => $this->accountName($invitation),
            // Which path the accept form should render.
            'needsAccount'  => $existingUser === null,
            'wrongUser'     => $currentUser !== null && $currentUser->email !== $invitation->email,
            'mustLogIn'     => $existingUser !== null && $currentUser === null,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->pending($token);

        abort_if($invitation === null, 404, 'Pozvánka je neplatná nebo vypršela.');

        $current = $request->user();

        // Existing user, signed in as the invited address → just grant access.
        if ($current !== null) {
            abort_unless($current->email === $invitation->email, 403, 'Tato pozvánka je pro jiný e-mail.');

            $this->grant($invitation, $current);

            return redirect()->route('panel.dashboard')->with('status', 'Pozvánka byla přijata.');
        }

        // Address already registered but nobody is signed in → they must log in.
        if (User::where('email', $invitation->email)->exists()) {
            return redirect()->route('login')->with('status', 'Přihlaste se prosím a poté pozvánku přijměte.');
        }

        // Brand-new invitee sets their own password.
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $invitation->email,
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $this->grant($invitation, $user);

        Auth::login($user);

        return redirect()->route('panel.dashboard')->with('status', 'Účet byl vytvořen a pozvánka přijata.');
    }

    private function grant(CustomerInvitation $invitation, User $user): void
    {
        $invitation->customer->members()->syncWithoutDetaching([
            $user->id => ['role' => $invitation->role],
        ]);

        $invitation->forceFill(['accepted_at' => now()])->save();
    }

    private function pending(string $token): ?CustomerInvitation
    {
        $invitation = CustomerInvitation::where('token_hash', hash('sha256', $token))->first();

        return ($invitation !== null && $invitation->isPending()) ? $invitation : null;
    }

    private function accountName(CustomerInvitation $invitation): string
    {
        $customer = $invitation->customer;

        return $customer->company_name ?: $customer->user->name;
    }
}
