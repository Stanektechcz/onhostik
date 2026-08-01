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
    /** Billing areas: reachable by the owner OR an accountant member. */
    private const BILLING = [
        'panel.billing.',
        'panel.billing-addresses.',
        'panel.payment-methods.',
        'panel.account.billing',
        'panel.services.billing-pause',
    ];

    /** Strictly owner-only: account deletion and personal-data export. */
    private const OWNER_STRICT = [
        'panel.account.delete',
        'panel.gdpr.export',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $name = $request->route()?->getName() ?? '';

        if ($user !== null) {
            if ($this->matches($name, self::OWNER_STRICT) && ! $user->isCustomerOwner()) {
                abort(403, 'Tato sekce je dostupná pouze vlastníkovi účtu.');
            }

            if ($this->matches($name, self::BILLING) && ! $user->canAccessBilling()) {
                abort(403, 'Tato sekce je dostupná vlastníkovi nebo účetnímu.');
            }
        }

        return $next($request);
    }

    /** @param list<string> $prefixes */
    private function matches(string $name, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
