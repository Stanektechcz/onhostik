<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Enums\CustomerRole;
use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\CustomerInvitationNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Sub-account member management. Owner-only: an invited member cannot invite or
 * remove other members. Enforced here (and again on each write) because members
 * legitimately reach the rest of the panel.
 */
final class CustomerMemberController extends Controller
{
    private const MAX_MEMBERS = 10;

    public function index(Request $request): View
    {
        $customer = $this->ownedCustomer($request);

        return view('panel.account.members', [
            'customer'    => $customer,
            'members'     => $customer->members()->orderBy('customer_user.role')->get(),
            'invitations' => CustomerInvitation::query()
                ->where('customer_id', $customer->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->latest()
                ->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $customer = $this->ownedCustomer($request);

        $assignable = array_map(fn (CustomerRole $r): string => $r->value, CustomerRole::assignable());

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['nullable', 'in:' . implode(',', $assignable)],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $role  = CustomerRole::tryFrom((string) ($validated['role'] ?? '')) ?? CustomerRole::Member;

        if ($customer->members()->where('users.email', $email)->exists()) {
            return back()->withErrors(['email' => 'Tento uživatel už má k účtu přístup.']);
        }

        if ($customer->members()->count() >= self::MAX_MEMBERS) {
            return back()->withErrors(['email' => 'Byl dosažen maximální počet členů (' . self::MAX_MEMBERS . ').']);
        }

        // Re-inviting the same address replaces any earlier pending invite.
        CustomerInvitation::query()
            ->where('customer_id', $customer->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $raw = Str::random(64);

        $invitation = CustomerInvitation::create([
            'customer_id'        => $customer->id,
            'invited_by_user_id' => $request->user()?->id,
            'email'              => $email,
            'role'               => $role->value,
            'token_hash'         => hash('sha256', $raw),
            'expires_at'         => now()->addDays(7),
        ]);

        Notification::route('mail', $email)->notify(
            new CustomerInvitationNotification($invitation, $raw, $this->accountName($customer)),
        );

        return back()->with('status', 'Pozvánka byla odeslána na ' . $email . '.');
    }

    public function removeMember(Request $request, User $member): RedirectResponse
    {
        $customer = $this->ownedCustomer($request);

        abort_if($member->id === $customer->user_id, 403, 'Vlastníka účtu nelze odebrat.');

        $customer->members()->detach($member->id);

        return back()->with('status', 'Přístup člena byl odebrán.');
    }

    public function revokeInvitation(Request $request, CustomerInvitation $invitation): RedirectResponse
    {
        $customer = $this->ownedCustomer($request);

        abort_unless($invitation->customer_id === $customer->id, 403);

        $invitation->delete();

        return back()->with('status', 'Pozvánka byla zrušena.');
    }

    /** The current user's account — and a hard guarantee they own it. */
    private function ownedCustomer(Request $request): Customer
    {
        $user     = $request->user();
        $customer = $user?->accessibleCustomer();

        abort_if($customer === null, 404);
        abort_unless($user->isCustomerOwner($customer), 403, 'Členy účtu může spravovat pouze vlastník.');

        return $customer;
    }

    private function accountName(Customer $customer): string
    {
        return $customer->company_name ?: $customer->user->name;
    }
}
