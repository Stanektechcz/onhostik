<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sub-accounts: keeps invited members out of the owner-only areas — billing,
 * payment methods, account deletion, data export and billing addresses.
 *
 * Enforced centrally by route name (rather than wrapping ~25 scattered routes
 * in a group), so a new billing route is covered as soon as it uses the
 * conventional name prefix. Owners and single-user accounts are unaffected: the
 * check only bites when the signed-in user is NOT the owner of the account they
 * are acting on.
 */
final class RestrictMembersFromBilling
{
    /** Route-name prefixes only the account owner may reach. */
    private const OWNER_ONLY = [
        'panel.billing.',
        'panel.billing-addresses.',
        'panel.payment-methods.',
        'panel.account.billing',
        'panel.account.delete',
        'panel.gdpr.export',
        'panel.services.billing-pause',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $name = $request->route()?->getName() ?? '';

        if ($user !== null && $this->isOwnerOnly($name) && ! $user->isCustomerOwner()) {
            abort(403, 'Tato sekce je dostupná pouze vlastníkovi účtu.');
        }

        return $next($request);
    }

    private function isOwnerOnly(string $name): bool
    {
        foreach (self::OWNER_ONLY as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
