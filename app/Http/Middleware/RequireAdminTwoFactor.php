<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that admin users have confirmed 2FA before they can access admin routes.
 *
 * Enforcement is OPT-IN via the `security.require_admin_2fa` setting (default
 * OFF). This lets a fresh install / launch reach the admin panel immediately;
 * an admin can then turn the requirement on from Nastavení zabezpečení once
 * they have their own 2FA configured, avoiding a lock-out loop.
 *
 * Applies only to users with the 'admin' role. Non-admins pass through.
 * Register this after auth + can:access-admin so $user is guaranteed.
 */
class RequireAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            return $next($request);
        }

        if (! $this->enforced()) {
            return $next($request);
        }

        // Already on the security page? Never redirect onto itself (loop guard).
        if ($request->routeIs('admin.account.security')) {
            return $next($request);
        }

        // Admin without confirmed 2FA → redirect to security page
        if (is_null($user->two_factor_confirmed_at)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Dvoufázové ověření je povinné pro administrátory.'], 403);
            }

            return redirect()
                ->route('admin.account.security')
                ->with('warning', 'Administrátoři jsou povinni mít aktivované dvoufázové ověření (2FA). Prosím, nastavte jej níže v sekci Dvoufázové ověření.');
        }

        return $next($request);
    }

    private function enforced(): bool
    {
        try {
            $row = \Illuminate\Support\Facades\DB::table('settings')
                ->where('group', 'security')
                ->where('name', 'require_admin_2fa')
                ->value('payload');
        } catch (\Throwable) {
            return false;
        }

        return $row !== null && json_decode((string) $row, true) === true;
    }
}
