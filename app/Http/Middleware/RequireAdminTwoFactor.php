<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that admin users have confirmed 2FA before they can access admin routes.
 *
 * Applies only to users who have the 'admin' role. Non-admin users pass through
 * unchanged. Admins without confirmed 2FA are redirected to the security settings
 * page with a one-time warning message.
 *
 * Register this after the auth + can:access-admin middleware so $user is guaranteed.
 */
class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            return $next($request);
        }

        // Admin without confirmed 2FA → redirect to security page
        if (is_null($user->two_factor_confirmed_at)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Dvoufázové ověření je povinné pro administrátory.'], 403);
            }

            return redirect()
                ->route('panel.account.security')
                ->with('warning', 'Administrátoři jsou povinni mít aktivované dvoufázové ověření (2FA). Prosím, nastavte jej.');
        }

        return $next($request);
    }
}
