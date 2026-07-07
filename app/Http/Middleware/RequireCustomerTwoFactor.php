<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects authenticated customers to 2FA setup when the admin has enabled
 * the "require_customer_2fa" security setting.
 *
 * Admins always pass through — they are governed by RequireAdminTwoFactor.
 * The check is skipped on the security/2FA setup page itself to avoid redirect loops.
 */
class RequireCustomerTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isAdmin()) {
            return $next($request);
        }

        if (! $this->settingEnabled()) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        // Skip the redirect on the security page itself to avoid loops.
        if ($request->routeIs('panel.account.security') || $request->routeIs('panel.account.security.*')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Dvoufázové ověření je povinné.'], 403);
        }

        return redirect()
            ->route('panel.account.security')
            ->with('warning', 'Pro pokračování je vyžadováno aktivní dvoufázové ověření (2FA). Prosím, nastavte jej.');
    }

    private function settingEnabled(): bool
    {
        $row = DB::table('settings')
            ->where('group', 'security')
            ->where('name', 'require_customer_2fa')
            ->value('payload');

        return $row !== null && json_decode((string) $row, true) === true;
    }
}
