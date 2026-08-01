<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer sub-accounts: makes the panel work for invited members without
 * touching the ~120 sites that read `$request->user()->customer`.
 *
 * An owner's `customer` relation resolves normally (customers.user_id). A member
 * owns no customer, so that relation is null — here we resolve their single
 * membership and bind it onto the authenticated user as the `customer` relation.
 * Every downstream `$user->customer` then returns the account they were invited
 * to, unchanged. Owners and users with no account are left exactly as they were.
 */
final class ResolveMemberCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // A user who belongs to several accounts can pick the active one; honour
        // that selection when it is still a valid membership.
        $active = $request->hasSession() ? $request->session()->get('active_customer_id') : null;

        if ($active !== null) {
            $selected = $user->memberCustomers()->where('customers.id', $active)->first();

            if ($selected !== null) {
                $user->setRelation('customer', $selected);

                return $next($request);
            }

            $request->session()->forget('active_customer_id'); // stale selection
        }

        // No selection: a member (who owns no account) falls back to their single
        // membership. Owners keep their owned account untouched.
        if ($user->customer === null) {
            $membership = $user->memberCustomers()->first();

            if ($membership !== null) {
                $user->setRelation('customer', $membership);
            }
        }

        return $next($request);
    }
}
